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
        Schema::table('diagnosis_questions', function (Blueprint $table) {
            $table->string('step')->nullable()->after('diagnosis_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnosis_questions', function (Blueprint $table) {
            $table->dropColumn('step');
        });
    }
};
