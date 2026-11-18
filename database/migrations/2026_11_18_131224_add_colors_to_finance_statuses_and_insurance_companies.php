<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Финансовые статусы
        if (Schema::hasTable('finance_statuses')) {
            Schema::table('finance_statuses', function (Blueprint $table) {
                if (!Schema::hasColumn('finance_statuses', 'bg_color')) {
                    $table->string('bg_color', 30)->default('#e0f2fe')->after('color');
                }
                if (!Schema::hasColumn('finance_statuses', 'text_color')) {
                    $table->string('text_color', 30)->default('#0369a1')->after('bg_color');
                }
                if (!Schema::hasColumn('finance_statuses', 'time')) {
                    $table->integer('time')->default(0)->after('text_color');
                }
                if (!Schema::hasColumn('finance_statuses', 'is_day')) {
                    $table->boolean('is_day')->default(false)->after('time');
                }
            });
        }

        // 2. Страховые компании
        if (Schema::hasTable('insurance_companies')) {
            Schema::table('insurance_companies', function (Blueprint $table) {
                if (!Schema::hasColumn('insurance_companies', 'bg_color')) {
                    $table->string('bg_color', 30)->default('#fce7f3')->after('name');
                }
                if (!Schema::hasColumn('insurance_companies', 'text_color')) {
                    $table->string('text_color', 30)->default('#be185d')->after('bg_color');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('finance_statuses')) {
            Schema::table('finance_statuses', function (Blueprint $table) {
                $table->dropColumn(['bg_color', 'text_color']);
            });
        }

        if (Schema::hasTable('insurance_companies')) {
            Schema::table('insurance_companies', function (Blueprint $table) {
                $table->dropColumn(['bg_color', 'text_color']);
            });
        }
    }
};