<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInventoriesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('closed_at')->nullable();
            $table->tinyInteger('status')->unsigned()->default(1);

            $table->integer('warehouse_id')->unsigned();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_contents', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('stock', 8, 3);
            $table->decimal("previousManagerStock", 8, 3)->default(0);
            $table->decimal('expectedStock', 8, 3)->nullable();
            $table->string("itemName", 8, 3)->default("");
            $table->decimal("previousAuditStock", 8, 3)->default(0);   // TODO: Ask for it
            $table->decimal("stockCost", 8, 3)->default(0);
            $table->decimal("surplusDeficitCost", 8, 3)->default(0);    // TODO: Ask for it   stockCost/stock*variance
            $table->decimal("maxRetailPrice", 8, 3)->default(0);        // TODO: Ask for it
            $table->decimal("stockConsumed", 8, 3)->default(0);
//            $table->decimal("stockIn", 8, 3);             // TODO: Ask for it
//            $table->decimal("stockUsageEPOS", 8, 3);      // TODO: Ask for it
//            $table->decimal("salesRetailGross", 8, 3);    // TODO: Ask for it
//            $table->decimal("GPPercent", 8, 3);           // TODO: Ask for it
//            $table->decimal("EstDaysStock", 8, 3);        // TODO: Ask for it

            $table->integer('item_id')->unsigned();
            $table->foreign('item_id')->references('id')->on('menu_items')->onDelete('cascade');

            $table->integer('unit_id')->unsigned()->default(1);
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');

            $table->integer('inventory_id')->unsigned();
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventories');
    }
}
