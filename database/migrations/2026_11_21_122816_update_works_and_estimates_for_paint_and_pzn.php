<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Поля в таблице works (единая таблица для слесарных и малярных работ калькуляции)
        Schema::table('works', function (Blueprint $table) {
            if (!Schema::hasColumn('works', 'is_paint')) {
                $table->boolean('is_paint')->default(false)->after('name'); // false = Ремонтные, true = Окрасочные
            }
            if (!Schema::hasColumn('works', 'price')) {
                $table->decimal('price', 10, 2)->default(1000.00)->after('name'); // Ставка н/ч
            }
            if (!Schema::hasColumn('works', 'count_hours')) {
                $table->decimal('count_hours', 8, 2)->default(0.00)->after('price'); // Нормочасы СК
            }
            if (!Schema::hasColumn('works', 'hours_pzn')) {
                $table->decimal('hours_pzn', 8, 2)->default(0.00)->after('count_hours'); // Часы ПЗН
            }
            if (!Schema::hasColumn('works', 'sum_sk')) {
                $table->decimal('sum_sk', 12, 2)->default(0.00)->after('hours_pzn'); // Сумма страховой
            }
            if (!Schema::hasColumn('works', 'sum_pzn')) {
                $table->decimal('sum_pzn', 12, 2)->default(0.00)->after('sum_sk'); // Сумма ПЗН
            }
        });

        // 2. Расходные материалы в таблице estimates (или applications)
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'paint_materials_approved')) {
                $table->decimal('paint_materials_approved', 12, 2)->default(0.00)->after('id'); // Одобренная стоимость ЛКМ
            }
            if (!Schema::hasColumn('estimates', 'paint_materials_plan')) {
                $table->decimal('paint_materials_plan', 12, 2)->default(0.00)->after('paint_materials_approved'); // Плановая стоимость ЛКМ
            }
        });

        // 3. Журнал истории для слесарных и малярных работ
        if (!Schema::hasTable('work_histories')) {
            Schema::create('work_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_id');
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action_type', 50); // updated_field, transferred_to_paint, transferred_to_repair, added, deleted
                $table->string('field_name', 50)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                $table->index(['application_id', 'work_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_histories');
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['paint_materials_approved', 'paint_materials_plan']);
        });
        Schema::table('works', function (Blueprint $table) {
            $table->dropColumn(['is_paint', 'price', 'count_hours', 'hours_pzn', 'sum_sk', 'sum_pzn']);
        });
    }
};