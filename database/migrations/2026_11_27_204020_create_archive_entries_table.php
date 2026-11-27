<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('archive_entries')) {
            return;
        }
        Schema::create('archive_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('archive_file_id')
                ->constrained('document_files')
                ->cascadeOnDelete();

            $table->string('entry_path', 1000);
            $table->string('entry_name', 255);

            $table->string('entry_type', 20)
                ->default('file');
            // file, directory, symlink, unsupported

            $table->string('extension', 30)->nullable();
            $table->string('mime_type', 255)->nullable();

            $table->unsignedBigInteger('compressed_size')
                ->nullable();

            $table->unsignedBigInteger('uncompressed_size')
                ->nullable();

            $table->unsignedInteger('depth')->default(0);

            $table->boolean('is_safe')->default(false);
            $table->boolean('is_previewable')->default(false);

            $table->string('status', 30)->default('indexed');
            // indexed, unsafe, failed, deleted

            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(
                ['archive_file_id', 'entry_path'],
                'archive_entries_file_path_unique'
            );

            $table->index([
                'archive_file_id',
                'entry_type',
                'is_safe',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_entries');
    }
};