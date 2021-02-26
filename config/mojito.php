<?php

return [
    "itemClass"             => "\\MenuItem",
    "warehouseClass"        => "\\Warehouse",
    "stockClass"            => "\\Stock",
    "stockMovementClass"    => "\\StockMovement",
    "employeeClass"         => "\\TenantUser",
    "taxClass"       	    => "\\Tax",
    "inventoryClass"        => "\\Inventory",
    "inventoryContentClass" => "\\InventoryContent",
    "vendorClass"           => "\\Vendor",
    "vendorItemClass"       => "\\VendorItemPivot",
    "assemblyClass"         => "\\Assembly",

    "stocksTable"           => "stocks",
    "assembliesTable"       => "assemblies",
    "itemsTable"            => "products",
    "vendorItemsTable"      => "item_vendor",

    "usesStockManagementKey"=> "usesStockManagement",
];
