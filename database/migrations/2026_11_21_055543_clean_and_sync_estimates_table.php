<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('estimates')) {
            Schema::create('estimates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('application_id')->nullable()->index();
                $table->unsignedBigInteger('id_application')->nullable()->index();

                // 10 основных сметных показателей калькуляции
                $table->decimal('price_sp_full', 12, 2)->default(0);        // Стоимость узлов и деталей
                $table->decimal('price_sp_half', 12, 2)->default(0);        // Стоимость узлов и деталей с учетом износа
                $table->decimal('price_repair', 12, 2)->default(0);         // Стоимость ремонта/замены
                $table->decimal('price_paint', 12, 2)->default(0);          // Стоимость окраски контроля
                $table->decimal('price_zip', 12, 2)->default(0);            // Стоимость расходных материалов
                $table->decimal('price_work', 12, 2)->default(0);           // Стоимость работ и материалов
                $table->decimal('price_repair_half', 12, 2)->default(0);    // Стоимость восст. ремонта (с учетом износа/скидки)
                $table->decimal('price_repair_full', 12, 2)->default(0);    // Стоимость восст. ремонта (Всего)
                $table->decimal('add_pay', 12, 2)->default(0);              // Доплата
                $table->decimal('price_repair_full_md2', 12, 2)->default(0);// Стоимость восст. ремонта с учетом мелких деталей

                // Расчетные фактические показатели
                $table->decimal('fact', 12, 2)->default(0);

                $table->timestamps();
            });
        } else {
            Schema::table('estimates', function (Blueprint $table) {
                $cols = Schema::getColumnListing('estimates');

                // Добавляем недостающие поля из 10 показателей
                $neededDecimals = [
                    'price_sp_full',
                    'price_sp_half',
                    'price_repair',
                    'price_paint',
                    'price_zip',
                    'price_work',
                    'price_repair_half',
                    'price_repair_full',
                    'add_pay',
                    'price_repair_full_md2',
                    'fact',
                ];

                foreach ($neededDecimals as $col) {
                    if (!in_array($col, $cols, true)) {
                        $table->decimal($col, 12, 2)->default(0);
                    }
                }

                // Гарантируем наличие ключа заявки
                if (!in_array('application_id', $cols, true) && !in_array('id_application', $cols, true)) {
                    $table->unsignedBigInteger('application_id')->nullable()->index();
                }

                // Удаляем ненужные поля сертификата и емр+10, если они существовали
                $unwanted = ['certificate', 'sertificat', 'price_cert', 'emr_10', 'price_emr_10'];
                foreach ($unwanted as $dropCol) {
                    if (in_array($dropCol, $cols, true)) {
                        $table->dropColumn($dropCol);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Откат структуры не требуется, сохраняем целостность данных
    }
};