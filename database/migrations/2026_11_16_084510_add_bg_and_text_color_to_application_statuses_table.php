<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('application_statuses', 'bg_color')) {
                $table->string('bg_color', 30)->default('#e2e8f0')->after('color');
            }
            if (!Schema::hasColumn('application_statuses', 'text_color')) {
                $table->string('text_color', 30)->default('#1e293b')->after('bg_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'text_color']);
        });
    }
};