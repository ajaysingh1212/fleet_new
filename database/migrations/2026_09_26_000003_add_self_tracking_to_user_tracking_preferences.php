<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tracking_preferences', function (Blueprint $table): void {
            $table->boolean('self_tracking_enabled')->default(false)->after('tracking_source');
        });
    }

    public function down(): void
    {
        Schema::table('user_tracking_preferences', function (Blueprint $table): void {
            $table->dropColumn('self_tracking_enabled');
        });
    }
};
