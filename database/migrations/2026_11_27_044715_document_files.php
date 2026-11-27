<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('document_files')) {
            return;
        }

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('document_id');

            // Для превью и производных файлов
            $table->unsignedBigInteger('parent_file_id')
                ->nullable()
                ->index();

            /*
             * original
             * preview
             * thumbnail
             * archive
             * generated_pdf
             * generated_docx
             */
            $table->string('file_role', 30)->default('original');

            $table->string('original_name', 255);
            $table->string('stored_name', 255)->nullable();

            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 1000);

            $table->string('extension', 30)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64)->nullable()->index();

            // Используется для галереи
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('preview_path', 1000)->nullable();

            /*
             * pending
             * processing
             * ready
             * failed
             */
            $table->string('processing_status', 30)->default('ready');
            $table->text('processing_error')->nullable();

            $table->boolean('is_current')->default(true);
            $table->boolean('is_inline')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();

            $table->unsignedBigInteger('legacy_attachment_id')
                ->nullable()
                ->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->cascadeOnDelete();

            $table->foreign('parent_file_id')
                ->references('id')
                ->on('document_files')
                ->nullOnDelete();

            $table->index([
                'document_id',
                'file_role',
                'is_current',
            ]);

            $table->index([
                'document_id',
                'processing_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
    }
};