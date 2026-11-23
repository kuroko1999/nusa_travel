<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementController extends Controller
{
    public function bookings(Request $request, BookingService $service)
    {
        // Rapikan pesanan yang sudah lewat batas bayar agar daftar admin tidak menampilkan status usang bila scheduler tidak berjalan.
        $service->expireDue();
        $bookings = Booking::with('user', 'passengers', 'schedule.route')->when($request->code, fn ($q) => $q->where('code', 'like', '%'.$request->code.'%'))->when($request->status, fn ($q) => $q->where('status', $request->status))->latest()->paginate(20)->withQueryString();

        return view('admin.bookings', compact('bookings'));
    }

    public function payments(BookingService $service)
    {
        $service->expireDue();

        return view('admin.payments', ['payments' => Payment::with('booking.user', 'booking.passengers', 'booking.schedule.route')->latest()->paginate(20)]);
    }

    public function approve(Payment $payment, PaymentService $service)
    {
        if ($payment->method !== 'transfer' || ! $payment->proof_path) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran ini tidak dapat disetujui: metode bukan transfer bank atau bukti transfer belum diunggah.']);
        }
        $service->approve($payment);
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'payment_approved', 'subject_type' => Payment::class, 'subject_id' => $payment->id]);

        return back()->with('status', 'Pembayaran disetujui.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        if ($payment->method !== 'transfer' || $payment->status !== 'pending') {
            throw ValidationException::withMessages(['reason' => 'Pembayaran ini sudah diproses atau bukan transfer bank.']);
        }
        $payment->update(['status' => 'gagal', 'rejection_reason' => $data['reason']]);
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'payment_rejected', 'subject_type' => Payment::class, 'subject_id' => $payment->id, 'details' => $data]);

        return back()->with('status', 'Pembayaran ditolak.');
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path, 404);

        return Storage::disk('local')->download($payment->proof_path);
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::with('booking.user', 'booking.passengers')->when($request->filled('number'), function ($query) use ($request) {
            $term = '%'.trim((string) $request->number).'%';
            $query->where(fn ($w) => $w->where('number', 'like', $term)
                ->orWhereHas('booking', fn ($b) => $b->where('code', 'like', $term)->orWhere('phone', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))));
        })->latest()->paginate(20)->withQueryString();

        return view('admin.invoices', compact('invoices'));
    }

    public function customers(Request $request)
    {
        $customers = User::where('role', 'pelanggan')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->withCount('bookings')
            ->withSum(['bookings as total_spent' => fn ($q) => $q->where('payment_status', 'lunas')], 'total')
            ->latest()->paginate(20)->withQueryString();

        return view('admin.customers', compact('customers'));
    }

    public function settings()
    {
        return view('admin.settings', ['settings' => Setting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['company_name' => 'required|string|max:100', 'company_address' => 'nullable|string|max:255', 'whatsapp' => 'nullable|string|max:30', 'email' => 'nullable|email', 'hero_title' => 'nullable|string|max:150', 'footer' => 'nullable|string|max:500', 'terms' => 'nullable|string|max:2000', 'cancellation' => 'nullable|string|max:2000', 'refund' => 'nullable|string|max:2000', 'pickup_fee' => 'required|integer|min:0', 'dropoff_fee' => 'required|integer|min:0', 'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048', 'favicon' => 'nullable|image|mimes:png,jpg,jpeg|max:512', 'hero_image' => 'nullable|image|mimes:png,jpg,jpeg|max:4096']);
        foreach (['logo', 'favicon', 'hero_image'] as $file) {
            if ($request->hasFile($file)) {
                $data[$file] = $request->file($file)->store('branding', 'public');
            } else {
                unset($data[$file]);
            }
        }
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('status', 'Pengaturan disimpan.');
    }

    public function status(Request $request, Booking $booking)
    {
        $status = $request->validate(['status' => 'required|in:dikonfirmasi,selesai,dibatalkan'])['status'];

        if ($status === 'dibatalkan') {
            app(BookingService::class)->cancel($booking);
        } else {
            if ($booking->payment_status !== 'lunas') {
                throw ValidationException::withMessages(['status' => 'Pesanan harus lunas sebelum status "'.str_replace('_', ' ', $status).'" dapat ditetapkan. Verifikasi pembayaran terlebih dahulu.']);
            }
            $statusDiizinkan = $status === 'dikonfirmasi' ? ['menunggu_pembayaran'] : ['menunggu_pembayaran', 'dikonfirmasi'];
            if (! in_array($booking->status, $statusDiizinkan, true)) {
                throw ValidationException::withMessages(['status' => 'Status "'.str_replace('_', ' ', $booking->status).'" tidak dapat diubah menjadi "'.str_replace('_', ' ', $status).'".']);
            }
            $booking->update(['status' => $status]);
        }
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'booking_status', 'subject_type' => Booking::class, 'subject_id' => $booking->id, 'details' => ['status' => $status]]);

        return back()->with('status', 'Status pesanan diperbarui.');
    }

    public function checkin(Request $request, Booking $booking)
    {
        if ($booking->payment_status !== 'lunas') {
            throw ValidationException::withMessages(['passenger_ids' => 'Check-in hanya tersedia untuk pesanan yang sudah lunas.']);
        }
        if (in_array($booking->status, ['dibatalkan', 'kedaluwarsa'], true)) {
            throw ValidationException::withMessages(['passenger_ids' => 'Pesanan sudah dibatalkan atau kedaluwarsa.']);
        }
        $ids = $request->validate([
            'passenger_ids' => ['required', 'array', 'min:1'],
            'passenger_ids.*' => [Rule::exists('booking_passengers', 'id')->where('booking_id', $booking->id)],
        ], [
            'passenger_ids.required' => 'Pilih minimal satu penumpang untuk check-in.',
            'passenger_ids.min' => 'Pilih minimal satu penumpang untuk check-in.',
        ])['passenger_ids'];
        $booking->passengers()->whereIn('id', $ids)->update(['checked_in_at' => now()]);

        return back()->with('status', 'Check-in berhasil.');
    }
}
