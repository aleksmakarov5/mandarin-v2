<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('archive_entries')) {
            return;
        }

        $columns = Schema::getColumnListing('archive_entries');

        Schema::table('archive_entries', function (Blueprint $table) use ($columns) {
            if (!in_array('entry_type', $columns, true)) {
                $table->string('entry_type', 20)
                    ->default('file')
                    ->after('entry_name');
            }

            if (!in_array('extension', $columns, true)) {
                $table->string('extension', 30)
                    ->nullable()
                    ->after('entry_type');
            }

            if (!in_array('mime_type', $columns, true)) {
                $table->string('mime_type', 255)
                    ->nullable()
                    ->after('extension');
            }

            if (!in_array('compressed_size', $columns, true)) {
                $table->unsignedBigInteger('compressed_size')
                    ->nullable();
            }

            if (!in_array('uncompressed_size', $columns, true)) {
                $table->unsignedBigInteger('uncompressed_size')
                    ->nullable();
            }

            if (!in_array('depth', $columns, true)) {
                $table->unsignedInteger('depth')
                    ->default(0);
            }

            if (!in_array('is_safe', $columns, true)) {
                $table->boolean('is_safe')
                    ->default(false);
            }

            if (!in_array('is_previewable', $columns, true)) {
                $table->boolean('is_previewable')
                    ->default(false);
            }

            if (!in_array('status', $columns, true)) {
                $table->string('status', 30)
                    ->default('indexed');
            }

            if (!in_array('error', $columns, true)) {
                $table->text('error')->nullable();
            }

            if (!in_array('created_at', $columns, true)) {
                $table->timestamp('created_at')->nullable();
            }

            if (!in_array('updated_at', $columns, true)) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Данные архива автоматически не удаляем.
    }
};