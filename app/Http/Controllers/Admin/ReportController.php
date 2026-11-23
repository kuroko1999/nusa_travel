<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $bookings = Booking::with('user', 'passengers', 'schedule.route')->where('payment_status', 'lunas')
            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['from']))
            ->when($data['to'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['to']))->latest('paid_at')->paginate(30)->withQueryString();
        $total = (clone $bookings->getCollection())->sum('total');

        return view('admin.reports', compact('bookings', 'total'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        return response()->streamDownload(function () use ($data) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Kode', 'Tanggal Lunas', 'Pelanggan', 'Rute', 'Penumpang', 'Total']);
            Booking::with('user', 'passengers', 'schedule.route')->where('payment_status', 'lunas')->when($data['from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['from']))->when($data['to'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['to']))->orderBy('id')->chunk(200, function ($bookings) use ($f) {
                foreach ($bookings as $b) {
                    fputcsv($f, [$b->code, $b->paid_at?->format('Y-m-d H:i'), $b->customerName(), $b->schedule->route->origin.' - '.$b->schedule->route->destination, $b->passenger_count, $b->total]);
                }
            });
            fclose($f);
        }, 'laporan-transaksi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function manifest(Schedule $schedule)
    {
        $schedule->load('route', 'vehicle', 'driver');
        $bookings = Booking::with('passengers.seat', 'user')->where('schedule_id', $schedule->id)->where('payment_status','lunas')->get();

        return view('admin.manifest',compact('schedule','bookings'));
    }
}
