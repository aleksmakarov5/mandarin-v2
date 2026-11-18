<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'bg_color')) {
                $table->string('bg_color', 30)->default('#e0f2fe')->after('comment')->comment('Цвет фона бейджа СТО');
            }
            if (!Schema::hasColumn('services', 'text_color')) {
                $table->string('text_color', 30)->default('#0369a1')->after('bg_color')->comment('Цвет текста бейджа СТО');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'text_color']);
        });
    }
};