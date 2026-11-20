<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Таблица фактических работ
        if (!Schema::hasTable('fact_works')) {
            Schema::create('fact_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->unsignedBigInteger('standard_work_id')->nullable()->index();
                $table->unsignedBigInteger('plan_work_id')->nullable()->index(); // ссылка на план при импорте
                $table->string('name');
                $table->decimal('price', 12, 2)->default(0.00);
                $table->date('execution_date')->nullable();
                $table->tinyInteger('author_type')->default(0); // 0-авто, 1-МРК, 2-Наставник, 4-Сервис, 5-Другой
                $table->string('author_role', 50)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();

                if (Schema::hasTable('applications')) {
                    $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
                }
            });
        }

        // 2. Таблица истории аудита фактических работ
        if (!Schema::hasTable('fact_work_histories')) {
            Schema::create('fact_work_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->unsignedBigInteger('fact_work_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action_type', 40); // created, imported, updated, deleted
                $table->string('field_name', 100)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                if (Schema::hasTable('applications')) {
                    $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_work_histories');
        Schema::dropIfExists('fact_works');
    }
};