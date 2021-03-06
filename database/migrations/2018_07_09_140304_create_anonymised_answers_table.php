<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnonymisedAnswersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('anonymised_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinic_id', 'clinics');
            $table->foreignUuid('question_id', 'questions');
            $table->json('answer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('anonymised_answers');
    }
}
