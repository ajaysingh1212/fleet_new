<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_geofence_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('geofence_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('vehicle_geofence_states', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->unique()->constrained('vehicle_geofence_rules')->cascadeOnDelete();
            $t->dateTime('recorded_at')->nullable();
            $t->boolean('outside')->default(false);
            $t->string('speed_state')->nullable();
        });
        Schema::create('vehicle_geofence_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('vehicle_geofence_rules')->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('gps_device_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30);
            $t->dateTime('started_at')->index();
            $t->dateTime('ended_at')->nullable()->index();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->float('speed')->nullable();
            $t->float('limit')->nullable();
            $t->index(['gps_device_id', 'ended_at']);
            $t->index(['vehicle_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_geofence_alerts');
        Schema::dropIfExists('vehicle_geofence_states');
        Schema::dropIfExists('vehicle_geofence_rules');
    }
};
