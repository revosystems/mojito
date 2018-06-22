<?php

namespace BadChoice\Mojito\Exceptions;

use Exception;

class AlreadyApprovedException extends Exception
{
    public function __construct()
    {
        parent::__construct("Inventory already approved");
    }
}
