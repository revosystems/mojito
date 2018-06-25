<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStocks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->increments('id');

            $table->decimal('quantity', 8, 3)         ->default(0);
            $table->decimal('defaultQuantity', 8, 3)  ->default(0);
            $table->integer('alert')->default(0);

            $table->integer('warehouse_id')->unsigned();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->integer('item_id')->unsigned();
            $table->foreign('item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

            $table->integer('unit_id')->unsigned()->default(1);
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('item_id')->unsigned();
            $table->foreign('item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

            $table->integer('from_warehouse_id')->unsigned()->nullable();
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->integer('to_warehouse_id')->unsigned();
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->decimal('quantity', 8, 3);

            $table->tinyInteger('action')   ->unsigned()->default(0);
            $table->tinyInteger('source')   ->unsigned()->default(0);

            $table->integer('user_id')->unsigned()->nullable();

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
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
}
