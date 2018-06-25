<?php

namespace BadChoice\Mojito\Exceptions;

use Exception;

class AlreadyDeclinedException extends Exception
{
    public function __construct()
    {
        parent::__construct("Inventory already declined");
    }
}
