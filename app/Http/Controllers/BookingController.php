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
        $booking = $service->reserve($request->user()->id, $request->validated());

        return redirect()->route('bookings.show', $booking);
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
        Gate::authorize('view', $booking);
        $service->cancel($booking);

        return back()->with('status','Pesanan dibatalkan.');
    }
}
