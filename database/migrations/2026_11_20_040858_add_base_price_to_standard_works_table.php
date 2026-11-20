<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_works', function (Blueprint $table) {
            if (!Schema::hasColumn('standard_works', 'base_price')) {
                $table->decimal('base_price', 12, 2)->default(0.00)->after('default_norm_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('standard_works', function (Blueprint $table) {
            if (Schema::hasColumn('standard_works', 'base_price')) {
                $table->dropColumn('base_price');
            }
        });
    }
};