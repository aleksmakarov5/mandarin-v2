<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('document_type_id');

            $table->string('title', 255)->nullable();

            /*
             * manual
             * generated
             * email       — зарезервировано для следующего этапа;
             * chat        — зарезервировано для следующего этапа;
             * legacy
             * archive_import
             */
            $table->string('source_type', 30)->default('manual');

            /*
             * active
             * processing
             * archived
             * deleted
             * failed
             */
            $table->string('lifecycle_status', 30)->default('active');

            /*
             * Для будущего разграничения:
             * all
             * internal
             * sto
             * mrk
             */
            $table->string('visibility_scope', 30)->default('all');

            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by_user_id')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            /*
             * Старые идентификаторы сохраняем для миграции
             * и поиска исходной записи.
             */
            $table->unsignedBigInteger('legacy_attachment_id')
                ->nullable()
                ->index();

            $table->unsignedBigInteger('legacy_doc_status_id')
                ->nullable()
                ->index();

            // Пока почту не реализуем, FK на сообщения не создаём.
            $table->unsignedBigInteger('source_message_id')
                ->nullable()
                ->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->foreign('document_type_id')
                ->references('id')
                ->on('document_types')
                ->restrictOnDelete();

            $table->index([
                'application_id',
                'document_type_id',
                'lifecycle_status',
            ]);

            $table->index([
                'application_id',
                'is_archived',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};