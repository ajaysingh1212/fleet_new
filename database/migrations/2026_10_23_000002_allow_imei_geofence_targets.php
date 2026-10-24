<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_geofence_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable()->change();
            $table->foreignId('gps_device_id')->nullable()->constrained()->cascadeOnDelete();
        });
        Schema::table('vehicle_geofence_alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // IMEI-only rules and their audit history must not be discarded on rollback.
        if (DB::table('vehicle_geofence_rules')->whereNull('vehicle_id')->exists()
            || DB::table('vehicle_geofence_alerts')->whereNull('vehicle_id')->exists()) {
            throw new RuntimeException('IMEI-only geofences exist; retain their history before rolling back.');
        }
        Schema::table('vehicle_geofence_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gps_device_id');
            $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
        });
        Schema::table('vehicle_geofence_alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
        });
    }
};
