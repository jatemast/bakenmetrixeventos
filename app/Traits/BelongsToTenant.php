<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    /**
     * Boot the trait and apply global scopes.
     */
    protected static function bootBelongsToTenant()
    {
        static::creating(function ($model) {
            if (!$model->tenant_id && app()->bound('tenant_id')) {
                $model->tenant_id = app('tenant_id');
            }
        });

        static::addGlobalScope(new \App\Scopes\TenantScope);
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
