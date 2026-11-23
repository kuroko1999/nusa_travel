<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function manual(Booking $booking): Payment
    {
        if ($booking->status !== 'menunggu_pembayaran' || $booking->expires_at->isPast()) {
            throw ValidationException::withMessages(['booking' => 'Pesanan sudah kedaluwarsa.']);
        }

        return Payment::where('booking_id', $booking->id)->where('method', 'transfer')->where('status', 'pending')->latest()->first() ?: Payment::create(['booking_id' => $booking->id, 'method' => 'transfer', 'amount' => $booking->total]);
    }

    public function midtrans(Booking $booking): Payment
    {
        if ($booking->status !== 'menunggu_pembayaran' || $booking->expires_at->isPast()) {
            throw ValidationException::withMessages(['booking' => 'Pesanan sudah kedaluwarsa.']);
        }
        $key = config('services.midtrans.server_key');
        if (! $key) {
            throw ValidationException::withMessages(['payment' => 'Midtrans belum dikonfigurasi. Gunakan transfer bank.']);
        }
        $payment = Payment::where('booking_id', $booking->id)->where('method', 'midtrans')->where('status', 'pending')->latest()->first() ?: Payment::create([
            'booking_id' => $booking->id, 'method' => 'midtrans', 'amount' => $booking->total, 'gateway_order_id' => $booking->code.'-'.Str::upper(Str::random(5)),
        ]);
        if ($payment->redirect_url) {
            return $payment;
        }
        $url = config('services.midtrans.production') ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $response = Http::withBasicAuth($key, '')->acceptJson()->post($url, [
            'transaction_details' => ['order_id' => $payment->gateway_order_id, 'gross_amount' => $booking->total],
            'customer_details' => array_filter([
                'first_name' => $booking->customerName(),
                'email' => $booking->user?->email,
                'phone' => $booking->phone,
            ], fn ($value) => $value !== null),
            'expiry' => ['unit' => 'minute', 'duration' => (int) max(1, now()->diffInMinutes($booking->expires_at, false))],
        ]);
        if (! $response->successful() || ! $response->json('redirect_url')) {
            throw ValidationException::withMessages(['payment' => 'Midtrans belum dapat membuat transaksi.']);
        }
        $payment->update(['redirect_url' => $response->json('redirect_url')]);

        return $payment;
    }

    public function approve(Payment $payment): Invoice
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'lunas') {
                // Notifikasi ulang webhook atau persetujuan ganda untuk pembayaran yang sama: cukup kembalikan faktur yang sudah ada.
                return Invoice::where('booking_id', $payment->booking_id)->firstOrFail();
            }
            $booking = Booking::with(['schedule.route', 'schedule.vehicle', 'passengers.seat', 'user'])->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            if ($booking->status === 'dibatalkan' || $booking->status === 'kedaluwarsa' || ($booking->payment_status !== 'lunas' && $booking->expires_at->isPast())) {
                throw ValidationException::withMessages(['payment' => 'Pesanan sudah kedaluwarsa atau dibatalkan.']);
            }
            if ($booking->payment_status === 'lunas') {
                throw ValidationException::withMessages(['payment' => 'Pesanan sudah lunas melalui pembayaran lain. Selesaikan pengembalian dana secara manual.']);
            }
            if ($payment->amount !== $booking->total) {
                throw ValidationException::withMessages(['payment' => 'Nominal pembayaran tidak cocok.']);
            }
            $payment->update(['status' => 'lunas', 'verified_at' => $payment->verified_at ?? now()]);
            $booking->update(['status' => 'dikonfirmasi', 'payment_status' => 'lunas', 'paid_at' => $booking->paid_at ?? now()]);
            if ($existing = Invoice::where('booking_id', $booking->id)->first()) {
                return $existing;
            }
            $day = now()->toDateString();
            DB::table('invoice_counters')->insertOrIgnore(['day' => $day, 'sequence' => 0]);
            $sequence = DB::table('invoice_counters')->where('day', $day)->lockForUpdate()->value('sequence') + 1;
            DB::table('invoice_counters')->where('day', $day)->update(['sequence' => $sequence]);
            $snapshot = [
                'company' => Setting::find('company_name')?->value ?? 'Nusa Travel',
                'address' => Setting::find('company_address')?->value ?? '',
                'whatsapp' => Setting::find('whatsapp')?->value ?? '',
                'customer' => $booking->customerName(), 'customer_phone' => $booking->phone,
                'origin' => $booking->schedule->route->origin, 'destination' => $booking->schedule->route->destination,
                'departs_at' => $booking->schedule->departs_at->toIso8601String(),
                'vehicle' => $booking->schedule->vehicle->model, 'plate' => $booking->schedule->vehicle->plate,
                'seats' => $booking->passengers->pluck('seat.number')->all(),
                'fare' => $booking->fare_snapshot, 'count' => $booking->passenger_count,
                'pickup_fee' => $booking->pickup_fee, 'dropoff_fee' => $booking->dropoff_fee,
                'discount' => $booking->discount, 'total' => $booking->total, 'method' => $payment->method,
            ];
            $invoice = Invoice::create(['booking_id' => $booking->id, 'number' => 'INV-'.now()->format('Ymd').'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT), 'snapshot' => $snapshot, 'issued_at' => now()]);
            $invoice->items()->create(['description' => 'Tiket '.$snapshot['origin'].' – '.$snapshot['destination'], 'quantity' => $booking->passenger_count, 'unit_price' => $booking->fare_snapshot, 'amount' => $booking->fare_snapshot * $booking->passenger_count]);
            foreach (['pickup_fee' => 'Biaya penjemputan', 'dropoff_fee' => 'Biaya pengantaran'] as $field => $label) {
                if ($booking->$field) {
                    $invoice->items()->create(['description' => $label, 'quantity' => 1, 'unit_price' => $booking->$field, 'amount' => $booking->$field]);
                }
            }

            return $invoice;
        }, 3);
    }

    public function webhook(array $data): void
    {
        $key = config('services.midtrans.server_key');
        if (! $key || ! hash_equals(hash('sha512', ($data['order_id'] ?? '').($data['status_code'] ?? '').($data['gross_amount'] ?? '').$key), $data['signature_key'] ?? '')) {
            abort(403);
        }
        $payment = Payment::where('gateway_order_id', $data['order_id'])->where('method', 'midtrans')->first();
        if ($payment === null) {
            // Midtrans mengulang notifikasi sampai menerima respons 2xx; catat dan akui agar tidak berulang tanpa akhir.
            Log::warning('Notifikasi Midtrans untuk order yang tidak dikenal diabaikan.', ['order_id' => $data['order_id'] ?? null]);

            return;
        }
        if ((int) round((float) ($data['gross_amount'] ?? 0)) !== $payment->amount) {
            abort(403);
        }
        $eventKey = hash('sha256', implode('|', [$data['order_id'], $data['transaction_id'] ?? '', $data['transaction_status'] ?? '', $data['fraud_status'] ?? '']));
        DB::transaction(function () use ($payment, $data, $eventKey) {
            // Unique index pada event_key menutup balapan antar-notifikasi yang datang bersamaan.
            $inserted = DB::table('payment_events')->insertOrIgnore(['payment_id' => $payment->id, 'event_key' => $eventKey, 'status' => $data['transaction_status'], 'payload' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
            if ($inserted === 0) {
                return;
            }
            $status = $data['transaction_status'];
            if (in_array($status, ['settlement', 'capture']) && ($data['fraud_status'] ?? 'accept') === 'accept') {
                if ($payment->booking->payment_status === 'lunas' && $payment->status !== 'lunas') {
                    // Dana masuk lewat kanal kedua setelah pesanan lunas: jangan ubah status pesanan, tandai untuk refund manual.
                    $payment->update(['status' => 'refund']);
                    Log::warning('Pembayaran Midtrans masuk untuk pesanan yang sudah lunas; ditandai refund.', ['payment_id' => $payment->id, 'booking_id' => $payment->booking_id]);
                } else {
                    $this->approve($payment);
                }
            } elseif (in_array($status, ['refund', 'partial_refund'])) {
                $payment->update(['status' => 'refund']);
                $payment->booking->update(['status' => 'dibatalkan', 'payment_status' => 'refund']);
                $payment->booking->invoice?->update(['status' => 'refund']);
                BookingSeat::where('booking_id', $payment->booking_id)->delete();
            } elseif (in_array($status, ['deny', 'cancel', 'expire', 'failure'])) {
                if ($payment->status !== 'lunas') {
                    $payment->update(['status' => $status === 'expire' ? 'kedaluwarsa' : 'gagal']);
                }
                if ($status === 'expire' && $payment->booking->payment_status !== 'lunas') {
                    app(BookingService::class)->expireDue();
                }
            }
        }, 3);
    }
}
