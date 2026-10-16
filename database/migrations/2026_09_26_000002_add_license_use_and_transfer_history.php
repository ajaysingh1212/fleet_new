<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_licenses', function (Blueprint $table) {
            $table->string('usage_type', 20)->nullable()->after('assigned_tracked_user_id');
            $table->foreignId('free_claimed_by_user_id')->nullable()->after('is_free_claim')
                ->constrained('users')->restrictOnDelete();
        });

        DB::table('user_licenses')
            ->where('is_free_claim', true)
            ->whereNull('free_claimed_by_user_id')
            ->update(['free_claimed_by_user_id' => DB::raw('user_id')]);

        Schema::create('license_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_license_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('admin_transfer')->default(false);
            $table->timestamp('transferred_at');
            $table->timestamps();
            $table->index(['from_user_id', 'transferred_at']);
            $table->index(['to_user_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_transfers');

        Schema::table('user_licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('free_claimed_by_user_id');
            $table->dropColumn('usage_type');
        });
    }
};