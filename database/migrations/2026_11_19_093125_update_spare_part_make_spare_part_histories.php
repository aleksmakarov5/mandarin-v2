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
                // original, analogue, used, sto или null
                $table->string('selected_mrk', 20)->nullable()->after('price_sto');
            }
            if (!Schema::hasColumn('spare_parts', 'order_date')) {
                $table->date('order_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('spare_parts', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('order_date');
            }
            if (!Schema::hasColumn('spare_parts', 'is_manual')) {
                $table->boolean('is_manual')->default(true)->after('delivery_date');
            }
            if (!Schema::hasColumn('spare_parts', 'is_small_parts')) {
                $table->boolean('is_small_parts')->default(false)->after('is_manual');
            }
            if (!Schema::hasColumn('spare_parts', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Таблица истории изменений по запчастям
        if (!Schema::hasTable('spare_part_histories')) {
            Schema::create('spare_part_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action_type', 30); // created, updated, deleted, supplier_selected
                $table->string('field_name')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_histories');
    }
};