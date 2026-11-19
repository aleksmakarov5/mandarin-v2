<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Поля для таблицы applications (КАСКО, скидки, сметные итоги)
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (!Schema::hasColumn('applications', 'discount_percent')) {
                    $table->decimal('discount_percent', 5, 2)->default(0)->after('ub_type');
                }
                if (!Schema::hasColumn('applications', 'sum_sk')) {
                    $table->decimal('sum_sk', 12, 2)->default(0);
                }
                if (!Schema::hasColumn('applications', 'sum_zap')) {
                    $table->decimal('sum_zap', 12, 2)->default(0);
                }
                if (!Schema::hasColumn('applications', 'remont_sum')) {
                    $table->decimal('remont_sum', 12, 2)->default(0);
                }
            });
        }

        // 2. Поля для таблицы spare_parts (Запчасти, КАСКО/ПЗН, поставщики)
        if (Schema::hasTable('spare_parts')) {
            Schema::table('spare_parts', function (Blueprint $table) {
                if (!Schema::hasColumn('spare_parts', 'application_id') && !Schema::hasColumn('spare_parts', 'id_application')) {
                    $table->unsignedBigInteger('application_id')->after('id');
                }
                if (!Schema::hasColumn('spare_parts', 'part_number')) {
                    $table->string('part_number', 100)->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'order_number')) {
                    $table->string('order_number', 100)->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'price_ic_full')) {
                    $table->decimal('price_ic_full', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('spare_parts', 'price_ic_half')) {
                    $table->decimal('price_ic_half', 10, 2)->default(0); // Цена со скидкой (КАСКО)
                }
                if (!Schema::hasColumn('spare_parts', 'price_sto')) {
                    $table->decimal('price_sto', 10, 2)->default(0); // Цена ПЗН (ОСАГО)
                }
                if (!Schema::hasColumn('spare_parts', 'iznos')) {
                    $table->decimal('iznos', 5, 2)->default(0); // Скидка / износ
                }
                if (!Schema::hasColumn('spare_parts', 'price_original')) {
                    $table->decimal('price_original', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'price_analogue')) {
                    $table->decimal('price_analogue', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'price_used')) {
                    $table->decimal('price_used', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'selected_price')) {
                    $table->tinyInteger('selected_price')->default(0); // 1-оригинал, 2-аналог, 3-бу, 4-СТО
                }
                if (!Schema::hasColumn('spare_parts', 'selected_mrk')) {
                    $table->tinyInteger('selected_mrk')->default(0);
                }
                if (!Schema::hasColumn('spare_parts', 'date_wait')) {
                    $table->date('date_wait')->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'date_price')) {
                    $table->date('date_price')->nullable();
                }
                if (!Schema::hasColumn('spare_parts', 'is_small_parts')) {
                    $table->boolean('is_small_parts')->default(false);
                }
                if (!Schema::hasColumn('spare_parts', 'comment')) {
                    $table->text('comment')->nullable();
                }
            });
        }

        // 3. Поля для таблицы works (Работы)
        if (Schema::hasTable('works')) {
            Schema::table('works', function (Blueprint $table) {
                if (!Schema::hasColumn('works', 'application_id') && !Schema::hasColumn('works', 'id_application')) {
                    $table->unsignedBigInteger('application_id')->after('id');
                }
                if (!Schema::hasColumn('works', 'work_type') && !Schema::hasColumn('works', 'type')) {
                    $table->tinyInteger('work_type')->default(1); // 1-слесарные, 2-кузовные, 3-малярные
                }
                if (!Schema::hasColumn('works', 'count_hours')) {
                    $table->decimal('count_hours', 8, 2)->default(0);
                }
                if (!Schema::hasColumn('works', 'price')) {
                    $table->decimal('price', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('works', 'summ')) {
                    $table->decimal('summ', 10, 2)->default(0); // Итог СК
                }
                if (!Schema::hasColumn('works', 'price_service')) {
                    $table->decimal('price_service', 10, 2)->default(0); // Итог СТО
                }
            });
        }
    }

    public function down(): void
    {
    }
};