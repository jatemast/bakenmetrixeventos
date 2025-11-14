# Ejemplos de Postman para Autenticación API en Laravel

Aquí tienes los ejemplos para probar el sistema de autenticación API que hemos configurado con Laravel Sanctum.

## 1. Registro de Usuario (Register)

**URL:** `http://localhost:8000/api/register` (o la URL de tu aplicación Laravel)
**Método:** `POST`
**Headers:**
- `Accept`: `application/json`
- `Content-Type`: `application/json`

**Body (raw, JSON):**
```json
{
    "name": "Nombre de Usuario",
    "email": "usuario@example.com",
    "password": "password",
    "password_confirmation": "password"
}
```

**Respuesta esperada (201 Created):**
```json
{
    "message": "Registro exitoso",
    "access_token": "TU_TOKEN_DE_ACCESO",
    "token_type": "Bearer"
}
```

## 2. Inicio de Sesión de Usuario (Login)

**URL:** `http://localhost:8000/api/login` (o la URL de tu aplicación Laravel)
**Método:** `POST`
**Headers:**
- `Accept`: `application/json`
- `Content-Type`: `application/json`

**Body (raw, JSON):**
```json
{
    "email": "usuario@example.com",
    "password": "password"
}
```

**Respuesta esperada (200 OK):**
```json
{
    "message": "Inicio de sesión exitoso",
    "access_token": "TU_TOKEN_DE_ACCESO",
    "token_type": "Bearer"
}
```

## 3. Obtener Usuario Autenticado (Protected Route)

**URL:** `http://localhost:8000/api/user` (o la URL de tu aplicación Laravel)
**Método:** `GET`
**Headers:**
- `Accept`: `application/json`
- `Authorization`: `Bearer TU_TOKEN_DE_ACCESO` (Reemplaza `TU_TOKEN_DE_ACCESO` con el token obtenido en el registro o login)

**Respuesta esperada (200 OK):**
```json
{
    "id": 1,
    "name": "Nombre de Usuario",
    "email": "usuario@example.com",
    "email_verified_at": null,
    "created_at": "2023-10-27T10:00:00.000000Z",
    "updated_at": "2023-10-27T10:00:00.000000Z"
}
```

## 4. Cerrar Sesión de Usuario (Logout)

**URL:** `http://localhost:8000/api/logout` (o la URL de tu aplicación Laravel)
**Método:** `POST`
**Headers:**
- `Accept`: `application/json`
- `Authorization`: `Bearer TU_TOKEN_DE_ACCESO` (Usa el token actual del usuario)

**Respuesta esperada (200 OK):**
```json
{
    "message": "Sesión cerrada exitosamente"
}
```

**Nota:** Antes de probar, asegúrate de que tu servidor Laravel esté corriendo (por ejemplo, con `php artisan serve`).