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
        Schema::table(config('mojito.itemsTable'), function (Blueprint $table) {
            $table->boolean('usesStockManagement')  ->default(0);
            $table->boolean('usesWeight')           ->default(0);

            $table->integer('unit_id')->unsigned()->default(1);
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
        Schema::table(config('mojito.itemsTable'), function (Blueprint $table) {
            $table->dropColumn('usesStockManagement');
            $table->dropColumn('usesWeight');

            $table->dropForeign(config('mojito.itemsTable').'_unit_id_foreign');
            $table->dropColumn('unit_id');
        });
    }
}
