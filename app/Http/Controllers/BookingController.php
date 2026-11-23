<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReserveBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function store(ReserveBookingRequest $request, BookingService $service)
    {
        $userId = $request->user()?->id;
        $guestKey = $userId === null ? bin2hex(random_bytes(32)) : null;
        $booking = $service->reserve(
            $userId,
            $request->validated(),
            $guestKey === null ? null : hash('sha256', $guestKey),
        );

        if ($guestKey !== null) {
            $booking->grantGuestAccess($request);
            $request->session()->flash('guest_key_'.$booking->id, Crypt::encryptString($guestKey));
        }

        return redirect()->route('bookings.show', $booking);
    }

    public function check()
    {
        return view('check');
    }

    public function recover(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'guest_key' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);
        $booking = Booking::where('code', strtoupper($data['code']))->first();
        if ($booking !== null && Gate::allows('view', $booking)) {
            return redirect()->route('bookings.show', $booking);
        }

        if ($booking?->user_id === null && $booking?->guest_key_hash !== null
            && hash_equals($booking->guest_key_hash, hash('sha256', strtolower($data['guest_key'] ?? '')))) {
            $booking->grantGuestAccess($request);

            return redirect()->route('bookings.show', $booking);
        }

        return redirect()->route('booking.check')
            ->withErrors(['code' => 'Kode booking atau kunci akses tidak cocok.']);
    }

    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()->with(['schedule.route', 'invoice'])->latest()->paginate(12);

        return view('customer.dashboard', compact('bookings'));
    }

    public function documents(string $type)
    {
        abort_unless(in_array($type, ['tiket', 'faktur']), 404);
        $bookings = auth()->user()->bookings()->where('payment_status', 'lunas')->with(['schedule.route', 'invoice'])->latest()->paginate(12);

        return view('customer.documents', compact('bookings', 'type'));
    }

    public function show(Request $request, Booking $booking)
    {
        Gate::authorize('view', $booking);
        $booking->load(['schedule.route', 'schedule.vehicle', 'passengers.seat', 'payments', 'invoice']);

        $encryptedKey = $request->session()->pull('guest_key_'.$booking->id);
        $guestKey = $encryptedKey === null ? null : Crypt::decryptString($encryptedKey);

        return view('customer.booking', compact('booking', 'guestKey'));
    }

    public function cancel(Booking $booking, BookingService $service)
    {
        Gate::authorize('cancel', $booking);
        $service->cancel($booking);

        return back()->with('status','Pesanan dibatalkan.');
    }
}
