<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geofence_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date')->index();
            $table->dateTime('first_check_in_at')->nullable();
            $table->dateTime('last_check_out_at')->nullable();
            $table->unsignedInteger('working_seconds')->default(0);
            $table->unsignedInteger('travel_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->string('status', 20)->default('absent')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });

        Schema::create('movement_statistics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date')->index();
            $table->unsignedBigInteger('total_distance_meters')->default(0);
            $table->decimal('average_speed', 8, 2)->nullable();
            $table->decimal('maximum_speed', 8, 2)->nullable();
            $table->unsignedInteger('moving_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedInteger('stopped_seconds')->default(0);
            $table->decimal('route_efficiency', 5, 2)->nullable();
            $table->decimal('average_accuracy', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stat_date']);
        });

        Schema::create('route_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->unsignedBigInteger('distance_meters')->default(0);
            $table->unsignedInteger('point_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });

        Schema::create('route_replay_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('range_start')->index();
            $table->dateTime('range_end')->index();
            $table->string('status', 20)->default('ready')->index();
            $table->unsignedInteger('point_count')->default(0);
            $table->unsignedBigInteger('distance_meters')->default(0);
            $table->json('statistics')->nullable();
            $table->timestamps();
        });

        Schema::create('heatmaps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 20)->default('daily')->index();
            $table->date('range_start')->index();
            $table->date('range_end')->index();
            $table->json('points');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->string('scope', 40)->index();
            $table->json('payload');
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('employee_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->json('filters')->nullable();
            $table->json('payload')->nullable();
            $table->string('export_format', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_summary', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('distance_meters')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('max_speed', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });

        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event_type', 40)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('visit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geofence_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('entered_at')->index();
            $table->dateTime('exited_at')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('status', 20)->default('completed')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_logs');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('trip_summary');
        Schema::dropIfExists('employee_reports');
        Schema::dropIfExists('analytics_cache');
        Schema::dropIfExists('heatmaps');
        Schema::dropIfExists('route_replay_sessions');
        Schema::dropIfExists('route_history');
        Schema::dropIfExists('movement_statistics');
        Schema::dropIfExists('attendance_logs');
    }
};
