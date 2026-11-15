<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tautan dan catatan off cam berlaku untuk platform meeting apa pun, bukan hanya Zoom.
        Schema::table('trainings', function (Blueprint $table) {
            $table->renameColumn('zoom_link', 'meeting_link');
        });
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('meeting_platform', 20)->nullable();
            $table->boolean('meeting_monitoring_enabled')->default(false);
            $table->foreignId('meeting_account_id')->nullable()->constrained('meeting_accounts')->restrictOnDelete();
            $table->uuid('meeting_monitoring_revision')->nullable();
        });

        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('zoom_display_name', 'meeting_display_name');
        });
        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('zoom_off_cam_at', 'off_cam_at');
        });
    }

    public function down(): void
    {
        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('off_cam_at', 'zoom_off_cam_at');
        });
        Schema::table('training_participants', function (Blueprint $table) {
            $table->renameColumn('meeting_display_name', 'zoom_display_name');
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_account_id');
            $table->dropColumn(['meeting_platform', 'meeting_monitoring_enabled', 'meeting_monitoring_revision']);
        });
        Schema::table('trainings', function (Blueprint $table) {
            $table->renameColumn('meeting_link', 'zoom_link');
        });
    }
};
