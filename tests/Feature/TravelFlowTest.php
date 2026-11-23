<?php

namespace Tests\Feature;

use App\Models\Booking;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_guest_can_book_and_recover_private_invoice_with_whatsapp_number(): void
    {
        [$schedule, $seat] = $this->journey();
        $this->get(route('schedules.show', $schedule))->assertOk();
        $response = $this->post(route('bookings.store'), $this->data($schedule, [$seat]));
        $booking = Booking::sole();

        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertGuest();
        $this->assertNull($booking->user_id);
        $this->assertArrayNotHasKey('guest_key_hash', $booking->toArray());
        $this->assertSame('08123456789', $booking->phone);
        $this->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('08123456789')->assertSee('Cek Booking')->assertDontSee('kunci akses');
        $this->get(route('tickets.pdf', $booking))->assertNotFound();

        $this->post(route('payments.choose', $booking), ['method' => 'transfer'])
            ->assertRedirect(route('bookings.show', $booking));
        $payment = $booking->payments()->sole();
        Storage::fake('local');
        $this->from(route('bookings.show', $booking))->post(route('payments.proof', $payment), [
            'proof' => UploadedFile::fake()->create('bukti.pdf', 5, 'application/pdf'),
        ])->assertRedirect(route('bookings.show', $booking))->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($payment->fresh()->proof_path);
        $invoice = app(PaymentService::class)->approve($payment);
        $this->assertSame('Penumpang 1', $invoice->snapshot['customer']);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Penumpang 1');
        $pdf = $this->get(route('invoices.pdf', $invoice))->assertOk()->assertDownload('faktur-'.$invoice->number.'.pdf');
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));

        $this->app['session']->forget('guest_bookings');
        $this->get(route('bookings.show', $booking))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertForbidden();
        $this->get(route('invoices.pdf', $invoice))->assertForbidden();
        $this->get(route('tickets.pdf', $booking))->assertForbidden();
        $this->post(route('payments.proof', $payment))->assertForbidden();
        $this->post(route('bookings.cancel', $booking))->assertForbidden();
        $this->get(route('verify.invoice', $invoice->number))->assertOk()
            ->assertDontSee('Penumpang 1')->assertDontSee('08123456789');

        $this->from(route('booking.check'))->post(route('booking.recover'), [
            'code' => $booking->code, 'phone' => '081200000000',
        ])->assertRedirect(route('booking.check'))->assertSessionHasErrors('code')
            ->assertSessionMissing('_old_input.phone');
        $this->post(route('booking.recover'), [
            'code' => $booking->code.'X', 'phone' => '08123456789',
        ])->assertRedirect(route('booking.check'))->assertSessionHasErrors('code');
        $this->get(route('invoices.pdf', $invoice))->assertForbidden();

        // Format internasional untuk nomor yang sama tetap membuka pesanan.
        $this->post(route('booking.recover'), ['code' => $booking->code, 'phone' => '+62 812-3456-789'])
            ->assertRedirect(route('bookings.show', $booking));
        $this->get(route('invoices.pdf', $invoice))->assertOk();
        $this->travel(25)->hours();
        $this->get(route('invoices.pdf', $invoice))->assertForbidden();
        $this->travelBack();

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['dashboard', 'bookings', 'payments', 'invoices', 'reports'] as $page) {
            $this->get(route('admin.'.$page))->assertOk()->assertSee('Penumpang 1');
        }
        // Shell admin tidak memuat header/footer situs publik.
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Panel Admin')->assertDontSee('Cara Pemesanan')->assertDontSee('Jelajahi');
        $this->get(route('profile.edit'))->assertOk()
            ->assertSee('Ganti kata sandi')->assertDontSee('Jelajahi');
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
        // Detail pesanan mengikuti shell pengguna: admin tanpa footer publik, pelanggan dengan footer.
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Panel Admin')->assertDontSee('Jelajahi');
        $this->actingAs($customer)->get(route('bookings.show', $booking))->assertOk()->assertSee('Jelajahi');
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
        $this->get(route('travel.search', ['model' => 'Toyota Avanza']))->assertOk()->assertDontSee('Panther Test');
        $this->get(route('travel.search', ['passengers' => 3]))->assertOk()->assertDontSee('Panther Test');
        $this->get(route('schedules.show', $schedule))->assertOk()->assertSee('Pilih Kursi');
    }

    public function test_report_total_covers_every_filtered_row_not_only_the_page(): void
    {
        [$schedule] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create();
        foreach (range(1, 31) as $index) {
            Booking::create([
                'code' => 'TRV-REPORT-'.$index, 'user_id' => $customer->id, 'schedule_id' => $schedule->id,
                'status' => 'dikonfirmasi', 'payment_status' => 'lunas', 'pickup_address' => 'Jalan Satu',
                'dropoff_address' => 'Jalan Dua', 'phone' => '08123456789', 'passenger_count' => 1,
                'fare_snapshot' => 100000, 'total' => 100000, 'expires_at' => now()->addHour(), 'paid_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()
            ->assertSee('Rp3.100.000')->assertSee('31 transaksi')->assertSee('31 penumpang');
        $this->get(route('admin.reports.csv'))->assertOk();
    }

    public function test_midtrans_webhook_for_unknown_order_is_acknowledged_without_side_effects(): void
    {
        config(['services.midtrans.server_key' => 'sandbox-secret']);
        $payload = ['order_id' => 'ORDER-TIDAK-DIKENAL', 'status_code' => '200', 'gross_amount' => '100000.00', 'transaction_id' => 'TX-HANTU', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'sandbox-secret');

        $this->postJson(route('payments.webhook'), $payload)->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_settlement_on_second_channel_is_flagged_for_refund_instead_of_double_paying(): void
    {
        [$schedule, $seat] = $this->journey();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat]));
        app(PaymentService::class)->approve(app(PaymentService::class)->manual($booking));
        $booking->update(['status' => 'selesai']);

        $gateway = Payment::create(['booking_id' => $booking->id, 'method' => 'midtrans', 'status' => 'pending', 'amount' => $booking->total, 'gateway_order_id' => 'ORDER-KEDUA']);
        config(['services.midtrans.server_key' => 'sandbox-secret']);
        $payload = ['order_id' => 'ORDER-KEDUA', 'status_code' => '200', 'gross_amount' => (string) $booking->total.'.00', 'transaction_id' => 'TX-2', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'sandbox-secret');

        $this->postJson(route('payments.webhook'), $payload)->assertOk();
        $this->assertSame('refund', $gateway->fresh()->status);
        $this->assertSame('lunas', $booking->fresh()->payment_status);
        $this->assertSame('selesai', $booking->fresh()->status);
        $this->assertSame(1, Invoice::where('booking_id', $booking->id)->count());

        $second = Payment::create(['booking_id' => $booking->id, 'method' => 'transfer', 'status' => 'pending', 'amount' => $booking->total, 'proof_path' => 'payment-proofs/lain.pdf']);
        $this->actingAs($admin)->post(route('admin.payments.approve', $second))->assertSessionHasErrors('payment');
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_admin_can_change_own_password_from_profile_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'ganti@example.test', 'password' => 'password-lama-123']);
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertSee('Ganti kata sandi');

        $this->put(route('profile.password'), [
            'current_password' => 'salah-sekali-123',
            'password' => 'kata-sandi-baru-123',
            'password_confirmation' => 'kata-sandi-baru-123',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password-lama-123', $admin->fresh()->password));

        $this->put(route('profile.password'), [
            'current_password' => 'password-lama-123',
            'password' => 'kata-sandi-baru-123',
            'password_confirmation' => 'kata-sandi-baru-123',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('kata-sandi-baru-123', $admin->fresh()->password));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->put(route('profile.password'), [
            'current_password' => 'kata-sandi-baru-123',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->post('/logout');
        $this->post('/login', ['email' => 'ganti@example.test', 'password' => 'password-lama-123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'ganti@example.test', 'password' => 'kata-sandi-baru-123'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_super_admin_command_creates_and_resets_account(): void
    {
        $this->artisan('app:super-admin', ['email' => 'baru@nusa.local', '--password' => 'rahasia-panjang-123'])->assertExitCode(0);
        $user = User::where('email', 'baru@nusa.local')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check('rahasia-panjang-123', $user->password));

        $this->artisan('app:super-admin', ['email' => 'baru@nusa.local', '--password' => 'rahasia-baru-456'])->assertExitCode(0);
        $this->assertSame(1, User::where('email', 'baru@nusa.local')->count());
        $this->assertTrue(Hash::check('rahasia-baru-456', $user->fresh()->password));

        $this->artisan('app:super-admin', ['email' => 'bukan-email', '--password' => 'pendek'])->assertExitCode(1);
        $this->assertSame(1, User::count());
    }

    public function test_admin_status_form_only_allows_valid_transitions_and_never_returns_422(): void
    {
        [$schedule, $seat1] = $this->journey();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat1]));

        // Halaman detail menampilkan breadcrumb panel admin, bukan "Pesanan Saya".
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee(route('admin.bookings'), false)
            ->assertSee('Pesanan belum lunas')
            ->assertDontSee('Konfirmasi pesanan');
        $this->actingAs($admin)->get(route('customer.dashboard'))->assertOk();

        // Pesanan belum lunas tidak boleh dikonfirmasi: galat validasi yang terbaca, bukan halaman 422.
        $this->actingAs($admin)->from(route('bookings.show', $booking))
            ->post(route('admin.bookings.status', $booking), ['status' => 'dikonfirmasi'])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('status');
        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status);

        // Setelah lunas, alurnya berjalan: konfirmasi lalu selesai.
        $payment = app(PaymentService::class)->manual($booking);
        $payment->update(['proof_path' => 'payment-proofs/bukti.pdf']);
        app(PaymentService::class)->approve($payment);
        $this->assertSame('dikonfirmasi', $booking->fresh()->status);

        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Tandai selesai')
            ->assertDontSee('Konfirmasi pesanan');

        $this->actingAs($admin)->post(route('admin.bookings.status', $booking), ['status' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $booking->fresh()->status);

        // Tidak bisa dikembalikan ke dikonfirmasi dan tidak bisa dibatalkan (pesanan lunas).
        $this->actingAs($admin)->post(route('admin.bookings.status', $booking), ['status' => 'dikonfirmasi'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->post(route('admin.bookings.status', $booking), ['status' => 'dibatalkan'])->assertSessionHasErrors('booking');
        $this->assertSame('selesai', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_unpaid_booking_from_admin_booking_page(): void
    {
        [$schedule, $seat1] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve(User::factory()->create()->id, $this->data($schedule, [$seat1]));

        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Batalkan pesanan')
            ->assertDontSee('Tandai selesai');

        $this->actingAs($admin)->post(route('admin.bookings.status', $booking), ['status' => 'dibatalkan'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $booking->fresh()->status);
        $this->assertDatabaseMissing('booking_seats', ['booking_id' => $booking->id]);
    }

    public function test_checkin_requires_paid_booking_and_own_passengers(): void
    {
        [$schedule, $seat1, $seat2] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve(User::factory()->create()->id, $this->data($schedule, [$seat1]));

        $this->actingAs($admin)->post(route('admin.checkin', $booking), ['passenger_ids' => [$booking->passengers()->first()->id]])
            ->assertSessionHasErrors('passenger_ids');

        $payment = app(PaymentService::class)->manual($booking);
        $payment->update(['proof_path' => 'payment-proofs/bukti.pdf']);
        app(PaymentService::class)->approve($payment);

        // Tanpa memilih penumpang, admin mendapat pesan yang jelas.
        $this->actingAs($admin)->post(route('admin.checkin', $booking))->assertSessionHasErrors('passenger_ids');
        $this->assertNull($booking->passengers()->first()->checked_in_at);

        // Penumpang milik pesanan lain tidak dapat di-check-in lewat pesanan ini.
        $lain = app(BookingService::class)->reserve(User::factory()->create()->id, $this->data($schedule, [$seat2]));
        $this->actingAs($admin)->post(route('admin.checkin', $booking), ['passenger_ids' => [$lain->passengers()->first()->id]])
            ->assertSessionHasErrors('passenger_ids.0');

        $this->actingAs($admin)->post(route('admin.checkin', $booking), ['passenger_ids' => [$booking->passengers()->first()->id]])->assertSessionHasNoErrors();
        $this->assertNotNull($booking->passengers()->first()->checked_in_at);
    }

    public function test_payment_approval_without_proof_redirects_with_message_instead_of_422(): void
    {
        [$schedule, $seat1] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve(User::factory()->create()->id, $this->data($schedule, [$seat1]));
        $payment = app(PaymentService::class)->manual($booking);

        $this->actingAs($admin)->from(route('admin.payments'))
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect(route('admin.payments'))
            ->assertSessionHasErrors('payment');
        $this->assertSame('pending', $payment->fresh()->status);

        // Halaman admin menampilkan peringatan dan tombol setujui nonaktif.
        $this->actingAs($admin)->get(route('admin.payments'))->assertOk()
            ->assertSee('Bukti transfer belum diunggah pelanggan.')
            ->assertSee('disabled', false);
    }

    public function test_admin_lists_search_and_expire_stale_bookings(): void
    {
        [$schedule, $seat1] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create(['name' => 'Rani Pencari', 'email' => 'rani@example.test']);
        $booking = app(BookingService::class)->reserve($customer->id, $this->data($schedule, [$seat1]));
        $booking->update(['expires_at' => now()->subMinutes(5)]);

        // Membuka daftar admin memaksa pesanan yang lewat batas bayar menjadi kedaluwarsa.
        $this->actingAs($admin)->get(route('admin.bookings'))->assertOk()->assertSee($booking->code);
        $this->assertSame('kedaluwarsa', $booking->fresh()->status);

        $this->actingAs($admin)->get(route('admin.customers', ['q' => 'rani']))->assertOk()
            ->assertSee('Rani Pencari')
            ->assertSee('rani@example.test')
            ->assertSee('1 pesanan');

        $this->actingAs($admin)->get(route('admin.invoices', ['number' => 'tidak-ada']))->assertOk()->assertSee('Belum ada faktur.');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_report_date_filter_is_guarded_and_csv_includes_payment_method(): void
    {
        [$schedule, $seat1] = $this->journey();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $booking = app(BookingService::class)->reserve(User::factory()->create()->id, $this->data($schedule, [$seat1]));
        $payment = app(PaymentService::class)->manual($booking);
        $payment->update(['proof_path' => 'payment-proofs/bukti.pdf']);
        app(PaymentService::class)->approve($payment);

        // Tanggal terbalik dijawab dengan redirect + pesan, bukan redirect berulang ke URL filter yang sama.
        $this->actingAs($admin)->get(route('admin.reports', ['from' => '2026-12-31', 'to' => '2026-01-01']))
            ->assertRedirect(route('admin.reports', ['from' => '2026-12-31']))
            ->assertSessionHasErrors('to');

        $this->actingAs($admin)->get(route('admin.reports', ['to' => '2026-12-31']))->assertOk();

        $csv = $this->actingAs($admin)->get(route('admin.reports.csv'))->streamedContent();
        $this->assertStringContainsString('Metode', $csv);
        $this->assertStringContainsString('transfer', $csv);
        $this->assertStringContainsString($booking->code, $csv);
    }

    public function test_validation_and_auth_messages_are_translated_to_indonesian(): void
    {
        $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors([
            'email' => 'email wajib diisi.',
            'password' => 'kata sandi wajib diisi.',
        ]);

        $this->post('/login', ['email' => 'tidak-ada@example.test', 'password' => 'salah-sekali'])->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah.',
        ]);
    }
}
