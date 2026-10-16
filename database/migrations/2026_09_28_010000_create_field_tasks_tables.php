<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tracking_relation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('schedule_type', 20)->default('once')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->date('repeat_until')->nullable();
            $table->json('repeat_days')->nullable();
            $table->string('status', 20)->default('assigned')->index();
            $table->unsignedSmallInteger('arrival_radius_meters')->default(100);
            $table->string('customer_name', 160)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('reference_code', 80)->nullable();
            $table->text('instructions')->nullable();
            $table->json('tags')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['creator_id', 'starts_at']);
            $table->index(['assignee_id', 'starts_at']);
        });

        Schema::create('field_task_stops', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('field_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('expected_at')->nullable();
            $table->unsignedSmallInteger('radius_meters')->default(100);
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('arrived_at')->nullable()->index();
            $table->foreignId('arrival_location_id')->nullable()->constrained('gps_locations')->nullOnDelete();
            $table->unsignedInteger('arrival_distance_meters')->nullable();
            $table->timestamps();

            $table->unique(['field_task_id', 'sequence']);
        });

        Schema::create('field_task_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_task_stop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_task_activity');
        Schema::dropIfExists('field_task_stops');
        Schema::dropIfExists('field_tasks');
    }
};
