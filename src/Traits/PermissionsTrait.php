<?php

namespace BadChoice\Mojito\Traits;

use Illuminate\Support\Facades\Schema;

trait PermissionsTrait
{
    public function permissionsCount()
    {
        return $this->getPermissions()->count();
    }

    public function activePermissions()
    {
        return $this->getPermissions()->sum(function($permission){
            return $this->$permission;
        });
    }

    public function getPermissions()
    {
        return collect(Schema::getColumnListing($this->getTableName()))->flip()
            ->except($this->excludedPermissionsArray)->keys();
    }
}