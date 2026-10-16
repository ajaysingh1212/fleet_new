<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->foreignId('tracking_license_id')->nullable()->constrained('user_licenses')->nullOnDelete();
            $table->char('tracking_key_hash', 64)->nullable();
            $table->dateTime('tracking_registered_at')->nullable();
            $table->dateTime('tracking_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracking_license_id');
            $table->dropColumn(['tracking_key_hash', 'tracking_registered_at', 'tracking_revoked_at']);
        });
    }
};
