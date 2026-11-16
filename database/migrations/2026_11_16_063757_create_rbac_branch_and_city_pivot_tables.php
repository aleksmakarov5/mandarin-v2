<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Связь: Пользователь - Филиал - Роль (Сотрудник имеет конкретную роль в конкретном филиале)
        Schema::create('our_company_user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('our_company_id')->constrained('our_companies')->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Один пользователь в одном филиале не может дублировать одну и ту же роль
            $table->unique(['user_id', 'our_company_id', 'user_group_id'], 'user_company_role_unique');
        });

        // 2. Связь: Пользователь - Доступные города (многие-ко-многим)
        Schema::create('city_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'city_id']);
        });

        // 3. Флаг супер-админа в users (чтобы отделить глобального сисадмина от филиальных ролей)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_admin')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_system_admin');
        });
        Schema::dropIfExists('city_user');
        Schema::dropIfExists('our_company_user_role');
    }
};