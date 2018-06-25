<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWarehouses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            // auto increment id (primary key)
            $table->increments('id');
            $table->string('name');
            $table->integer('order')->default(0);

            // created_at, updated_at DATETIME
            $table->timestamps();
            $table->softDeletes();  //It is not really deleted, just marked as deleted
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('warehouses');
    }
}
