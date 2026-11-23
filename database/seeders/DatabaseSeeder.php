<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSeat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (env('SUPER_ADMIN_EMAIL') && env('SUPER_ADMIN_PASSWORD')) {
            User::updateOrCreate(['email' => env('SUPER_ADMIN_EMAIL')], ['name' => 'Super Admin', 'role' => 'super_admin', 'password' => Hash::make(env('SUPER_ADMIN_PASSWORD'))]);
        }
        if (env('DEMO_CUSTOMER_PASSWORD')) {
            foreach (['Pelanggan Contoh Satu' => 'demo1@nusa.local', 'Pelanggan Contoh Dua' => 'demo2@nusa.local'] as $name => $email) {
                User::firstOrCreate(['email' => $email], ['name' => $name, 'phone' => '081200000000', 'role' => 'pelanggan', 'password' => Hash::make(env('DEMO_CUSTOMER_PASSWORD'))]);
            }
        }
        foreach (['company_name' => 'Nusa Travel', 'company_address' => 'Mukomuko, Bengkulu', 'whatsapp' => '', 'hero_title' => 'Perjalanan antarkota terasa lebih mudah', 'pickup_fee' => '0', 'dropoff_fee' => '0'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        $driver = Driver::firstOrCreate(['phone' => '081200000001'], ['name' => 'Sopir Contoh', 'active' => true]);
        foreach ([['Isuzu Panther', 'Panther 01', 'BD 1001 NT', 7], ['Toyota Avanza', 'Avanza 01', 'BD 1002 NT', 6]] as [$model,$name,$plate,$capacity]) {
            $vehicle = Vehicle::firstOrCreate(['plate' => $plate], ['model' => $model, 'name' => $name, 'capacity' => $capacity, 'photo' => 'images/'.($model === 'Isuzu Panther' ? 'panther' : 'avanza').'.jpg', 'status' => 'aktif', 'facilities' => 'AC, bagasi, reclining seat']);
            VehicleSeat::firstOrCreate(['vehicle_id' => $vehicle->id, 'number' => 'SOPIR'], ['row' => 0, 'column' => 0, 'selectable' => false]);
            for ($i = 1; $i <= $capacity; $i++) {
                VehicleSeat::firstOrCreate(['vehicle_id' => $vehicle->id, 'number' => (string) $i], ['row' => (int) ceil($i / 3), 'column' => (($i - 1) % 3) + 1, 'selectable' => true]);
            }
        }
        foreach ([['Mukomuko', 'Bengkulu', 360, 180000], ['Bengkulu', 'Mukomuko', 360, 180000], ['Mukomuko', 'Padang', 420, 220000], ['Padang', 'Mukomuko', 420, 220000]] as [$origin,$destination,$duration,$fare]) {
            TravelRoute::firstOrCreate(compact('origin', 'destination'), ['duration_minutes' => $duration, 'base_fare' => $fare, 'active' => true]);
        }
        $routes = TravelRoute::all();
        $vehicles = Vehicle::all();
        foreach ($vehicles as $index => $vehicle) {
            $route = $routes[$index];
            $start = now()->addDays(2)->setTime(7 + $index * 10, 0);
            Schedule::firstOrCreate(['route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'departs_at' => $start], ['driver_id' => $driver->id, 'arrives_at' => $start->copy()->addMinutes($route->duration_minutes), 'fare' => $route->base_fare, 'status' => 'terjadwal']);
        }
    }
}
