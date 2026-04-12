<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('serial', 40)->index();
            $table->string('email', 100);
            $table->string('stripe_payment_id', 100)->nullable();
            $table->string('stripe_session_id', 100)->nullable();
            $table->decimal('amount', 8, 2)->default(2.99);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('code_revealed', 20)->nullable();
            $table->string('brand', 50)->nullable();
            $table->string('car_make', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

