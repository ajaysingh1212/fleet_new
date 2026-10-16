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
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable()->after('priority');
            $table->timestamp('resolved_at')->nullable()->after('resolution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['resolution', 'resolved_at']);
        });
    }
};
