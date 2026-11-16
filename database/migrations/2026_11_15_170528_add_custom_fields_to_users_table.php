<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'user_group_id')) {
                $table->foreignId('user_group_id')->nullable()->after('phone')->constrained('user_groups')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('user_group_id')->constrained('cities')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'def_mrk_proc')) {
                $table->integer('def_mrk_proc')->nullable()->default(0)->after('city_id');
            }
            if (!Schema::hasColumn('users', 'def_zap_proc')) {
                $table->integer('def_zap_proc')->nullable()->default(0)->after('def_mrk_proc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_group_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['phone', 'user_group_id', 'city_id', 'def_mrk_proc', 'def_zap_proc']);
        });
    }
};