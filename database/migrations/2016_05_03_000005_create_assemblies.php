<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssemblies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assemblies', function (Blueprint $table) {
            // auto increment id (primary key)
            $table->increments('id');
            $table->decimal('quantity', 8, 3)->default('1');

            $table->integer('main_item_id')->unsigned();
            $table->foreign('main_item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

            $table->integer('item_id')->unsigned();
            $table->foreign('item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

            $table->integer('unit_id')->unsigned()->default(1);
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');

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
        Schema::dropIfExists('assemblies');
    }
}
