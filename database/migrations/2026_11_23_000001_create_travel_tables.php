<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('pelanggan')->index();
            $t->string('phone', 30)->nullable();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('model');
            $t->string('name');
            $t->string('plate')->unique();
            $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedTinyInteger('capacity');
            $t->string('photo')->nullable();
            $t->text('facilities')->nullable();
            $t->string('status')->default('aktif');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('vehicle_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('number', 12);
            $t->unsignedTinyInteger('row');
            $t->unsignedTinyInteger('column');
            $t->boolean('selectable')->default(true);
            $t->unique(['vehicle_id', 'number']);
            $t->unique(['vehicle_id', 'row', 'column']);
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('license_number')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('routes', function (Blueprint $t) {
            $t->id();
            $t->string('origin');
            $t->string('destination');
            $t->unsignedSmallInteger('duration_minutes');
            $t->unsignedBigInteger('base_fare');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['origin', 'destination']);
        });
        Schema::create('schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('route_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('departs_at')->index();
            $t->dateTime('arrives_at');
            $t->unsignedBigInteger('fare');
            $t->string('status')->default('terjadwal');
            $t->timestamps();
            $t->index(['vehicle_id', 'departs_at']);
            $t->index(['driver_id', 'departs_at']);
        });
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('menunggu_pembayaran');
            $t->string('payment_status')->default('pending');
            $t->string('pickup_address');
            $t->string('dropoff_address');
            $t->string('phone', 30);
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('passenger_count');
            $t->unsignedBigInteger('fare_snapshot');
            $t->unsignedBigInteger('pickup_fee')->default(0);
            $t->unsignedBigInteger('dropoff_fee')->default(0);
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('total');
            $t->dateTime('expires_at')->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('booking_passengers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_seat_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('identity_number')->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->unique(['booking_id', 'vehicle_seat_id']);
        });
        Schema::create('booking_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_seat_id')->constrained()->restrictOnDelete();
            $t->unique(['schedule_id', 'vehicle_seat_id']);
            $t->unique(['booking_id', 'vehicle_seat_id']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('method');
            $t->string('status')->default('pending');
            $t->unsignedBigInteger('amount');
            $t->string('gateway_order_id')->nullable()->unique();
            $t->string('gateway_transaction_id')->nullable();
            $t->string('redirect_url')->nullable();
            $t->string('proof_path')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->string('event_key')->unique();
            $t->string('status');
            $t->json('payload');
            $t->timestamps();
        });
        Schema::create('invoice_counters', function (Blueprint $t) {
            $t->date('day')->primary();
            $t->unsignedInteger('sequence');
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->json('snapshot');
            $t->string('status')->default('lunas');
            $t->timestamp('issued_at');
            $t->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->unsignedSmallInteger('quantity');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('amount');
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('bank');
            $t->string('number');
            $t->string('holder');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'bank_accounts', 'settings', 'invoice_items', 'invoices', 'invoice_counters', 'payment_events', 'payments', 'booking_seats', 'booking_passengers', 'bookings', 'schedules', 'routes', 'drivers', 'vehicle_seats', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone']));
    }
};
