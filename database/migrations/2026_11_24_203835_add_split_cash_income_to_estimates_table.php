<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = Schema::getColumnListing('estimates');

        Schema::table('estimates', function (Blueprint $table) use ($columns) {
            if (!in_array('dds_repair_income_sum', $columns, true)) {
                $table->decimal('dds_repair_income_sum', 12, 2)
                    ->default(0);
            }

            if (!in_array('dds_repair_income_date', $columns, true)) {
                $table->date('dds_repair_income_date')->nullable();
            }

            if (!in_array('dds_defect_income_sum', $columns, true)) {
                $table->decimal('dds_defect_income_sum', 12, 2)
                    ->default(0);
            }

            if (!in_array('dds_defect_income_date', $columns, true)) {
                $table->date('dds_defect_income_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        /*
         * Не удаляем заполненные финансовые данные автоматически.
         * Откат полей выполняется отдельной миграцией после проверки.
         */
    }
};