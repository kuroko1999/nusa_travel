<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReserveBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function store(ReserveBookingRequest $request, BookingService $service)
    {
        $userId = $request->user()?->id;
        $booking = $service->reserve($userId, $request->validated());

        if ($userId === null) {
            $booking->grantGuestAccess($request);
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
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $booking = Booking::where('code', strtoupper(trim($data['code'])))->first();
        if ($booking !== null && Gate::allows('view', $booking)) {
            return redirect()->route('bookings.show', $booking);
        }

        $nomor = Booking::normalizePhone($data['phone']);
        if ($nomor !== '' && $booking !== null && $booking->user_id === null
            && hash_equals(Booking::normalizePhone($booking->phone), $nomor)) {
            $booking->grantGuestAccess($request);

            return redirect()->route('bookings.show', $booking);
        }

        return redirect()->route('booking.check')
            ->withErrors(['code' => 'Kode booking atau nomor WhatsApp tidak cocok.'])
            ->withInput($request->only('code'));
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

    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);
        $booking->load(['schedule.route', 'schedule.vehicle', 'passengers.seat', 'payments', 'invoice']);

        return view('customer.booking', compact('booking'));
    }

    public function cancel(Booking $booking, BookingService $service)
    {
        Gate::authorize('cancel', $booking);
        $service->cancel($booking);

        return back()->with('status', 'Pesanan dibatalkan.');
    }
}
