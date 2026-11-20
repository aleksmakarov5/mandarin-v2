<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fact_works', function (Blueprint $table) {
            if (!Schema::hasColumn('fact_works', 'price')) {
                // Если есть старая колонка sum/cost, можно скопировать данные
                $table->decimal('price', 12, 2)->default(0.00)->after('name');
            }
            if (!Schema::hasColumn('fact_works', 'standard_work_id')) {
                $table->unsignedBigInteger('standard_work_id')->nullable()->after('application_id');
            }
            if (!Schema::hasColumn('fact_works', 'plan_work_id')) {
                $table->unsignedBigInteger('plan_work_id')->nullable()->after('standard_work_id');
            }
            if (!Schema::hasColumn('fact_works', 'author_type')) {
                $table->tinyInteger('author_type')->default(0)->after('price');
            }
            if (!Schema::hasColumn('fact_works', 'author_role')) {
                $table->string('author_role', 50)->nullable()->after('author_type');
            }
            if (!Schema::hasColumn('fact_works', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }
            if (!Schema::hasColumn('fact_works', 'updated_by_user_id')) {
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
            }
        });

        // Если в таблице была колонка 'sum' или 'cost', переносим старые цены в 'price'
        if (Schema::hasColumn('fact_works', 'sum')) {
            \DB::statement('UPDATE fact_works SET price = `sum` WHERE price = 0 AND `sum` IS NOT NULL');
        } elseif (Schema::hasColumn('fact_works', 'cost')) {
            \DB::statement('UPDATE fact_works SET price = `cost` WHERE price = 0 AND `cost` IS NOT NULL');
        }
    }

    public function down(): void
    {
        // Не удаляем колонки при откате во избежание потери данных
    }
};