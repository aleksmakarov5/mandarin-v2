<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Таблица сервисов (СТО-партнеров)
        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Название СТО / автосервиса');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Ответственный сотрудник/аккаунт сервиса');
                $table->string('phone', 50)->nullable()->comment('Контактный телефон');
                $table->string('email')->nullable()->comment('Email сервиса');
                $table->string('address', 500)->nullable()->comment('Адрес сервиса');
                $table->text('comment')->nullable()->comment('Примечание, реквизиты');
                $table->boolean('is_active')->default(true)->comment('Активен ли сервис');
                $table->timestamps();
            });
        } else {
            Schema::table('services', function (Blueprint $table) {
                if (!Schema::hasColumn('services', 'user_id') && !Schema::hasColumn('services', 'id_user')) {
                    $table->foreignId('user_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('services', 'comment')) {
                    $table->string('comment')->nullable();
                }
                if (!Schema::hasColumn('services', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('comment');
                }
            });
        }

        // 2. Связующая таблица многие-ко-многим: Филиалы компании <-> Сервисы
        if (!Schema::hasTable('our_company_service')) {
            Schema::create('our_company_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->foreignId('our_company_id')->constrained('our_companies')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['service_id', 'our_company_id'], 'service_branch_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('our_company_service');
        Schema::dropIfExists('services');
    }
};