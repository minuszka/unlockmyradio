<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_api_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->string('key_prefix', 16)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_api_keys');
    }
};

