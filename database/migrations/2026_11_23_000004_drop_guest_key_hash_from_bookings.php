<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akses pesanan tamu kini diverifikasi dengan nomor WhatsApp, sehingga
     * kunci akses 64 karakter tidak lagi disimpan.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('guest_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_key_hash', 64)->nullable();
        });
    }
};
