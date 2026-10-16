<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 10)->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->dateTime('joined_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable()->index();
            $table->dateTime('muted_until')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('forwarded_from_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->boolean('is_edited')->default(false);
            $table->dateTime('edited_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_user_deletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('deleted_at')->nullable();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 20);
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('type', 20);
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('status', 20)->default('ringing')->index();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('ended_reason', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('call_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('invited');
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_video_enabled')->default(true);
            $table->timestamps();

            $table->unique(['call_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_participants');
        Schema::dropIfExists('call_sessions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('message_user_deletes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }
};
