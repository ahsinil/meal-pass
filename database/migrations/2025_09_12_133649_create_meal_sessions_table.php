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
        Schema::create('meal_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->integer('qty')->default(0);
            $table->string('image')->nullable();
            $table->unsignedBigInteger('officer_id ')->nullable();
            $table->unsignedBigInteger('meal_window_id');
            $table->foreign('officer_id ')->references('id')->on('users')->onDelete('set null');
            $table->foreign('meal_window_id')->references('id')->on('meal_windows')->onDelete('cascade');
            $table->integer('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_sessions');
    }
};
