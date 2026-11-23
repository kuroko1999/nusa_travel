<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Schedule;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSeat;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelFlowTest extends TestCase
{
    use RefreshDatabase;

    private function journey(): array
    {
        $vehicle = Vehicle::create(['model' => 'Isuzu Panther', 'name' => 'Panther Test', 'plate' => 'BD 1234 XX', 'capacity' => 2, 'status' => 'aktif']);
        $seat1 = VehicleSeat::create(['vehicle_id' => $vehicle->id, 'number' => '1', 'row' => 1, 'column' => 1, 'selectable' => true]);
        $seat2 = VehicleSeat::create(['vehicle_id' => $vehicle->id, 'number' => '2', 'row' => 1, 'column' => 2, 'selectable' => true]);
        $route = TravelRoute::create(['origin' => 'Mukomuko', 'destination' => 'Bengkulu', 'duration_minutes' => 360, 'base_fare' => 180000, 'active' => true]);
        $start = now()->addDay();
        $schedule = Schedule::create(['route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'departs_at' => $start, 'arrives_at' => $start->copy()->addHours(6), 'fare' => 200000, 'status' => 'terjadwal']);

        return [$schedule, $seat1, $seat2];
    }

    private function data(Schedule $schedule, array $seats): array
    {
        return ['schedule_id' => $schedule->id, 'phone' => '08123456789', 'pickup_address' => 'Jalan Satu', 'dropoff_address' => 'Jalan Dua', 'passengers' => array_map(fn ($seat) => ['name' => 'Penumpang '.$seat->number, 'seat_id' => $seat->id], $seats)];
    }

    public function test_registration_is_always_customer_and_admin_is_protected(): void
    {
        $this->post('/daftar', ['name' => 'Ani', 'email' => 'ani@example.test', 'phone' => '08123', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'super_admin'])->assertRedirect('/akun');
        $this->assertDatabaseHas('users', ['email' => 'ani@example.test', 'role' => 'pelanggan']);
        $this->get('/admin')->assertForbidden();
    }

    public function test_seat_reservation_prevents_double_booking_and_supports_multiple_passengers(): void
    {
        [$schedule,$seat1,$seat2] = $this->journey();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $booking = app(BookingService::class)->reserve($first->id, $this->data($schedule, [$seat1, $seat2]));
        $this->assertSame(2, $booking->passengers()->count());
        $this->assertSame(400000, $booking->total);
        $this->expectException(ValidationException::class);
        app(BookingService::class)->reserve($second->id, $this->data($schedule, [$seat1]));
    }

    public function test_expiry_releases_seats_and_manual_payment_issues_one_invoice(): void
    {
        [$schedule,$seat1] = $this->journey();
        $user = User::factory()->create();
        $old = app(BookingService::class)->reserve($user->id, $this->data($schedule, [$seat1]));
        $old->update(['expires_at' => now()->subMinute()]);
        app(BookingService::class)->expireDue();
        $this->assertDatabaseMissing('booking_seats', ['booking_id' => $old->id]);
        $booking = app(BookingService::class)->reserve($user->id, $this->data($schedule, [$seat1]));
        $this->assertTrue($booking->expires_at->isFuture(), 'expiry='.$booking->expires_at->toIso8601String().' now='.now()->toIso8601String());
        $payment = app(PaymentService::class)->manual($booking);
        $invoice = app(PaymentService::class)->approve($payment);
        $this->assertSame($invoice->id, app(PaymentService::class)->approve($payment)->id);
        $this->assertSame(1, Invoice::where('booking_id', $booking->id)->count());
        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk();
        $this->get(route('tickets.show', $booking))->assertOk();
        $this->get(route('tickets.pdf', $booking))->assertOk();
        $this->get(route('invoices.pdf', $invoice))->assertOk();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    public function test_unverified_midtrans_notification_is_rejected(): void
    {
        $this->postJson(route('payments.webhook'), ['order_id' => 'fake', 'signature_key' => 'fake'])->assertForbidden();
    }

    public function test_admin_pages_and_operational_crud_render(): void
    {
        [$schedule] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        foreach (['vehicles', 'drivers', 'routes', 'schedules', 'bank-accounts'] as $type) {
            $this->get(route('admin.resources.index', $type))->assertOk();
            $this->get(route('admin.resources.create', $type))->assertOk();
        }
        $this->get(route('admin.seats.edit', $schedule->vehicle_id))->assertOk();
        $this->get(route('admin.payments'))->assertOk();
        $this->get(route('admin.invoices'))->assertOk();
        $this->get(route('admin.customers'))->assertOk();
        $this->get(route('admin.settings'))->assertOk();
        $this->get(route('admin.reports'))->assertOk();
        $this->get(route('admin.manifest', $schedule))->assertOk();
        $this->post(route('admin.resources.store', 'drivers'), ['name' => 'Sopir Baru', 'phone' => '08129999', 'active' => 1])->assertRedirect(route('admin.resources.index', 'drivers'));
        $this->assertDatabaseHas('drivers', ['name' => 'Sopir Baru']);
    }

    public function test_valid_midtrans_webhook_is_idempotent(): void
    {
        [$schedule,$seat] = $this->journey();
        $user = User::factory()->create();
        $booking = app(BookingService::class)->reserve($user->id, $this->data($schedule, [$seat]));
        $payment = Payment::create(['booking_id' => $booking->id, 'method' => 'midtrans', 'status' => 'pending', 'amount' => $booking->total, 'gateway_order_id' => 'ORDER-123']);
        config(['services.midtrans.server_key' => 'sandbox-secret']);
        $payload = ['order_id' => 'ORDER-123', 'status_code' => '200', 'gross_amount' => '200000.00', 'transaction_id' => 'TX-1', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'sandbox-secret');
        $this->postJson(route('payments.webhook'), $payload)->assertOk();
        $this->postJson(route('payments.webhook'), $payload)->assertOk();
        $this->assertSame(1, Invoice::where('booking_id', $booking->id)->count());
        $this->assertSame('lunas', $booking->fresh()->payment_status);
        $refund = $payload;
        $refund['transaction_status'] = 'refund';
        $this->postJson(route('payments.webhook'), $refund)->assertOk();
        $this->assertSame('refund', $booking->fresh()->invoice->status);
        $this->actingAs($user)->get(route('invoices.show', $booking->invoice))->assertOk()->assertSee('REFUND');
    }

    public function test_super_admin_login_vehicle_crud_and_schedule_overlap(): void
    {
        [$schedule] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin@example.test', 'password' => 'strong-pass-123']);
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'strong-pass-123'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.resources.store', 'vehicles'), ['model' => 'Toyota Avanza', 'name' => 'Avanza Baru', 'plate' => 'BD 9999 ZZ', 'capacity' => 4, 'status' => 'aktif'])->assertRedirect();
        $vehicle = Vehicle::where('plate', 'BD 9999 ZZ')->firstOrFail();
        $this->assertSame(4, $vehicle->seats()->where('selectable', true)->count());
        $this->put(route('admin.resources.update', ['vehicles', $vehicle->id]), ['model' => 'Toyota Avanza', 'name' => 'Avanza Baru', 'plate' => 'BD 9999 ZZ', 'capacity' => 5, 'status' => 'aktif'])->assertRedirect();
        $this->assertSame(5, $vehicle->fresh()->seats()->where('selectable', true)->count());
        $this->post(route('admin.resources.store', 'schedules'), ['route_id' => $schedule->route_id, 'vehicle_id' => $schedule->vehicle_id, 'departs_at' => $schedule->departs_at->copy()->addHour()->format('Y-m-d H:i'), 'fare' => 200000, 'status' => 'terjadwal'])->assertSessionHasErrors('departs_at');
    }

    public function test_manual_proof_review_and_cancellation(): void
    {
        [$schedule,$seat1,$seat2] = $this->journey();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat1]));
        $this->actingAs($customer)->post(route('payments.choose', $booking), ['method' => 'transfer'])->assertRedirect();
        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $this->post(route('payments.proof', $payment), ['proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();
        $this->assertSame('lunas', $booking->fresh()->payment_status);
        $other = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat2]));
        $this->actingAs($customer)->post(route('bookings.cancel', $other))->assertRedirect();
        $this->assertSame('dibatalkan', $other->fresh()->status);
        $this->assertDatabaseMissing('booking_seats', ['booking_id' => $other->id]);
    }

    public function test_finished_customer_review_requires_admin_approval(): void
    {
        [$schedule,$seat] = $this->journey();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat]));
        $payment = app(PaymentService::class)->manual($booking);
        app(PaymentService::class)->approve($payment);
        $booking->update(['status' => 'selesai']);
        $this->actingAs($customer)->post(route('reviews.store', $booking), ['rating' => 5, 'quote' => 'Perjalanannya nyaman dan tepat waktu.'])->assertRedirect();
        $review = Review::where('booking_id', $booking->id)->firstOrFail();
        $this->get('/')->assertDontSee('Perjalanannya nyaman dan tepat waktu.');
        $this->actingAs($admin)->post(route('admin.reviews.approve', $review))->assertRedirect();
        $this->get('/')->assertSee('Perjalanannya nyaman dan tepat waktu.');
    }

    public function test_search_filters_vehicle_model(): void
    {
        [$schedule] = $this->journey();
        $this->get(route('travel.search', ['model' => 'Isuzu Panther']))->assertOk()->assertSee('Panther Test');
        $this->get(route('travel.search',['model' => 'Toyota Avanza']))->assertOk()->assertDontSee('Panther Test');
        $this->get(route('travel.search',['passengers' => 3]))->assertOk()->assertDontSee('Panther Test');
        $this->get(route('schedules.show',$schedule))->assertOk()->assertSee('Pilih Kursi');
    }
}
