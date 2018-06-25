<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name')          ;
            $table->string('address')       ->nullable();
            $table->string('city')          ->nullable();
            $table->string('state')         ->nullable();
            $table->string('country')       ->nullable();
            $table->string('postalCode')    ->nullable();
            $table->string('nif')           ->nullable()->unique();
            $table->string('web')           ->nullable();
            $table->string('email')         ->nullable();
            $table->string('phone')         ->nullable();
            $table->string('notes')         ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_vendor', function (Blueprint $table) {
            $table->increments('id');

            $table->string('reference')->nullable();
            $table->integer('pack');
            $table->decimal('costPrice')->nullable();

            $table->integer('unit_id')->unsigned()->default(1);
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');

            $table->integer('item_id')->unsigned()->nullable();
            $table->foreign('item_id')->references('id')->on(config('mojito.itemsTable'))->onDelete('cascade');

            $table->integer('vendor_id')->unsigned()->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');

            $table->integer('tax_id')->unsigned()->nullable();
            $table->foreign('tax_id')->references('id')->on('taxes')->onDelete('set null');

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
        Schema::dropIfExists('item_vendor');
        Schema::dropIfExists('vendors');
    }
}
