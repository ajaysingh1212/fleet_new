<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_speed_kmh')->nullable()->after('radius_meters');
            $table->unsignedSmallInteger('max_speed_kmh')->nullable()->after('min_speed_kmh');
        });
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->dropColumn(['min_speed_kmh', 'max_speed_kmh']);
        });
    }
};
