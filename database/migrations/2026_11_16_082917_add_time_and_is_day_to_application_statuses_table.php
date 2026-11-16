<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('application_statuses', 'time')) {
                // Длительность нормативного времени (число)
                $table->unsignedInteger('time')->nullable()->default(0)->after('color');
            }
            if (!Schema::hasColumn('application_statuses', 'is_day')) {
                // true = дни, false = часы
                $table->boolean('is_day')->default(false)->after('time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn(['time', 'is_day']);
        });
    }
};