<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            /*
             * Версия состава и порядка фотографий.
             * Увеличивается при добавлении, удалении,
             * перестановке, перемещении и копировании.
             */
            $table->unsignedBigInteger('content_revision')
                ->default(0);

            /*
             * Версия содержимого, для которой собран ZIP.
             * NULL — актуального архива ещё нет.
             */
            $table->unsignedBigInteger('archive_revision')
                ->nullable();

            /*
             * missing
             * pending
             * building
             * ready
             * failed
             */
            $table->string('archive_status', 20)
                ->default('missing');

            $table->text('archive_error')->nullable();

            $table->index(
                ['application_id', 'archive_status'],
                'documents_archive_status_idx'
            );
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->index(
                ['document_id', 'file_role', 'sort_order', 'id'],
                'document_files_gallery_order_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->dropIndex('document_files_gallery_order_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_archive_status_idx');

            $table->dropColumn([
                'content_revision',
                'archive_revision',
                'archive_status',
                'archive_error',
            ]);
        });
    }
};