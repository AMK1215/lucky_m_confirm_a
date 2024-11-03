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
        Schema::create('seamless_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('message_id')->index();
            $table->string('product_id')->index();
            $table->timestamp('request_time');
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seamless_events');
    }
};