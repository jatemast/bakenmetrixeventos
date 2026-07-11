<?php

namespace App\Support;

use RuntimeException;

/**
 * Request-scoped Central account id (cuenta_id) for multi-tenant Hub queries.
 */
class CuentaContext
{
    private static ?int $cuentaId = null;

    public static function set(?int $cuentaId): void
    {
        self::$cuentaId = $cuentaId;
    }

    public static function id(): ?int
    {
        return self::$cuentaId;
    }

    public static function require(): int
    {
        $id = self::$cuentaId;

        if ($id === null) {
            throw new RuntimeException('Tenant context (cuenta_id) is not set.', 403);
        }

        return $id;
    }

    public static function clear(): void
    {
        self::$cuentaId = null;
    }
}
