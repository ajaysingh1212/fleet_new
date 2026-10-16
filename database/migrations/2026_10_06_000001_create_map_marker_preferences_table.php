<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_marker_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tracked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32)->default('person');
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->unique(['viewer_user_id', 'tracked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_marker_preferences');
    }
};
