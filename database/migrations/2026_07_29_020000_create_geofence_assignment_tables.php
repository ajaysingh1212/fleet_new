<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route_label')->nullable();
            $table->unsignedSmallInteger('sequence')->nullable();
            $table->string('schedule_type', 20)->default('daily');
            $table->json('schedule_days')->nullable();
            $table->date('schedule_date')->nullable();
            $table->time('window_start')->nullable();
            $table->time('window_end')->nullable();
            $table->boolean('alert_on_exit')->default(true);
            $table->boolean('alert_on_missed')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['route_label', 'sequence']);
        });

        Schema::create('geofence_assignment_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geofence_assignment_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('visited_at')->nullable();
            $table->foreignId('geofence_event_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['geofence_assignment_id', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_assignment_runs');
        Schema::dropIfExists('geofence_assignments');
    }
};
