<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->boolean('view_once')->default(false)->after('height');
        });

        Schema::create('message_attachment_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('opened_at');

            $table->unique(['message_attachment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachment_views');

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn('view_once');
        });
    }
};
