<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_device_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('manufacturer')->nullable();
            $table->string('protocol', 40);
            $table->string('protocol_version', 20)->nullable();
            $table->string('network_generation', 10)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('gps_devices', fn (Blueprint $table) => $table->foreignId('gps_device_model_id')->nullable()->constrained('gps_device_models')->restrictOnDelete());
        Schema::table('vehicles', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('gps_devices', fn (Blueprint $table) => $table->dropConstrainedForeignId('gps_device_model_id'));
        Schema::dropIfExists('gps_device_models');
    }
};
