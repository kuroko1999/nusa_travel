<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'bookings_today' => Booking::whereDate('created_at', today())->count(),
            'passengers' => Booking::where('payment_status', 'lunas')->sum('passenger_count'),
            'revenue_today' => Booking::where('payment_status', 'lunas')->whereDate('paid_at', today())->sum('total'),
            'revenue_month' => Booking::where('payment_status', 'lunas')->whereYear('paid_at', now()->year)->whereMonth('paid_at', now()->month)->sum('total'),
            'vehicles' => Vehicle::where('status', 'aktif')->count(),
            'schedules' => Schedule::where('departs_at', '>', now())->where('status', 'terjadwal')->count(),
            'pending' => Booking::where('status', 'menunggu_pembayaran')->count(),
            'completed' => Booking::where('status', 'selesai')->count(),
        ];
        $bookings = Booking::with('user', 'passengers', 'schedule.route')->latest()->limit(8)->get();
        $chart = Booking::where('payment_status', 'lunas')->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())->get(['paid_at', 'total'])->groupBy(fn ($b) => $b->paid_at->format('Y-m'))->map(fn ($rows, $month) => (object) ['month' => $month, 'total' => $rows->sum('total')])->values();

        return view('admin.dashboard',compact('stats','bookings','chart'));
    }
}
