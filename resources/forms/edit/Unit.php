<?php
    return [
        [
            ['field' => 'name'],
            ['field' => 'main_unit', 'select' => \BadChoice\Mojito\Models\Unit::getMainUnits() ],
            ['field' => 'conversion' ]
        ]
    ];
