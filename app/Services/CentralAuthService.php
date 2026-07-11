<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Validates Metrix Central JWTs for protected Events Hub API routes.
 * Login / 2FA / logout are handled by the frontend calling Central directly.
 */
class CentralAuthService
{
    public function baseUrl(): string
    {
        return config('central.api_url');
    }

    public function me(string $bearerToken): array
    {
        return $this->unwrap($this->request('get', '/auth/me', [], $bearerToken));
    }

    public function assertEventsModuleAccess(array $user): void
    {
        $modules = $user['modules'] ?? [];

        if ($modules !== [] && ! in_array('events', $modules, true)) {
            throw new RuntimeException(
                'Tu cuenta no tiene acceso al módulo de eventos. Contacta al administrador de Central.',
                403
            );
        }
    }

    private function request(string $method, string $path, array $payload = [], ?string $bearerToken = null): Response
    {
        $client = Http::baseUrl($this->baseUrl())
            ->timeout(15)
            ->acceptJson()
            ->asJson();

        if ($bearerToken) {
            $client = $client->withToken($bearerToken);
        }

        try {
            /** @var Response $response */
            $response = $client->{$method}($path, $payload);
        } catch (ConnectionException) {
            throw new RuntimeException(
                'No se pudo conectar con Metrix Central.',
                502
            );
        }

        return $response;
    }

    private function unwrap(Response $response): array
    {
        if ($response->successful()) {
            $json = $response->json();

            if (is_array($json) && array_key_exists('data', $json)) {
                return is_array($json['data']) ? $json['data'] : ['data' => $json['data']];
            }

            return is_array($json) ? $json : [];
        }

        $json = $response->json();
        $message = is_array($json)
            ? ($json['message'] ?? $json['error']['message'] ?? 'Central authentication failed')
            : 'Central authentication failed';

        throw new RuntimeException((string) $message, $response->status());
    }
}
