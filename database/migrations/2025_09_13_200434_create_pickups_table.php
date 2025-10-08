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
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('officer_id')->nullable();
            $table->foreign('officer_id')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('picked_by')->nullable();
            $table->foreign('picked_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('meal_session_id')->nullable();
            $table->foreign('meal_session_id')->references('id')->on('meal_sessions')->onDelete('set null');
            $table->datetime('picked_at');
            $table->string('method');
            $table->boolean('overriden')->default(false);
            $table->string('overriden_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};
