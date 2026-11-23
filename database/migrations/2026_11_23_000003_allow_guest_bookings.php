<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_key_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Cannot roll back guest booking support while guest bookings exist.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('guest_key_hash');
            $table->unsignedBigInteger('user_id')->change();
        });
    }
};
