<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu sesi pencatatan aktivitas sebuah rapat (provider + meeting_code) dengan satu konfigurasi akun
        // (account_revision). source "live": petugas memantau dari browser selama rapat. source "import": data
        // ditarik dari API platform.
        Schema::create('training_meeting_monitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('meeting_code', 128);
            $table->string('source', 10)->default('live');
            $table->char('account_revision', 64);
            $table->dateTime('started_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('ended_at')->nullable();
            $table->index(['training_id', 'provider', 'meeting_code', 'ended_at', 'last_seen_at'], 'training_meeting_monitor_activity');
        });

        Schema::create('training_meeting_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('monitor_id')->constrained('training_meeting_monitors')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('type', 10);
            $table->dateTime('observed_at', 3);
            $table->string('participant_key', 128);
            $table->string('name');
            $table->boolean('camera_on')->nullable();
            $table->unique(['monitor_id', 'event_id']);
            $table->index(['monitor_id', 'observed_at']);
        });

        Schema::create('training_meeting_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('meeting_code', 128);
            $table->string('sent_time', 32);
            $table->string('sender');
            $table->text('message');
            $table->char('fingerprint', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['training_id', 'provider', 'meeting_code', 'fingerprint'], 'training_meeting_chat_message_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_meeting_chat_messages');
        Schema::dropIfExists('training_meeting_events');
        Schema::dropIfExists('training_meeting_monitors');
    }
};
