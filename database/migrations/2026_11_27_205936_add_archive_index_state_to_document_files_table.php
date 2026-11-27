<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = Schema::getColumnListing('document_files');

        Schema::table('document_files', function (Blueprint $table) use ($columns) {
            if (!in_array('archive_index_status', $columns, true)) {
                $table->string('archive_index_status', 30)
                    ->nullable()
                    ->after('processing_status');
            }

            if (!in_array('archive_indexed_at', $columns, true)) {
                $table->timestamp('archive_indexed_at')
                    ->nullable();
            }

            if (!in_array('archive_index_error', $columns, true)) {
                $table->text('archive_index_error')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        // Финансовые и документные данные автоматически не удаляем.
    }
};