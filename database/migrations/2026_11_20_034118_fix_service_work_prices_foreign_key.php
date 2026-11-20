<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('service_work_prices')) {
            Schema::table('service_work_prices', function (Blueprint $table) {
                // Если было поле our_company_id, заменяем его на service_id
                if (Schema::hasColumn('service_work_prices', 'our_company_id')) {
                    $table->dropIndex('work_service_unique');
                    $table->renameColumn('our_company_id', 'service_id');
                    $table->unique(['standard_work_id', 'service_id'], 'work_service_unique');
                }
            });
        } else {
            Schema::create('service_work_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('standard_work_id');
                $table->unsignedBigInteger('service_id'); // ID автосервиса (СТО) из таблицы services
                $table->decimal('price', 12, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['standard_work_id', 'service_id'], 'work_service_unique');
                $table->foreign('standard_work_id')->references('id')->on('standard_works')->onDelete('cascade');
                $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_work_prices', 'service_id')) {
            Schema::table('service_work_prices', function (Blueprint $table) {
                $table->dropIndex('work_service_unique');
                $table->renameColumn('service_id', 'our_company_id');
                $table->unique(['standard_work_id', 'our_company_id'], 'work_service_unique');
            });
        }
    }
};