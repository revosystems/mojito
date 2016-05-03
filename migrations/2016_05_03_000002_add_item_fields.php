<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddItemFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menu_items',function(Blueprint $table){
            $table->boolean('usesStockManagement')  ->default(0);
            $table->boolean('usesWeight')           ->default(0);

            $table->integer('unit_id')->unsigned();
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menu_items',function(Blueprint $table){
            $table->dropColumn('usesStockManagement');
            $table->dropColumn('usesWeight');

            $table->dropForeign('menu_items_unit_id_foreign');
            $table->dropColumn('unit_id');
        });
    }
}
