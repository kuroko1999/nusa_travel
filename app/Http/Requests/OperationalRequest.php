<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    public function rules(): array
    {
        $type = $this->route('type');
        $id = $this->route('id');

        return match ($type) {
            'vehicles' => ['model' => ['required', Rule::in(['Isuzu Panther', 'Toyota Avanza'])], 'name' => 'required|string|max:100', 'plate' => ['required', 'string', 'max:30', Rule::unique('vehicles', 'plate')->ignore($id)], 'year' => 'nullable|integer|min:1990|max:2100', 'capacity' => 'required|integer|min:1|max:12', 'facilities' => 'nullable|string|max:500', 'status' => 'required|in:aktif,maintenance,tidak_aktif', 'notes' => 'nullable|string|max:1000', 'photo' => 'nullable|image|max:4096'],
            'drivers' => ['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'license_number' => 'nullable|string|max:50', 'active' => 'required|boolean'],
            'routes' => ['origin' => 'required|string|max:100', 'destination' => 'required|string|max:100|different:origin', 'duration_minutes' => 'required|integer|min:15|max:2880', 'base_fare' => 'required|integer|min:0', 'active' => 'required|boolean'],
            'schedules' => ['route_id' => 'required|exists:routes,id', 'vehicle_id' => 'required|exists:vehicles,id', 'driver_id' => 'nullable|exists:drivers,id', 'departs_at' => 'required|date', 'fare' => 'required|integer|min:0', 'status' => 'required|in:terjadwal,penuh,berangkat,selesai,dibatalkan'],
            'bank-accounts' => ['bank' => 'required|string|max:100', 'number' => 'required|string|max:50', 'holder' => 'required|string|max:100', 'active' => 'required|boolean'],
            default => [],
        };
    }
}
