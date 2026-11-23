<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-().]{6,}$/'],
            'pickup_address' => ['required', 'string', 'max:255'],
            'dropoff_address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'passengers' => ['required', 'array', 'min:1', 'max:12'],
            'passengers.*.name' => ['required', 'string', 'max:100'],
            'passengers.*.identity_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.seat_id' => ['required', 'integer', 'exists:vehicle_seats,id'],
        ];
    }
}
