<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('type', 30)->index();
            $table->unsignedInteger('duration_in_days')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('maximum_tracking_slots');
            $table->string('status', 20)->default('active')->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->constrained()->restrictOnDelete();
            $table->string('license_number')->unique();
            $table->timestamp('purchase_date')->index();
            $table->timestamp('activation_date')->nullable();
            $table->timestamp('expiry_date')->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('remaining_slots')->default(0);
            $table->unsignedInteger('consumed_slots')->default(0);
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('order_number')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tracking_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tracker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tracked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_name', 120);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tracker_user_id', 'tracked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_relations');
        Schema::dropIfExists('user_licenses');
        Schema::dropIfExists('license_plans');
    }
};
