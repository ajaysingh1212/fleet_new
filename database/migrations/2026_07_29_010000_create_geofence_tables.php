<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->index();
            $table->string('category', 30)->default('custom')->index();
            $table->string('color', 7)->default('#38bdf8');
            $table->string('status', 20)->default('active')->index();
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('geofence_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->index(['geofence_id', 'sequence']);
        });

        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gps_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('occurred_at')->index();

            $table->index(['geofence_id', 'user_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
        Schema::dropIfExists('geofence_points');
        Schema::dropIfExists('geofences');
    }
};
