<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            'document_upload_chunks',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('upload_item_id')
                    ->constrained('document_upload_items')
                    ->cascadeOnDelete();

                // Нумерация частей начинается с нуля.
                $table->unsignedInteger('chunk_index');

                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);

                $table->string('storage_disk', 100);
                $table->string('storage_path', 500);

                $table->timestamps();

                $table->unique(
                    ['upload_item_id', 'chunk_index'],
                    'document_upload_chunk_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('document_upload_chunks');
    }
};