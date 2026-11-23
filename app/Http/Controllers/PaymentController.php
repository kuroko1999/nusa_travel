<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function choose(Request $request, Booking $booking, PaymentService $service)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $method = $request->validate(['method' => 'required|in:transfer,midtrans'])['method'];
        $payment = $method === 'transfer' ? $service->manual($booking) : $service->midtrans($booking);

        return $payment->redirect_url ? redirect()->away($payment->redirect_url) : redirect()->route('bookings.show', $booking);
    }

    public function proof(Request $request, Payment $payment)
    {
        abort_unless($payment->booking->user_id === $request->user()->id, 403);
        abort_unless($payment->method === 'transfer' && $payment->status === 'pending' && $payment->booking->expires_at->isFuture(), 403);
        $file = $request->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096'])['proof'];
        $payment->update(['proof_path' => $file->store('payment-proofs', 'local')]);

        return back()->with('status', 'Bukti transfer terkirim.');
    }

    public function webhook(Request $request, PaymentService $service)
    {
        $service->webhook($request->all());

        return response()->json(['ok' => true]);
    }
}
