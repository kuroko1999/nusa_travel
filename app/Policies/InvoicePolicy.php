<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function __construct(private BookingPolicy $bookings) {}

    public function view(?User $user, Invoice $invoice): bool
    {
        return $this->bookings->view($user, $invoice->booking);
    }
}
