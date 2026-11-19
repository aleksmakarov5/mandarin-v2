<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // =========================================================================
        // 1. ТАБЛИЦА: spare_parts (Запасные части)
        // =========================================================================
        if (!Schema::hasTable('spare_parts')) {
            Schema::create('spare_parts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->string('name')->nullable();
                $table->string('part_number')->nullable();
                $table->integer('count')->default(1);

                // Цены СК и ПЗН
                $table->decimal('price_ic', 12, 2)->default(0);
                $table->decimal('price_pzn', 12, 2)->default(0);

                // Поставщики
                $table->decimal('price_sto', 12, 2)->default(0);
                $table->decimal('price_emex', 12, 2)->default(0);
                $table->decimal('price_ap', 12, 2)->default(0);
                $table->decimal('price_ea', 12, 2)->default(0);

                // Выбранный поставщик
                $table->string('selected_supplier', 50)->nullable();
                $table->decimal('price_selected', 12, 2)->default(0);

                $table->string('status', 50)->default('draft');
                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            });
        } else {
            Schema::table('spare_parts', function (Blueprint $table) {
                if (!Schema::hasColumn('spare_parts', 'application_id') && Schema::hasColumn('spare_parts', 'id_application')) {
                    // Если поле называется id_application, оставляем его или делаем алиас
                } elseif (!Schema::hasColumn('spare_parts', 'application_id')) {
                    $table->unsignedBigInteger('application_id')->nullable()->index()->after('id');
                }

                if (!Schema::hasColumn('spare_parts', 'name'))
                    $table->string('name')->nullable();
                if (!Schema::hasColumn('spare_parts', 'part_number'))
                    $table->string('part_number')->nullable();
                if (!Schema::hasColumn('spare_parts', 'count'))
                    $table->integer('count')->default(1);

                if (!Schema::hasColumn('spare_parts', 'price_ic'))
                    $table->decimal('price_ic', 12, 2)->default(0);
                if (!Schema::hasColumn('spare_parts', 'price_pzn'))
                    $table->decimal('price_pzn', 12, 2)->default(0);

                if (!Schema::hasColumn('spare_parts', 'price_sto'))
                    $table->decimal('price_sto', 12, 2)->default(0);
                if (!Schema::hasColumn('spare_parts', 'price_emex'))
                    $table->decimal('price_emex', 12, 2)->default(0);
                if (!Schema::hasColumn('spare_parts', 'price_ap'))
                    $table->decimal('price_ap', 12, 2)->default(0);
                if (!Schema::hasColumn('spare_parts', 'price_ea'))
                    $table->decimal('price_ea', 12, 2)->default(0);

                if (!Schema::hasColumn('spare_parts', 'selected_supplier'))
                    $table->string('selected_supplier', 50)->nullable();
                if (!Schema::hasColumn('spare_parts', 'price_selected'))
                    $table->decimal('price_selected', 12, 2)->default(0);

                if (!Schema::hasColumn('spare_parts', 'status'))
                    $table->string('status', 50)->default('draft');
            });
        }

        // =========================================================================
        // 2. ТАБЛИЦА: works (Работы калькуляции СК: ремонтные и окрасочные)
        // =========================================================================
        if (!Schema::hasTable('works')) {
            Schema::create('works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->enum('work_category', ['repair', 'paint'])->default('repair');
                $table->string('name');

                $table->decimal('norm_hours', 8, 2)->default(0);
                $table->decimal('pzn_hours', 8, 2)->default(0);
                $table->decimal('price_per_hour', 10, 2)->default(0);

                $table->decimal('sum_ic', 12, 2)->default(0);
                $table->decimal('sum_pzn', 12, 2)->default(0);
                $table->decimal('sum_materials', 12, 2)->default(0);

                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            });
        } else {
            Schema::table('works', function (Blueprint $table) {
                if (!Schema::hasColumn('works', 'application_id') && !Schema::hasColumn('works', 'id_application')) {
                    $table->unsignedBigInteger('application_id')->nullable()->index()->after('id');
                }

                if (!Schema::hasColumn('works', 'work_category'))
                    $table->enum('work_category', ['repair', 'paint'])->default('repair');
                if (!Schema::hasColumn('works', 'name'))
                    $table->string('name')->nullable();

                if (!Schema::hasColumn('works', 'norm_hours'))
                    $table->decimal('norm_hours', 8, 2)->default(0);
                if (!Schema::hasColumn('works', 'pzn_hours'))
                    $table->decimal('pzn_hours', 8, 2)->default(0);
                if (!Schema::hasColumn('works', 'price_per_hour'))
                    $table->decimal('price_per_hour', 10, 2)->default(0);

                if (!Schema::hasColumn('works', 'sum_ic'))
                    $table->decimal('sum_ic', 12, 2)->default(0);
                if (!Schema::hasColumn('works', 'sum_pzn'))
                    $table->decimal('sum_pzn', 12, 2)->default(0);
                if (!Schema::hasColumn('works', 'sum_materials'))
                    $table->decimal('sum_materials', 12, 2)->default(0);
            });
        }

        // =========================================================================
        // 3. ТАБЛИЦА: plan_works (Плановые работы МРК / Ремонтная ведомость)
        // =========================================================================
        if (!Schema::hasTable('plan_works')) {
            Schema::create('plan_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->enum('work_category', ['repair', 'paint'])->default('repair');
                $table->string('name');
                $table->decimal('norm_hours', 8, 2)->default(0);
                $table->decimal('price_plan', 12, 2)->default(0);
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            });
        } else {
            Schema::table('plan_works', function (Blueprint $table) {
                if (!Schema::hasColumn('plan_works', 'application_id') && !Schema::hasColumn('plan_works', 'id_application')) {
                    $table->unsignedBigInteger('application_id')->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('plan_works', 'work_category'))
                    $table->enum('work_category', ['repair', 'paint'])->default('repair');
                if (!Schema::hasColumn('plan_works', 'name'))
                    $table->string('name')->nullable();
                if (!Schema::hasColumn('plan_works', 'norm_hours'))
                    $table->decimal('norm_hours', 8, 2)->default(0);
                if (!Schema::hasColumn('plan_works', 'price_plan'))
                    $table->decimal('price_plan', 12, 2)->default(0);
                if (!Schema::hasColumn('plan_works', 'comment'))
                    $table->text('comment')->nullable();
            });
        }

        // =========================================================================
        // 4. ТАБЛИЦА: fact_works (Фактические работы СТО и начисление ЗП)
        // =========================================================================
        if (!Schema::hasTable('fact_works')) {
            Schema::create('fact_works', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->index();
                $table->enum('work_category', ['repair', 'paint'])->default('repair');
                $table->string('name');
                $table->decimal('fact_hours', 8, 2)->default(0);
                $table->decimal('price_sto', 12, 2)->default(0);
                $table->decimal('salary_sum', 12, 2)->default(0);
                $table->date('done_date')->nullable();
                $table->timestamps();

                $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            });
        } else {
            Schema::table('fact_works', function (Blueprint $table) {
                if (!Schema::hasColumn('fact_works', 'application_id') && !Schema::hasColumn('fact_works', 'id_application')) {
                    $table->unsignedBigInteger('application_id')->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('fact_works', 'work_category'))
                    $table->enum('work_category', ['repair', 'paint'])->default('repair');
                if (!Schema::hasColumn('fact_works', 'name'))
                    $table->string('name')->nullable();
                if (!Schema::hasColumn('fact_works', 'fact_hours'))
                    $table->decimal('fact_hours', 8, 2)->default(0);
                if (!Schema::hasColumn('fact_works', 'price_sto'))
                    $table->decimal('price_sto', 12, 2)->default(0);
                if (!Schema::hasColumn('fact_works', 'salary_sum'))
                    $table->decimal('salary_sum', 12, 2)->default(0);
                if (!Schema::hasColumn('fact_works', 'done_date'))
                    $table->date('done_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Откат безопасный — не удаляем существующие боевые таблицы целиком
    }
};