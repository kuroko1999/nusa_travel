<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;

class BookingPolicy
{
    public function __construct(private Request $request) {}

    public function view(?User $user, Booking $booking): bool
    {
        return $user?->role === 'super_admin' || $this->pay($user, $booking);
    }

    public function pay(?User $user, Booking $booking): bool
    {
        return ($user !== null && $booking->user_id === $user->id)
            || $booking->hasGuestAccess($this->request);
    }

    public function cancel(?User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
