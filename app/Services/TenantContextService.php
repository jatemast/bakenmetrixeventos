<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Senior Tenant Context Service
 * Centralizes the multi-tenant isolation logic to prevent data leaks.
 */
class TenantContextService
{
    protected ?int $tenantId = null;

    /**
     * Set the current tenant ID from request or session
     */
    public function setTenantId(int $id): void
    {
        $this->tenantId = $id;
        app()->instance('tenant_id', $id);
        Log::debug("Tenant Context set to: {$id}");
    }

    /**
     * Get the current tenant ID
     */
    public function getTenantId(): ?int
    {
        try {
            return $this->tenantId ?? app('tenant_id') ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate if an object belongs to the current context
     */
    public function validateOwnership($model): bool
    {
        if (!$model || !isset($model->tenant_id)) {
            return false;
        }

        return (int)$model->tenant_id === (int)$this->getTenantId();
    }
}
