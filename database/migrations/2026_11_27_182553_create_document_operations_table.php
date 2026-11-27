<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_operations', function (Blueprint $table) {
            $table->id();

            $table->uuid('request_key')->unique();

            $table->unsignedBigInteger('application_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->string('action', 40);
            $table->char('request_hash', 64);

            $table->json('result');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_operations');
    }
};