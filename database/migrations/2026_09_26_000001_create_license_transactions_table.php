<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('gateway', 30);
            $table->string('environment', 10);
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider_order_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_transactions');
    }
};