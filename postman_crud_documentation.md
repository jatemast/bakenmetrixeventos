# Documentación de API para Personas y Mascotas (Postman)

Esta documentación describe cómo interactuar con la API de Personas y Mascotas utilizando Postman. Todas las rutas requieren autenticación con un token Sanctum.

## Endpoints de Personas

### 1. Obtener todas las Personas (GET)
- **URL:** `http://127.0.0.1:8000/api/personas`
- **Método:** `GET`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (200 OK):**
  ```json
  [
    {
      "id": 1,
      "nombre": "Rogelio",
      "apellido_paterno": "Vazquez",
      "apellido_materno": "Mercado",
      "edad": 38,
      "sexo": "H",
      "calle": "Panaderos",
      "numero_exterior": "350",
      "numero_interior": "NA",
      "colonia": "Las Flores",
      "codigo_postal": "56340",
      "municipio": "Corregidora",
      "estado": "Estado",
      "created_at": "2025-11-17T...",
      "updated_at": "2025-11-17T..."
    }
  ]
  ```

### 2. Crear una nueva Persona (POST)
- **URL:** `http://127.0.0.1:8000/api/personas`
- **Método:** `POST`
- **Headers:**
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Body (raw, JSON):**
  ```json
  {
    "nombre": "Rogelio",
    "apellido_paterno": "Vazquez",
    "apellido_materno": "Mercado",
    "edad": 38,
    "sexo": "H",
    "calle": "Panaderos",
    "numero_exterior": "350",
    "numero_interior": "NA",
    "colonia": "Las Flores",
    "codigo_postal": "56340",
    "municipio": "Corregidora",
    "estado": "Estado"
  }
  ```
- **Respuesta esperada (201 Created):** La persona creada.

### 3. Obtener una Persona específica (GET)
- **URL:** `http://127.0.0.1:8000/api/personas/{id}` (Reemplaza `{id}` con el ID de la persona)
- **Método:** `GET`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (200 OK):** La persona solicitada.

### 4. Actualizar una Persona (PUT/PATCH)
- **URL:** `http://127.0.0.1:8000/api/personas/{id}` (Reemplaza `{id}` con el ID de la persona)
- **Método:** `PUT` o `PATCH`
- **Headers:**
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Body (raw, JSON):**
  ```json
  {
    "edad": 39,
    "calle": "Nueva Calle"
  }
  ```
- **Respuesta esperada (200 OK):** La persona actualizada.

### 5. Eliminar una Persona (DELETE)
- **URL:** `http://127.0.0.1:8000/api/personas/{id}` (Reemplaza `{id}` con el ID de la persona)
- **Método:** `DELETE`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (204 No Content):** Sin contenido.

## Endpoints de Mascotas (Anidadas bajo Personas)

### 1. Obtener todas las Mascotas de una Persona (GET)
- **URL:** `http://127.0.0.1:8000/api/personas/{persona_id}/mascotas` (Reemplaza `{persona_id}` con el ID de la persona)
- **Método:** `GET`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (200 OK):**
  ```json
  [
    {
      "id": 1,
      "persona_id": 1,
      "reino_animal": "Perro",
      "edad": 3.5,
      "nombre": "Coronel",
      "created_at": "2025-11-17T...",
      "updated_at": "2025-11-17T..."
    }
  ]
  ```

### 2. Crear una nueva Mascota para una Persona (POST)
- **URL:** `http://127.0.0.1:8000/api/personas/{persona_id}/mascotas` (Reemplaza `{persona_id}` con el ID de la persona)
- **Método:** `POST`
- **Headers:**
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Body (raw, JSON):**
  ```json
  {
    "reino_animal": "Perro",
    "edad": 3.5,
    "nombre": "Coronel"
  }
  ```
- **Respuesta esperada (201 Created):** La mascota creada.

### 3. Obtener una Mascota específica de una Persona (GET)
- **URL:** `http://127.0.0.1:8000/api/personas/{persona_id}/mascotas/{mascota_id}` (Reemplaza `{persona_id}` y `{mascota_id}`)
- **Método:** `GET`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (200 OK):** La mascota solicitada.

### 4. Actualizar una Mascota de una Persona (PUT/PATCH)
- **URL:** `http://127.0.0.1:8000/api/personas/{persona_id}/mascotas/{mascota_id}` (Reemplaza `{persona_id}` y `{mascota_id}`)
- **Método:** `PUT` o `PATCH`
- **Headers:**
  - `Accept`: `application/json`
  - `Content-Type`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Body (raw, JSON):**
  ```json
  {
    "edad": 4.0,
    "nombre": "Coronel Jr."
  }
  ```
- **Respuesta esperada (200 OK):** La mascota actualizada.

### 5. Eliminar una Mascota de una Persona (DELETE)
- **URL:** `http://127.0.0.1:8000/api/personas/{persona_id}/mascotas/{mascota_id}` (Reemplaza `{persona_id}` y `{mascota_id}`)
- **Método:** `DELETE`
- **Headers:**
  - `Accept`: `application/json`
  - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Respuesta esperada (204 No Content):** Sin contenido.