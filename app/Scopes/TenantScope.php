<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (app()->bound('tenant_id')) {
            $builder->where($model->getTable() . '.tenant_id', app('tenant_id'));
        }
    }
}
