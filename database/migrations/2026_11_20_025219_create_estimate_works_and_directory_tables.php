<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Справочник типовых работ (каталог операций)
        if (!Schema::hasTable('standard_works')) {
            Schema::create('standard_works', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique(); // Наименование типовой работы
                $table->string('category', 50)->default('слесарные'); // слесарные, кузовные, малярные, диагностика
                $table->decimal('default_norm_hours', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Цены типовых работ для конкретных СТО (Прайс-лист сервиса)
        if (!Schema::hasTable('service_standard_work_prices')) {
            Schema::create('service_standard_work_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_id'); // ID автосервиса (СТО)
                $table->unsignedBigInteger('standard_work_id');
                $table->decimal('price', 10, 2)->default(0); // Цена этой работы в данном сервисе
                $table->timestamps();

                $table->foreign('standard_work_id')->references('id')->on('standard_works')->cascadeOnDelete();
                $table->unique(['service_id', 'standard_work_id'], 'service_work_unique');
            });
        }

        // 3. Плановые работы дела (3 столбца: #, наименование, цена) + согласование СТО
        if (!Schema::hasTable('plan_works')) {
            Schema::create('plan_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('standard_work_id')->nullable(); // Ссылка на справочник (если подтянуто)
                $table->string('name');
                $table->decimal('price', 10, 2)->default(0);

                // Статус согласования сервиса:
                // draft (черновик), pending (отправлено на согласование), approved (принято МРК), rejected (отклонено)
                $table->string('approval_status', 20)->default('draft');
                $table->text('reject_reason')->nullable();

                // Авторство и раскраска
                $table->tinyInteger('author_type')->default(0); // 0-авто, 1-мрк, 2-наставник, 4-сервис, 5-другой
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();

                $table->timestamps();
            });
        }

        // 4. Фактические работы (Акт выполненных работ СТО)
        if (!Schema::hasTable('fact_works')) {
            Schema::create('fact_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id');
                $table->string('name');
                $table->decimal('price_service', 10, 2)->default(0); // Цена сервиса
                $table->date('execution_date')->nullable(); // Дата выполнения

                $table->tinyInteger('author_type')->default(0);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();

                $table->timestamps();
            });
        }

        // 5. Дополнительные затраты (эвакуатор, мойка, такси, сертификаты)
        if (!Schema::hasTable('extra_expenses')) {
            Schema::create('extra_expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id');
                $table->string('name'); // Название статьи расхода
                $table->string('expense_type', 50)->default('прочее'); // эвакуатор, мойка, такси, сертификат
                $table->decimal('sum', 10, 2)->default(0);
                $table->text('comment')->nullable();

                $table->tinyInteger('author_type')->default(0);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();

                $table->timestamps();
            });
        }

        // 6. Поля статуса согласования в таблице applications
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (!Schema::hasColumn('applications', 'plan_works_approved_by_service')) {
                    $table->boolean('plan_works_approved_by_service')->default(false);
                }
                if (!Schema::hasColumn('applications', 'plan_works_fully_approved')) {
                    $table->boolean('plan_works_fully_approved')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_expenses');
        Schema::dropIfExists('fact_works');
        Schema::dropIfExists('plan_works');
        Schema::dropIfExists('service_standard_work_prices');
        Schema::dropIfExists('standard_works');
    }
};