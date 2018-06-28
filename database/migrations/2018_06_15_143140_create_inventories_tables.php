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
            $table->tinyInteger('status')->unsigned()->default(\BadChoice\Mojito\Models\Inventory::STATUS_PENDING);

            $table->integer('employee_id')->unsigned();

            $table->integer('warehouse_id')->unsigned();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_contents', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('quantity', 8, 3);
            $table->decimal('expectedQuantity', 8, 3)->nullable();
            $table->decimal("previousQuantity", 8, 3)->default(0);
            $table->decimal("stockCost", 8, 3)->default(0);
            $table->decimal("variance", 8, 3)->default(0);
            $table->decimal("stockDeficitCost", 8, 3)->default(0);
            $table->decimal("consumedSinceLastInventory", 8, 3)->default(0);
            $table->decimal("stockConsumedByPOS", 8, 3)->default(0);
            $table->decimal("consumptionCost", 8, 3)->default(0);
            $table->decimal("stockIn", 8, 3)->default(0);
            $table->decimal("estimatedDaysLeft", 8, 3)->default(0);

//            $table->decimal("stockUsageEPOS", 8, 3);              // TODO: Ask for it
//            $table->decimal("GPPercent", 8, 3);                   // TODO: Ask for it

            $table->integer('item_id')->unsigned();
            $table->foreign('item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

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
        Schema::dropIfExists('inventory_contents');
        Schema::dropIfExists('inventories');
    }
}
