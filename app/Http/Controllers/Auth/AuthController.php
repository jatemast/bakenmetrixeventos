<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // DEBUG LOG (Standard Log already present)
        \Illuminate\Support\Facades\Log::debug('Register Attempt Data:', $request->all());

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:4'], // Bajar a 4 y quitar confirmed para prueba rápida
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Register Validation Errors:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $request->tenant_id;

        // If no tenant_id provided, create a new one automatically
        if (empty($tenantId)) {
            $tenant = \App\Models\Tenant::create([
                'name' => 'Universo de ' . $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name . '-' . uniqid()),
            ]);
            $tenantId = $tenant->id;
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'tenant_id' => $user->tenant_id,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        \Illuminate\Support\Facades\Log::info('Login Attempt for: ' . $request->email);

        // Caching user lookup for faster login (30 seconds)
        $cacheKey = 'user_login_' . md5($request->email);
        $user = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($request) {
            return User::withoutGlobalScopes()->where('email', $request->email)->first();
        });

        if (!$user) {
            \Illuminate\Support\Facades\Log::warning('Login Failed: User not found for ' . $request->email);
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            \Illuminate\Support\Facades\Log::warning('Login Failed: Password mismatch for ' . $request->email);
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'tenant_id' => $user->tenant_id,
            'tenant_name' => $user->tenant->name ?? null,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
}
