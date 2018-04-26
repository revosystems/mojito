<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use BadChoice\Mojito\Models\Unit;

class CreateUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name');
            $table->integer('main_unit')->unsigned();
            $table->decimal('conversion', 8, 3)->default(1);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('units')->delete();
        Unit::insert([
            ['name' => Unit::getMainUnitName(Unit::STANDARD),   'main_unit' => Unit::STANDARD,  'conversion' => 1],
            ['name' => Unit::getMainUnitName(Unit::KG),         'main_unit' => Unit::KG,        'conversion' => 1],
            ['name' => Unit::getMainUnitName(Unit::L),          'main_unit' => Unit::L,         'conversion' => 1],
            ['name' => Unit::getMainUnitName(Unit::LBS),        'main_unit' => Unit::LBS,       'conversion' => 1],
            ['name' => Unit::getMainUnitName(Unit::GAL),        'main_unit' => Unit::GAL,       'conversion' => 1],
            ['name' => 'g',                                     'main_unit' => Unit::KG,        'conversion' => 0.001],
            ['name' => 'cl',                                    'main_unit' => Unit::L,         'conversion' => 0.010],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('units');
    }
}
