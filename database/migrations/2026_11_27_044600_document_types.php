<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('document_types')) {
            return;
        }

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            // Совместимость со старым doc_statuses.id
            $table->unsignedBigInteger('legacy_doc_status_id')
                ->nullable()
                ->index();

            $table->string('code', 100)->unique();
            $table->string('name', 255);

            /*
             * incoming
             * client
             * vehicle_movement
             * photo_archive
             * closing
             * supporting
             */
            $table->string('category', 40)->index();

            /*
             * file       — обычный файл;
             * form       — печатная форма;
             * photo      — отдельные фотографии;
             * archive    — архив фотографий;
             * mixed      — может содержать разные варианты.
             */
            $table->string('document_kind', 30)->default('file');

            /*
             * Код бизнес-события.
             * Например:
             * direction_received
             * vehicle_received
             * hidden_damage_recorded
             * vehicle_released
             */
            $table->string('workflow_code', 100)->nullable()->index();

            $table->boolean('is_photo')->default(false);
            $table->boolean('is_archive')->default(false);
            $table->boolean('is_generated')->default(false);
            $table->boolean('allows_multiple')->default(true);
            $table->boolean('allows_upload')->default(true);
            $table->boolean('allows_generation')->default(false);
            $table->boolean('default_sto_view')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};