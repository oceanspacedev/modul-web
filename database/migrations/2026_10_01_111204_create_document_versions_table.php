<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('documents', 'version')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->integer('version')->default(1)->after('path');
            });
        }

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->integer('version_number')->default(1);
            $table->string('file_name');
            $table->string('path');
            $table->string('file_size')->nullable();
            $table->text('change_note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        // Backfill existing documents as Version 1
        $existingDocuments = \Illuminate\Support\Facades\DB::table('documents')->get();
        foreach ($existingDocuments as $doc) {
            \Illuminate\Support\Facades\DB::table('document_versions')->insert([
                'document_id' => $doc->id,
                'version_number' => 1,
                'file_name' => $doc->name,
                'path' => $doc->path,
                'change_note' => 'Versi Awal Dokumen',
                'created_at' => $doc->created_at ?? now(),
                'updated_at' => $doc->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_versions');

        if (Schema::hasColumn('documents', 'version')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
