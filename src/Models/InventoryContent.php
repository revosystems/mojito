<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryContent extends Model
{
    protected $guarded  = [];
    protected $appends  = ["itemName", "estimatedDaysLeft"];
    protected $lastInventory;

    use SoftDeletes;
    use SaveNestedTrait;

    public function inventory()
    {
        return $this->belongsTo(config('mojito.inventoryClass', 'Inventory'));
    }

    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass', 'Item'));
    }

    public function getItemNameAttribute()
    {
        return nameOrDash($this->item);
    }

    public function getEstimatedDaysLeft()
    {
        return $this->quantity / ($this->consumedSinceLastInventory  /  $this->daysSinceLastInventory);
    }

    public function approve()
    {
        $this->calculateFields();
        $this->updateStock();
    }

    protected function calculateFields()
    {
        $inventoryClass             = config('mojito.inventoryClass');
        $lastInventory              = $this->getLastInventory($inventoryClass);
        $this->previousQuantity     = $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->quantity ?? 0 : 0;
        $this->stockCost            = $this->item->costPrice * $this->quantity;

        $stockClass                       = config('mojito.stockClass');
        $this->expectedQuantity           = $stockClass::findWith($this->item_id, $this->inventory->warehouse_id)->quantity;
        $this->variance                   = $this->quantity - $this->expectedQuantity;
        $this->stockDeficitCost           = $this->stockCost / $this->quantity * $this->variance;
        $this->consumedSinceLastInventory = 0;   // TODO : es la sum dels stock movements x el magatzem on la qty < 0 des de l'ultim inventari amb aquest producte, type add. * les unitats
        $this->consumptionCost            = $this->stockCost / $this->quantity * $this->consumedSinceLastInventory;
        $this->stockIn                    = 0;   // TODO: es la sum dels stock movements x el magatzem on la qty > 0 des de l'ultim inventari amb aquest producte, type add qty < 0 || type move toWarehouse == stockMovement->warehouse. * les unitats
        $this->estimatedDaysLeft          = $lastInventory ? $this->quantity / ($this->consumedSinceLastInventory / ($this->closed_at->diff($lastInventory->closed_at)->days)) : 0;
        $this->save();
    }

    protected function updateStock()
    {
        $stockClass                 = config('mojito.stockClass');
        $stock                      = $stockClass::findWith($this->item_id, $this->inventory->warehouse_id);
        if (! $stock) {
            return $stockClass::create(["warehouse_id" => $this->inventory->warehouse_id, "item_id" => $this->item_id, "quantity" => $this->quantity]);
        }
        return $stock->update(["quantity" => $this->quantity]);
    }

    protected function getLastInventory($inventoryClass)
    {
        if (! $this->lastInventory) {
            $this->lastInventory = $inventoryClass::approved()->where("warehouse_id", $this->inventory->warehouse_id)->orderBy('updated_at', 'desc')->first();
        }
        return $this->lastInventory;
    }
}
