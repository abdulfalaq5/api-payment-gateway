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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->datetime('transaction_date');
            $table->string('order_id');
            $table->double('amount', 15, 2);
            $table->foreignId('status_transaction_id')->constrained('status_transactions');
            $table->string('description')->nullable();
            $table->integer('type_transaction')->default(1)->comment('1: deposit, 2: withdraw');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
