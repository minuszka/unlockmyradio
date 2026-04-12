<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('brand', 50);
            $table->string('car_make', 50);
            $table->string('prefix', 10)->nullable();
            $table->string('serial', 40)->index();
            $table->unique(['serial', 'brand', 'car_make']);
            $table->string('code', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_codes');
    }
};

