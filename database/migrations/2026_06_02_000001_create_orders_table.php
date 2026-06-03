<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('out_trade_no')->unique();
            $table->string('body');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method');                  // khqr | card
            $table->string('status')->default('WAITING');
            $table->string('token')->nullable();       // gateway order token
            $table->json('cart')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
