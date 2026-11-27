<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
         * Нераспределённый документ:
         * document_type_id = NULL.
         *
         * Несколько документов одного типа разрешены:
         * уникального индекса application_id + document_type_id нет.
         */
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')
                ->nullable()
                ->change();
        });

        if (!Schema::hasColumn('documents', 'document_date')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->date('document_date')->nullable();
            });
        }

        /*
         * Пакет может создавать новый комплект
         * или добавлять файлы в существующий.
         */
        if (!Schema::hasColumn('document_upload_batches', 'document_id')) {
            Schema::table(
                'document_upload_batches',
                function (Blueprint $table) {
                    $table->foreignId('document_id')
                        ->nullable()
                        ->constrained('documents')
                        ->nullOnDelete();
                }
            );
        }

        if (
            !Schema::hasColumn(
                'document_upload_batches',
                'document_type_id'
            )
        ) {
            Schema::table(
                'document_upload_batches',
                function (Blueprint $table) {
                    $table->foreignId('document_type_id')
                        ->nullable()
                        ->constrained('document_types')
                        ->restrictOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        Schema::table(
            'document_upload_batches',
            function (Blueprint $table) {
                $table->dropConstrainedForeignId('document_type_id');
                $table->dropConstrainedForeignId('document_id');
            }
        );

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('document_date');
        });

        /*
         * document_type_id обратно NOT NULL не делаем:
         * после эксплуатации могут существовать
         * нераспределённые документы.
         */
    }
};