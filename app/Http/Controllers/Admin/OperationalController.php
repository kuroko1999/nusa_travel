<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperationalRequest;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BookingPassenger;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\TravelRoute;
use App\Models\Vehicle;
use App\Models\VehicleSeat;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalController extends Controller
{
    private function model(string $type): string
    {
        return match ($type) {
            'vehicles' => Vehicle::class,'drivers' => Driver::class,'routes' => TravelRoute::class,'schedules' => Schedule::class,'bank-accounts' => BankAccount::class,default => abort(404)
        };
    }

    public function index(string $type)
    {
        $model = $this->model($type);
        $items = $model::latest()->paginate(15);

        return view('admin.resources', compact('type', 'items'));
    }

    public function create(string $type)
    {
        $this->model($type);
        $item = null;
        $routes = TravelRoute::where('active', true)->get();
        $vehicles = Vehicle::where('status', 'aktif')->get();
        $drivers = Driver::where('active', true)->get();

        return view('admin.resource-form', compact('type', 'item', 'routes', 'vehicles', 'drivers'));
    }

    public function edit(string $type, int $id)
    {
        $model = $this->model($type);
        $item = $model::findOrFail($id);
        $routes = TravelRoute::where('active', true)->get();
        $vehicles = Vehicle::where('status', 'aktif')->get();
        $drivers = Driver::where('active', true)->get();

        return view('admin.resource-form', compact('type', 'item', 'routes', 'vehicles', 'drivers'));
    }

    public function store(OperationalRequest $request, string $type)
    {
        $model = $this->model($type);
        $item = DB::transaction(fn () => $this->save($model, new $model, $request->validated(), $request));

        return redirect()->route('admin.resources.index', $type)->with('status', 'Data ditambahkan.');
    }

    public function update(OperationalRequest $request, string $type, int $id)
    {
        $model = $this->model($type);
        DB::transaction(fn () => $this->save($model, $model::findOrFail($id), $request->validated(), $request));

        return redirect()->route('admin.resources.index', $type)->with('status', 'Data diperbarui.');
    }

    private function save(string $model, $item, array $data, Request $request)
    {
        if ($model === Schedule::class) {
            $route = TravelRoute::findOrFail($data['route_id']);
            $start = Carbon::parse($data['departs_at']);
            $end = $start->copy()->addMinutes($route->duration_minutes);
            if ($start->isPast() && ! $item->exists) {
                throw ValidationException::withMessages(['departs_at' => 'Waktu berangkat harus di masa depan.']);
            }
            $buffer = 60;
            $overlap = Schedule::where('id', '!=', $item->id ?? 0)->whereNotIn('status', ['selesai', 'dibatalkan'])
                ->where(function ($q) use ($data) {
                    $q->where('vehicle_id', $data['vehicle_id']);
                    if ($data['driver_id'] ?? null) {
                        $q->orWhere('driver_id', $data['driver_id']);
                    }
                })
                ->where('departs_at', '<', $end->copy()->addMinutes($buffer))->where('arrives_at', '>', $start->copy()->subMinutes($buffer))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['departs_at' => 'Kendaraan atau sopir sudah terjadwal pada waktu ini.']);
            }
            $data['arrives_at'] = $end;
        }
        if ($model === Vehicle::class) {
            if ($item->exists && $item->capacity != $data['capacity']) {
                $jadwalMendatang = Schedule::where('vehicle_id', $item->id)->where('departs_at', '>', now())->whereNotIn('status', ['dibatalkan'])->exists();
                $kursiDipakai = BookingPassenger::whereIn('vehicle_seat_id', $item->seats()->pluck('id'))->exists();
                if ($jadwalMendatang || $kursiDipakai) {
                    throw ValidationException::withMessages(['capacity' => 'Kapasitas tidak dapat diubah selama masih ada jadwal mendatang atau kursi yang pernah dipesan.']);
                }
            }
            unset($data['photo']);
            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('vehicles', 'public');
            }
        }
        $oldCapacity = $item->exists ? $item->capacity : null;
        $item->fill($data)->save();
        if ($model === Vehicle::class && $oldCapacity !== null && $oldCapacity != $item->capacity) {
            $item->seats()->delete();
        }
        if ($model === Vehicle::class && ! $item->seats()->exists()) {
            VehicleSeat::create(['vehicle_id' => $item->id, 'number' => 'SOPIR', 'row' => 0, 'column' => 0, 'selectable' => false]);
            for ($i = 1; $i <= $item->capacity; $i++) {
                VehicleSeat::create(['vehicle_id' => $item->id, 'number' => (string) $i, 'row' => (int) ceil($i / 3), 'column' => (($i - 1) % 3) + 1, 'selectable' => true]);
            }
        }
        AuditLog::create(['user_id' => auth()->id(), 'action' => $item->wasRecentlyCreated ? 'create' : 'update', 'subject_type' => $model, 'subject_id' => $item->id]);

        return $item;
    }

    public function seatForm(int $id)
    {
        $vehicle = Vehicle::with('seats')->findOrFail($id);

        return view('admin.seats', compact('vehicle'));
    }

    public function saveSeats(Request $request, int $id)
    {
        $vehicle = Vehicle::with('seats')->findOrFail($id);
        if (Schedule::where('vehicle_id', $vehicle->id)->exists()) {
            throw ValidationException::withMessages(['seats' => 'Layout kursi tidak dapat diubah setelah kendaraan memiliki jadwal.']);
        }
        $data = $request->validate(['seats' => 'required|array', 'seats.*.number' => 'required|string|max:12', 'seats.*.row' => 'required|integer|min:0|max:9', 'seats.*.column' => 'required|integer|min:0|max:5', 'seats.*.selectable' => 'required|boolean']);
        $seats = $data['seats'];
        $idKursi = array_map('intval', array_keys($seats));
        if (count($seats) !== $vehicle->seats->count() || array_diff($idKursi, $vehicle->seats->pluck('id')->all())) {
            throw ValidationException::withMessages(['seats' => 'Data kursi tidak sesuai dengan kendaraan ini. Muat ulang halaman lalu coba lagi.']);
        }
        if (count(array_unique(array_column($seats, 'number'))) !== count($seats) || count(array_unique(array_map(fn ($s) => $s['row'].'-'.$s['column'], $seats))) !== count($seats)) {
            throw ValidationException::withMessages(['seats' => 'Nomor dan posisi kursi harus unik.']);
        }
        if (collect($seats)->where('selectable', 1)->count() !== $vehicle->capacity) {
            throw ValidationException::withMessages(['seats' => 'Jumlah kursi penumpang harus sama dengan kapasitas kendaraan.']);
        }
        DB::transaction(function () use ($vehicle, $seats) {
            // Fase 1: pindahkan semua kursi ke posisi sementara agar tidak bentrok dengan unique index (vehicle_id, row, column).
            foreach (array_keys($seats) as $index => $seatId) {
                $vehicle->seats->firstWhere('id', (int) $seatId)->update(['number' => 'TMP-'.$seatId, 'row' => 99, 'column' => $index]);
            }
            // Fase 2: tulis posisi final.
            foreach ($seats as $seatId => $seat) {
                $vehicle->seats->firstWhere('id', (int) $seatId)->update($seat);
            }
        });

        return back()->with('status', 'Layout kursi disimpan.');
    }

    public function destroy(string $type, int $id)
    {
        $model = $this->model($type);
        $item = $model::findOrFail($id);
        try {
            $item->delete();
        } catch (QueryException $e) {
            return back()->withErrors(['delete' => 'Data masih digunakan. Nonaktifkan statusnya.']);
        }

        return back()->with('status', 'Data dihapus.');
    }
}
