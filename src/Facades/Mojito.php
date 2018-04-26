<?php

namespace BadChoice\Mojito\Facades;

use Illuminate\Support\Facades\Facade;

class Mojito extends Facade
{
    protected static function getFacadeAccessor()
    {
        return "mojito";
    }
}
