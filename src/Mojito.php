<?php

namespace BadChoice\Mojito;

class Mojito
{
    public $warehouse;

    public function setWarehouse($warehouse)
    {
        $this->warehouse = $warehouse;
    }

    public function getWarehouse()
    {
        return $this->warehouse;
    }
}
