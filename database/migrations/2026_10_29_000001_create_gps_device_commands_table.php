<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_device_commands', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('gps_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->uuid('session_id');
            $table->string('command', 180);
            $table->string('status', 24)->default('queued');
            $table->text('response')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['gps_device_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_device_commands');
    }
};
