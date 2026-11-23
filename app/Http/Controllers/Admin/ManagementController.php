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

class ManagementController extends Controller
{
    public function bookings(Request $request)
    {
        $bookings = Booking::with('user', 'passengers', 'schedule.route')->when($request->code, fn ($q) => $q->where('code', 'like', '%'.$request->code.'%'))->when($request->status, fn ($q) => $q->where('status', $request->status))->latest()->paginate(20);

        return view('admin.bookings', compact('bookings'));
    }

    public function payments()
    {
        return view('admin.payments', ['payments' => Payment::with('booking.user', 'booking.passengers')->latest()->paginate(20)]);
    }

    public function approve(Payment $payment, PaymentService $service)
    {
        abort_unless($payment->method === 'transfer' && $payment->proof_path, 422);
        $service->approve($payment);
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'payment_approved', 'subject_type' => Payment::class, 'subject_id' => $payment->id]);

        return back()->with('status', 'Pembayaran disetujui.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        abort_unless($payment->method === 'transfer' && $payment->status === 'pending', 422);
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
        $invoices = Invoice::with('booking.user', 'booking.passengers')->when($request->number, fn ($q) => $q->where('number', 'like', '%'.$request->number.'%'))->latest()->paginate(20);

        return view('admin.invoices', compact('invoices'));
    }

    public function customers()
    {
        return view('admin.customers', ['customers' => User::where('role', 'pelanggan')->latest()->paginate(20)]);
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
        }foreach ($data as $key => $value) {
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
            abort_unless($booking->payment_status === 'lunas', 422);
            $booking->update(['status' => $status]);
        }
        AuditLog::create(['user_id' => auth()->id(), 'action' => 'booking_status', 'subject_type' => Booking::class, 'subject_id' => $booking->id, 'details' => ['status' => $status]]);

        return back()->with('status', 'Status pesanan diperbarui.');
    }

    public function checkin(Request $request, Booking $booking)
    {
        abort_unless($booking->payment_status === 'lunas', 422);
        $ids = $request->validate(['passenger_ids' => 'required|array', 'passenger_ids.*' => 'integer|exists:booking_passengers,id'])['passenger_ids'];
        $booking->passengers()->whereIn('id', $ids)->update(['checked_in_at' => now()]);

        return back()->with('status','Check-in berhasil.');
    }
}
