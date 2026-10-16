<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_session_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20)->index();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedBigInteger('total_distance_meters')->default(0);
            $table->decimal('average_speed', 8, 2)->nullable();
            $table->decimal('max_speed', 8, 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('gps_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_type', 20)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('bearing', 6, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('network_type', 20)->nullable();
            $table->unsignedTinyInteger('signal_strength')->nullable();
            $table->string('provider', 30)->nullable();
            $table->boolean('is_mock')->default(false);
            $table->dateTime('recorded_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'recorded_at']);
            $table->index(['device_session_id', 'recorded_at']);
        });

        Schema::create('device_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_gps_enabled')->nullable();
            $table->boolean('is_internet_enabled')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('network_type', 20)->nullable();
            $table->string('tracking_mode', 20)->default('automatic');
            $table->foreignId('last_location_id')->nullable()->constrained('gps_locations')->nullOnDelete();
            $table->dateTime('last_ping_at')->nullable();
            $table->timestamps();
        });

        Schema::create('movement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('bearing', 6, 2)->nullable();
            $table->dateTime('occurred_at');

            $table->index(['tracking_session_id', 'occurred_at']);
        });

        Schema::create('diagnostic_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('network_type', 20)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('offline_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('batch_size');
            $table->dateTime('oldest_recorded_at');
            $table->dateTime('newest_recorded_at');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->dateTime('synced_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_tracking_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('distance_filter_meters')->default(25);
            $table->string('tracking_source', 20)->default('automatic');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tracking_preferences');
        Schema::dropIfExists('offline_sync_logs');
        Schema::dropIfExists('diagnostic_logs');
        Schema::dropIfExists('movement_events');
        Schema::dropIfExists('device_status');
        Schema::dropIfExists('gps_locations');
        Schema::dropIfExists('tracking_sessions');
    }
};
