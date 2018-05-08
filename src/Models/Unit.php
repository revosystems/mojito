<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Mojito\Exceptions\UnitsNotCompatibleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $table    = "units";
    protected $guarded  = ['id'];

    const STANDARD  = 1;
    const KG        = 2;
    const L         = 3;
    const LBS       = 4;
    const GAL       = 5;

    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    //====================================================================
    // SCOPES
    //====================================================================
    public function scopeByMainUnit($query, $mainUnit)
    {
        $query->where('main_unit', '=', $mainUnit);
    }

    public static function unitFor($mainUnit, $conversion, $shouldCreate = false)
    {
        $unit = Unit::where('main_unit', '=', $mainUnit)->where('conversion', '=', $conversion)->first();
        if ($unit == null && $shouldCreate) {
            $unit = Unit::create(['main_unit' => $mainUnit, 'conversion' => $conversion , 'name' => $conversion . ' ' . Unit::getMainUnitName($mainUnit)]);
        }
        return $unit;
    }

    //====================================================================
    // METHODS
    //====================================================================
    public static function convert($qty, $origin, $destination)
    {
        if ($origin == null || $destination == null) {
            return $qty;
        }
        $origin         = is_numeric($origin) ? static::find($origin) : $origin;
        $destination    = is_numeric($destination) ? static::find($destination) : $destination;

        if (! static::areCompatible($origin, $destination)) {
            throw new UnitsNotCompatibleException;
        }

        $originMainQty = $qty * $origin     ->conversion;
        $finalQty      = $originMainQty / $destination->conversion;

        return $finalQty;
    }

    public function isCompatibleWith($destinationUnit)
    {
        return static::areCompatible($this, $destinationUnit);
    }

    public static function areCompatible($origin, $destination)
    {
        $origin         = is_numeric($origin) ? static::find($origin) : $origin;
        $destination    = is_numeric($destination) ? static::find($destination) : $destination;
        return $origin->main_unit == $destination->main_unit;
    }

    public function convertToMainUnit($qty)
    {
        return $qty * $this->conversion;
    }

    public function mainUnitName()
    {
        return static::getMainUnitName($this->main_unit);
    }

    public static function getMainUnits()
    {
        return [
            static::STANDARD    => static::getMainUnitName(static::STANDARD),
            static::KG          => static::getMainUnitName(static::KG),
            static::L           => static::getMainUnitName(static::L),
            static::LBS         => static::getMainUnitName(static::LBS),
            static::GAL         => static::getMainUnitName(static::GAL),
        ];
    }

    public static function getMainUnitName($mainUnit)
    {
        if ($mainUnit == static::STANDARD) {
            return trans_choice("admin.unit", 1);
        } elseif ($mainUnit == static::KG) {
            return "KG";
        } elseif ($mainUnit == static::L) {
            return "L";
        } elseif ($mainUnit == static::LBS) {
            return "LBS";
        } elseif ($mainUnit == static::GAL) {
            return "GAL";
        }
    }
}
