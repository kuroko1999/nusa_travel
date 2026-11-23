<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\TravelRoute;
use App\Models\Vehicle;
use App\Services\BookingService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $routes = TravelRoute::where('active', true)->get();
        $schedules = Schedule::with(['route', 'vehicle'])->where('status', 'terjadwal')->where('departs_at', '>', now())->orderBy('departs_at')->limit(6)->get();
        $vehicles = Vehicle::where('status', 'aktif')->get();
        $reviews = Review::with('user')->where('approved', true)->latest()->limit(3)->get();

        return view('home', compact('routes', 'schedules', 'vehicles', 'reviews'));
    }

    public function search(Request $request, BookingService $service)
    {
        $service->expireDue();
        $data = $request->validate(['origin' => 'nullable|string|max:100', 'destination' => 'nullable|string|max:100', 'date' => 'nullable|date', 'passengers' => 'nullable|integer|min:1|max:12', 'model' => 'nullable|in:Isuzu Panther,Toyota Avanza']);
        $query = Schedule::with(['route', 'vehicle'])->where('status', 'terjadwal')->where('departs_at', '>', now())->whereHas('vehicle', fn ($q) => $q->where('status', 'aktif'));
        if ($data['origin'] ?? null) {
            $query->whereHas('route', fn ($q) => $q->where('origin', $data['origin']));
        }
        if ($data['destination'] ?? null) {
            $query->whereHas('route', fn ($q) => $q->where('destination', $data['destination']));
        }
        if ($data['date'] ?? null) {
            $query->whereDate('departs_at', $data['date']);
        }
        if ($data['model'] ?? null) {
            $query->whereHas('vehicle', fn ($q) => $q->where('model', $data['model']));
        }
        if ($data['passengers'] ?? null) {
            $query->whereHas('vehicle', fn ($q) => $q->whereRaw('vehicles.capacity - (SELECT COUNT(*) FROM booking_seats WHERE booking_seats.schedule_id = schedules.id) >= ?', [(int) $data['passengers']]));
        }
        $schedules = $query->orderBy('departs_at')->paginate(12)->withQueryString();
        $schedules->getCollection()->transform(function ($s) {
            $s->available = $s->vehicle->seats()->where('selectable', true)->count() - $s->occupiedSeats()->count();

            return $s;
        });
        $routes = TravelRoute::where('active', true)->get();

        return view('search', compact('schedules', 'routes', 'data'));
    }

    public function show(Schedule $schedule, BookingService $service)
    {
        $service->expireDue();
        $schedule->load(['route', 'vehicle.seats', 'driver']);
        abort_unless($schedule->status === 'terjadwal' && $schedule->departs_at->isFuture(), 404);
        $occupied = $schedule->occupiedSeats()->pluck('vehicle_seat_id')->all();

        return view('schedule', compact('schedule', 'occupied'));
    }

    public function information(string $type)
    {
        $labels = ['terms' => 'Syarat dan Ketentuan', 'cancellation' => 'Kebijakan Pembatalan', 'refund' => 'Ketentuan Refund'];
        abort_unless(isset($labels[$type]), 404);
        $title = $labels[$type];
        $body = Setting::find($type)?->value;

        return view('information', compact('title', 'body'));
    }

    public function check(Request $request)
    {
        $code = $request->validate(['code' => 'required|string|max:40'])['code'];
        $booking = Booking::with('schedule.route')->where('code',$code)->first();

        return view('check',compact('booking'));
    }
}
