<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_hash')->unique();
            $table->string('store_access_token')->nullable();
            $table->unsignedBigInteger('store_user_id')->nullable();
            $table->string('store_domain')->nullable();
            $table->string('api_key')->nullable();
            $table->string('store_id')->nullable();
            $table->string('url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('webhook_id')->nullable();
            $table->string('js_file_version')->nullable();
            $table->string('js_file_uuid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
