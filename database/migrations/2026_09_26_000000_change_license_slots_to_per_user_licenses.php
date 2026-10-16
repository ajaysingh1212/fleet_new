<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_plans', function (Blueprint $table) {
            $table->unsignedInteger('maximum_tracking_slots')->default(1)->change();
            $table->decimal('renewal_price', 12, 2)->default(0)->after('price');
            $table->boolean('is_free')->default(false)->after('renewal_price');
        });

        DB::table('license_plans')->update(['renewal_price' => DB::raw('price')]);

        Schema::table('user_licenses', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_tracked_user_id')->nullable()->index()->after('user_id');
            $table->boolean('is_free_claim')->default(false)->after('assigned_tracked_user_id');
        });

        Schema::table('tracking_relations', function (Blueprint $table) {
            $table->foreignId('user_license_id')->nullable()->after('tracked_user_id')
                ->constrained('user_licenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_relations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_license_id');
        });

        Schema::table('user_licenses', function (Blueprint $table) {
            $table->dropColumn(['assigned_tracked_user_id', 'is_free_claim']);
        });

        Schema::table('license_plans', function (Blueprint $table) {
            $table->dropColumn(['renewal_price', 'is_free']);
        });
    }
};