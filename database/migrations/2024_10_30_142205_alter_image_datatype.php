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
        Schema::table('users', function (Blueprint $table) {
            $table->longText('agent_logo')->change()->nullable();
        });
        Schema::table('banners', function (Blueprint $table) {
            $table->longText('image')->change();
        });
        Schema::table('banner_ads', function (Blueprint $table) {
            $table->longText('image')->change();
        });
        Schema::table('promotions', function (Blueprint $table) {
            $table->longText('image')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('agent_logo')->change();
        });
        Schema::table('banners', function (Blueprint $table) {
            $table->string('image')->change();
        });
        Schema::table('banner_ads', function (Blueprint $table) {
            $table->string('image')->change();
        });
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('image')->change();
        });
    }
};
