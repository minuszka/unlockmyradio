<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_credit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 64)->index();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_credit_logs');
    }
};

