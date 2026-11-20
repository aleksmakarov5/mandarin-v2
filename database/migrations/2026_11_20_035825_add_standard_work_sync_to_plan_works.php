<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plan_works', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_works', 'standard_work_id')) {
                $table->unsignedBigInteger('standard_work_id')->nullable()->after('application_id');
                $table->index('standard_work_id');
            }
            if (!Schema::hasColumn('plan_works', 'is_custom_price')) {
                // true = менеджер сам вручную переписал цену, автоматический пересчет не затирает её
                $table->boolean('is_custom_price')->default(false)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_works', function (Blueprint $table) {
            $table->dropColumn(['standard_work_id', 'is_custom_price']);
        });
    }
};