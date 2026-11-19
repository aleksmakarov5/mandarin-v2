<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            if (!Schema::hasColumn('spare_parts', 'price_pzn')) {
                $table->decimal('price_pzn', 12, 2)->default(0)->after('price_ic');
            }
            if (!Schema::hasColumn('spare_parts', 'price_original')) {
                $table->decimal('price_original', 12, 2)->nullable()->after('price_pzn');
            }
            if (!Schema::hasColumn('spare_parts', 'price_analogue')) {
                $table->decimal('price_analogue', 12, 2)->nullable()->after('price_original');
            }
            if (!Schema::hasColumn('spare_parts', 'price_used')) {
                $table->decimal('price_used', 12, 2)->nullable()->after('price_analogue');
            }
            if (!Schema::hasColumn('spare_parts', 'price_sto')) {
                $table->decimal('price_sto', 12, 2)->nullable()->after('price_used');
            }
            if (!Schema::hasColumn('spare_parts', 'selected_mrk')) {
                $table->string('selected_mrk', 20)->nullable()->after('price_sto');
            }
            if (!Schema::hasColumn('spare_parts', 'order_date')) {
                $table->date('order_date')->nullable();
            }
            if (!Schema::hasColumn('spare_parts', 'delivery_date')) {
                $table->date('delivery_date')->nullable();
            }
            if (!Schema::hasColumn('spare_parts', 'is_manual')) {
                $table->boolean('is_manual')->default(true);
            }
            if (!Schema::hasColumn('spare_parts', 'is_small_parts')) {
                $table->boolean('is_small_parts')->default(false);
            }
            if (!Schema::hasColumn('spare_parts', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};