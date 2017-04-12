<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDartsliveCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dartslive_cards', function (Blueprint $table) {
            $table->increments('id');
            $table->string('card_id')->unique();
            $table->string('password');
            $table->string('name')->default('Undefined');
            $table->string('rating')->default(0);
            $table->string('coin')->default(0);
            $table->string('line_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dartslive_cards');
    }
}
