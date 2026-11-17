<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('finance_statuses', function (Blueprint $table) {
            // Цвет фона (если color использовался как фон, делаем алиас/доп поле)
            if (!Schema::hasColumn('finance_statuses', 'bg_color')) {
                $table->string('bg_color', 32)->nullable()->after('color')->comment('Цвет фона');
            }

            // Цвет текста (по умолчанию темный или белый)
            if (!Schema::hasColumn('finance_statuses', 'text_color')) {
                $table->string('text_color', 32)->nullable()->default('#ffffff')->after('bg_color')->comment('Цвет текста');
            }

            // Количество времени на статус
            if (!Schema::hasColumn('finance_statuses', 'time')) {
                $table->unsignedInteger('time')->nullable()->after('text_color')->comment('Нормативное время на статус');
            }

            // Флаг: true — дни, false — часы
            if (!Schema::hasColumn('finance_statuses', 'is_day')) {
                $table->boolean('is_day')->default(true)->after('time')->comment('1 - дни, 0 - часы');
            }

            // Тип статуса (если используется классификация этапов)
            if (!Schema::hasColumn('finance_statuses', 'type')) {
                $table->string('type', 64)->nullable()->after('is_day')->comment('Тип/группа статуса');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_statuses', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'text_color', 'time', 'is_day', 'type']);
        });
    }
};