<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('registration_number')->unique();
            $t->string('name');
            $t->string('status')->default('active');
            $t->json('details')->nullable();
            $t->timestamps();
        });
        Schema::create('gps_devices', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('identifier', 32)->unique();
            $t->string('imei', 15)->nullable()->unique();
            $t->string('manufacturer')->nullable();
            $t->string('model')->nullable();
            $t->string('protocol', 40);
            $t->string('protocol_version', 20)->nullable();
            $t->string('status', 20)->default('inactive')->index();
            // These unique current pointers serialize assignments in MySQL and SQLite.
            $t->foreignId('vehicle_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->json('details')->nullable();
            $t->json('configuration')->nullable();
            $t->timestamp('configuration_updated_at')->nullable();
            $t->string('configuration_status')->default('recorded_only');
            $t->timestamp('last_connected_at')->nullable();
            $t->timestamp('last_packet_at')->nullable()->index();
            $t->string('remote_ip', 45)->nullable();
            $t->timestamps();
        });
        Schema::create('gps_device_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gps_device_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->timestamp('installed_at');
            $t->timestamp('removed_at')->nullable();
            $t->index(['gps_device_id', 'installed_at']);
        });
        Schema::create('gps_connection_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('gps_device_id')->constrained()->cascadeOnDelete();
            $t->string('protocol');
            $t->string('remote_ip', 45);
            $t->dateTime('connected_at');
            $t->dateTime('last_packet_at')->index();
            $t->timestamp('disconnected_at')->nullable();
            $t->boolean('authenticated')->default(true);
        });
        Schema::create('gps_packet_diagnostics', function (Blueprint $t) {
            $t->id();
            $t->string('reason', 40)->index();
            $t->string('protocol', 40)->nullable();
            $t->string('identifier', 32)->nullable();
            $t->string('remote_ip', 45);
            $t->char('packet_hash', 64);
            $t->unsignedInteger('byte_count');
            // Never retain arbitrary payloads, credentials or bearer tokens.
            $t->timestamp('received_at')->index();
        });
        Schema::create('gps_device_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gps_device_id')->constrained()->cascadeOnDelete();
            $t->char('packet_hash', 64);
            $t->string('type', 30);
            $t->json('telemetry');
            $t->timestamp('received_at')->index();
            $t->unique(['gps_device_id', 'packet_hash']);
        });
        // Deliberately separate from user/mobile gps_locations: those require a
        // licensed user, device_session and SourceType. Hardware has neither.
        Schema::create('gps_device_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gps_device_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->char('packet_hash', 64);
            $t->unsignedSmallInteger('record_index');
            $t->string('protocol', 40);
            $t->string('protocol_version', 20);
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->float('speed')->nullable();
            $t->float('heading')->nullable();
            $t->float('altitude')->nullable();
            $t->unsignedTinyInteger('satellites')->nullable();
            $t->boolean('gps_fix');
            $t->json('telemetry')->nullable();
            $t->dateTime('recorded_at');
            $t->dateTime('received_at')->index();
            $t->unique(['gps_device_id', 'packet_hash', 'record_index'], 'gps_hardware_dedupe');
            $t->index(['gps_device_id', 'recorded_at']);
            $t->index(['vehicle_id', 'recorded_at']);
        });
        Schema::table('gps_devices', function (Blueprint $t) {
            $t->foreignId('latest_location_id')->nullable()->constrained('gps_device_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gps_devices', fn (Blueprint $t) => $t->dropConstrainedForeignId('latest_location_id'));
        foreach (['gps_device_locations', 'gps_device_events', 'gps_packet_diagnostics', 'gps_connection_sessions', 'gps_device_assignments', 'gps_devices', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
