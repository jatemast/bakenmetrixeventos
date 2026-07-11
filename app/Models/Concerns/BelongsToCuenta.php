<?php

namespace App\Models\Concerns;

use App\Support\CuentaContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCuenta
{
    protected static function bootBelongsToCuenta(): void
    {
        static::addGlobalScope('cuenta', function (Builder $builder): void {
            $cuentaId = CuentaContext::id();

            if ($cuentaId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('cuenta_id'),
                $cuentaId
            );
        });

        static::creating(function ($model): void {
            if ($model->cuenta_id !== null) {
                return;
            }

            $cuentaId = CuentaContext::id();

            if ($cuentaId !== null) {
                $model->cuenta_id = $cuentaId;
            }
        });
    }
}
