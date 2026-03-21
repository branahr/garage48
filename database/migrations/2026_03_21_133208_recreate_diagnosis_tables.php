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
        Schema::dropIfExists('diagnosis_questions');
        Schema::dropIfExists('diagnosis_sessions');

        Schema::create('diagnosis_sessions', function (Blueprint $table) {
            $table->id();
            $table->text('service_description');
            $table->json('diagnosis')->nullable();
            $table->json('final_result')->nullable();
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('status')->default('in_progress');
            $table->timestamps();
        });

        Schema::create('diagnosis_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnosis_session_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->string('question_key');
            $table->string('type')->default('single');
            $table->text('question');
            $table->text('intro_text')->nullable();
            $table->json('options');
            $table->json('answer')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnosis_questions');
        Schema::dropIfExists('diagnosis_sessions');
    }
};
