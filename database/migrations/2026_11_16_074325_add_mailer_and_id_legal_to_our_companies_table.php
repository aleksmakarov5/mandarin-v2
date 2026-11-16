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
        Schema::table('our_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('our_companies', 'mailer')) {
                $table->string('mailer')->nullable()->after('email');
            }

            if (!Schema::hasColumn('our_companies', 'id_legal')) {
                $table->unsignedBigInteger('id_legal')->nullable()->after('mailer');

                // Если таблица legal_entities уже существует — вешаем внешний ключ
                if (Schema::hasTable('legal_entities')) {
                    $table->foreign('id_legal')
                        ->references('id')
                        ->on('legal_entities')
                        ->nullOnDelete();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('our_companies', function (Blueprint $table) {
            if (Schema::hasColumn('our_companies', 'id_legal')) {
                // Дропаем внешний ключ, если он был создан
                try {
                    $table->dropForeign(['id_legal']);
                } catch (\Throwable $e) {
                    // Игнорируем, если FK не был создан
                }
                $table->dropColumn('id_legal');
            }

            if (Schema::hasColumn('our_companies', 'mailer')) {
                $table->dropColumn('mailer');
            }
        });
    }
};