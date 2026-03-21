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
        Schema::create('diagnosis_sessions', function (Blueprint $table) {
            $table->id();
            $table->text('service_description');
            $table->json('diagnosis')->nullable();
            $table->json('final_result')->nullable();
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('status')->default('in_progress');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnosis_sessions');
    }
};
