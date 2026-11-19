<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            if (!Schema::hasColumn('spare_parts', 'author_type')) {
                // 0 - авто из калькуляции
                // 1 - МРК
                // 2 - Наставник
                // 3 - Запчастист (СПМ)
                // 4 - СТО / Сервис
                // 5 - Другой пользователь
                // 6 - ИИ
                $table->unsignedTinyInteger('author_type')->default(0)->after('is_manual');
            }
            if (!Schema::hasColumn('spare_parts', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropColumn(['author_type', 'updated_by_user_id']);
        });
    }
};