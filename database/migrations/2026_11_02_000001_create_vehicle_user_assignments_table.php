<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vehicle_id', 'user_id']);
            $table->index(['user_id', 'vehicle_id']);
        });

        DB::table('vehicles')->whereNotNull('owner_user_id')->orderBy('id')->each(function ($vehicle) {
            DB::table('vehicle_user_assignments')->insertOrIgnore([
                'vehicle_id' => $vehicle->id, 'user_id' => $vehicle->owner_user_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_user_assignments');
    }
};
