<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('call_participants', function (Blueprint $table) {
            $table->boolean('is_on_hold')->default(false)->after('is_video_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_participants', function (Blueprint $table) {
            $table->dropColumn('is_on_hold');
        });
    }
};
