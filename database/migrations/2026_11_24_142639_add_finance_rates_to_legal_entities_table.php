<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasNdsRate = Schema::hasColumn(
            'legal_entities',
            'nds_rate'
        );

        $hasIncomeTaxRate = Schema::hasColumn(
            'legal_entities',
            'income_tax_rate'
        );

        Schema::table(
            'legal_entities',
            function (Blueprint $table) use ($hasNdsRate, $hasIncomeTaxRate) {
                if (!$hasNdsRate) {
                    $table->decimal('nds_rate', 5, 2)
                        ->nullable()
                        ->comment('Ставка НДС, %. NULL — не настроено');
                }

                if (!$hasIncomeTaxRate) {
                    $table->decimal('income_tax_rate', 5, 2)
                        ->nullable()
                        ->comment(
                            'Ставка налога с дохода для управленческого расчёта, %'
                        );
                }
            }
        );
    }

    public function down(): void
    {
        /*
         * Ставки могут быть заполнены после применения миграции.
         * Автоматически их не удаляем.
         */
    }
};