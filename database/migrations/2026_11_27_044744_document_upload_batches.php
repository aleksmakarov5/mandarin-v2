<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('document_upload_batches')) {
            Schema::create('document_upload_batches', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('application_id');

                /*
                 * photos
                 * documents
                 * archive
                 */
                $table->string('batch_type', 30);

                /*
                 * pending
                 * uploading
                 * processing
                 * ready
                 * partially_failed
                 * failed
                 */
                $table->string('status', 30)->default('pending');

                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('uploaded_count')->default(0);
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);

                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->string('client_batch_key', 100)
                    ->nullable()
                    ->unique();

                $table->timestamps();

                $table->foreign('application_id')
                    ->references('id')
                    ->on('applications')
                    ->cascadeOnDelete();

                $table->index([
                    'application_id',
                    'batch_type',
                    'status',
                ]);
            });
        }

        if (!Schema::hasTable('document_upload_items')) {
            Schema::create('document_upload_items', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('batch_id');

                $table->string('client_file_key', 150);
                $table->string('original_name', 255);
                $table->string('mime_type', 255)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);

                $table->string('temp_disk', 50)->nullable();
                $table->string('temp_path', 1000)->nullable();

                /*
                 * pending
                 * uploaded
                 * processing
                 * ready
                 * failed
                 */
                $table->string('status', 30)->default('pending');
                $table->text('error')->nullable();

                $table->unsignedBigInteger('document_file_id')->nullable();

                $table->timestamps();

                $table->foreign('batch_id')
                    ->references('id')
                    ->on('document_upload_batches')
                    ->cascadeOnDelete();

                $table->foreign('document_file_id')
                    ->references('id')
                    ->on('document_files')
                    ->nullOnDelete();

                $table->unique([
                    'batch_id',
                    'client_file_key',
                ]);

                $table->index([
                    'batch_id',
                    'status',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_upload_items');
        Schema::dropIfExists('document_upload_batches');
    }
};