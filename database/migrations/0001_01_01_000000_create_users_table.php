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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable()->unique();
            $table->string('phone_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('country_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 25)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['country_id', 'name']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('country_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['state_id', 'name']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('locale', 10)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('employee_id', 50)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('department', 100)->nullable()->index();
            $table->string('designation', 100)->nullable()->index();
            $table->string('company', 150)->nullable()->index();
            $table->string('avatar')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zip_code', 20)->nullable();
            $table->string('timezone', 100)->default('UTC');
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->string('theme', 20)->default('light');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('failed_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('attempted_at')->index();
            $table->timestamps();
        });

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('device_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('group', 100)->index();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->boolean('is_public')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['group', 'key']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('logged_at')->index();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('channel', 50);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'channel']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('open')->index();
            $table->string('priority', 20)->default('medium');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('device_sessions');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('failed_logins');
        Schema::dropIfExists('users');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
