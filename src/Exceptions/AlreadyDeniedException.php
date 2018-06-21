<?php

namespace BadChoice\Mojito\Exceptions;

use Exception;

class AlreadyDeniedException extends Exception
{
    public function __construct()
    {
        parent::__construct("Inventory already denied");
    }
}
