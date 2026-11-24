<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Юридические лица (компании): ставка НДС и налог на прибыль
        Schema::table('our_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('our_companies', 'nds_rate')) {
                $table->decimal('nds_rate', 5, 2)->default(20.00)->comment('Ставка НДС % (0 для УСН/Без НДС)');
            }
            if (!Schema::hasColumn('our_companies', 'income_tax_rate')) {
                $table->decimal('income_tax_rate', 5, 2)->default(6.00)->comment('Ставка налога на прибыль / УСН %');
            }
        });

        // 2. Страховые компании: флаг «Делить итоговую сумму»
        Schema::table('insurance_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('insurance_companies', 'split_ic_total')) {
                $table->boolean('split_ic_total')->default(false)->comment('Делить итоговую сумму (ремонт + дефектовка)');
            }
        });

        // 3. Таблица сметы (estimates): раздельные суммы, факт. доходы/расходы
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'ic_repair_sum')) {
                $table->decimal('ic_repair_sum', 12, 2)->default(0)->comment('Сумма СК: ремонт');
            }
            if (!Schema::hasColumn('estimates', 'ic_defect_sum')) {
                $table->decimal('ic_defect_sum', 12, 2)->default(0)->comment('Сумма СК: дефектовка');
            }
            if (!Schema::hasColumn('estimates', 'dds_income_sum')) {
                $table->decimal('dds_income_sum', 12, 2)->default(0)->comment('Фактический доход СК (выписка/ручной)');
            }
            if (!Schema::hasColumn('estimates', 'dds_income_date')) {
                $table->date('dds_income_date')->nullable()->comment('Дата поступления дохода СК');
            }
            if (!Schema::hasColumn('estimates', 'dds_sto_pay_date')) {
                $table->date('dds_sto_pay_date')->nullable()->comment('Дата фактического расчета с СТО');
            }
            if (!Schema::hasColumn('estimates', 'dds_extra_expenses')) {
                $table->decimal('dds_extra_expenses', 12, 2)->default(0)->comment('Сумма дополнительных расходов');
            }
        });

        // 4. Заявки (applications): персональные % для дела
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'nds_mode')) {
                $table->tinyInteger('nds_mode')->default(0)->comment('0 - сумма включая НДС, 1 - сумма + НДС');
            }
            if (!Schema::hasColumn('applications', 'mrk_proc_custom')) {
                $table->decimal('mrk_proc_custom', 5, 2)->nullable()->comment('Кастомный % ЗП для МРК по делу');
            }
            if (!Schema::hasColumn('applications', 'mentor_proc_custom')) {
                $table->decimal('mentor_proc_custom', 5, 2)->nullable()->comment('Кастомный % ЗП для Наставника по делу');
            }
        });

        // 5. Дефолтный процент в таблице users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'def_mrk_proc')) {
                $table->decimal('def_mrk_proc', 5, 2)->default(10.00)->comment('Дефолтный процент ЗП сотрудника');
            }
        });
    }

    public function down(): void
    {
        // Откат при необходимости
    }
};