<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Tanggal filter divalidasi sebagai tanggal saja; urutan from/to diperiksa manual agar galat
     * tidak memicu redirect berulang ke URL filter yang sama (form ini memakai GET).
     */
    private function filters(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        return [$data['from'] ?? null, $data['to'] ?? null];
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->filters($request);
        if ($from !== null && $to !== null && $to < $from) {
            return redirect()->route('admin.reports', ['from' => $from])
                ->withErrors(['to' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.']);
        }
        $query = Booking::with('user', 'passengers', 'schedule.route')->where('payment_status', 'lunas')
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to));
        // Total pelanggan dan pendapatan dihitung dari seluruh hasil filter, bukan hanya baris yang tampil di halaman ini.
        $total = (clone $query)->sum('total');
        $passengers = (clone $query)->sum('passenger_count');
        $bookings = $query->latest('paid_at')->paginate(30)->withQueryString();

        return view('admin.reports', compact('bookings', 'total', 'passengers'));
    }

    public function csv(Request $request)
    {
        [$from, $to] = $this->filters($request);
        if ($from !== null && $to !== null && $to < $from) {
            return redirect()->route('admin.reports', ['from' => $from])
                ->withErrors(['to' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.']);
        }

        return response()->streamDownload(function () use ($from, $to) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Kode', 'Tanggal Lunas', 'Pelanggan', 'Rute', 'Penumpang', 'Metode', 'Status', 'Total']);
            Booking::with('user', 'passengers', 'schedule.route', 'payments')->where('payment_status', 'lunas')
                ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
                ->orderBy('id')->chunk(200, function ($bookings) use ($f) {
                    foreach ($bookings as $b) {
                        $metode = $b->payments->firstWhere('status', 'lunas')?->method ?? $b->payments->last()?->method ?? '-';
                        fputcsv($f, [$b->code, $b->paid_at?->format('Y-m-d H:i'), $b->customerName(), $b->schedule->route->origin.' - '.$b->schedule->route->destination, $b->passenger_count, $metode, $b->status, $b->total]);
                    }
                });
            fclose($f);
        }, 'laporan-transaksi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function manifest(Schedule $schedule)
    {
        $schedule->load('route', 'vehicle', 'driver');
        $bookings = Booking::with('passengers.seat', 'user')->where('schedule_id', $schedule->id)->where('payment_status', 'lunas')->get();

        return view('admin.manifest', compact('schedule', 'bookings'));
    }
}
