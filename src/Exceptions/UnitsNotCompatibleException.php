<?php

namespace BadChoice\Mojito\Exceptions;

use Exception;

class UnitsNotCompatibleException extends Exception
{
    public function __construct()
    {
        parent::__construct(__("admin.unitsNotCompatible"));
    }
}
