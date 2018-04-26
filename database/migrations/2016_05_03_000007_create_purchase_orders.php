<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchaseOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('vendor_order_id')->nullable();

            $table->decimal('subtotal', 8, 2) ->default(0);
            $table->decimal('tax', 8, 2) ->default(0);
            $table->decimal('total', 8, 2) ->default(0);

            $table->tinyInteger('status')   ->unsigned()->default(0);

            $table->integer('vendor_id')->unsigned()->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_contents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status');
            $table->decimal('quantity', 8, 3);
            $table->integer('received')->default(0);

            $table->decimal('price', 8, 2);
            $table->decimal('subtotal', 8, 2);
            $table->decimal('tax', 8, 2);
            $table->decimal('total', 8, 2);

            $table->integer('order_id')->unsigned()->nullable();
            $table->foreign('order_id')->references('id')->on('purchase_orders')->onDelete('cascade');

            $table->integer('item_vendor_id')->unsigned()->nullable();
            $table->foreign('item_vendor_id')->references('id')->on('item_vendor')->onDelete('cascade');

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
        Schema::dropIfExists('purchase_order_contents');
        Schema::dropIfExists('purchase_orders');
    }
}
