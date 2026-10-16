<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('target_lat', 10, 7);
            $table->decimal('target_lng', 10, 7);
            $table->string('label')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_proposals');
    }
};
