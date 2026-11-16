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
            if (!Schema::hasColumn('our_companies', 'sign')) {
                $table->text('sign')->nullable()->after('id_legal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('our_companies', function (Blueprint $table) {
            if (Schema::hasColumn('our_companies', 'sign')) {
                $table->dropColumn('sign');
            }
        });
    }
};