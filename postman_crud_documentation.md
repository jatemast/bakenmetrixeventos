# Documentación de API para Personas y Mascotas (Postman)

Esta documentación describe cómo interactuar con la API de Personas y Mascotas utilizando Postman. Todas las rutas requieren autenticación con un token Sanctum.

## Endpoints de Personas

Es importante distinguir entre **obtener un recurso específico por su ID** y **filtrar una colección de recursos**.

*   **Obtener un recurso específico:** Si conoces el `ID` exacto de la persona que buscas, utiliza el endpoint `http://127.0.0.1:8000/api/personas/{id}`. Este método es para recuperar *una única persona*.
*   **Filtrar una colección:** Si deseas buscar personas que coincidan con ciertos criterios (como parte de un nombre, apellido o número de teléfono), utiliza el endpoint `http://127.0.0.1:8000/api/personas` junto con los parámetros de consulta de filtrado. Este método es para buscar dentro de *múltiples personas*.

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

### 1.1. Obtener Personas con Filtrado (GET)
- **URL:** `http://127.0.0.1:8000/api/personas`
- **Método:** `GET`
- **Headers:**
 - `Accept`: `application/json`
 - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Parámetros de Consulta (Query Parameters):** (Búsqueda insensible a mayúsculas y minúsculas)
 - `nombre` (opcional): Filtra por nombre (soporta `%like%`).
 - `apellido_paterno` (opcional): Filtra por apellido paterno (soporta `%like%`).
 - `apellido_materno` (opcional): Filtra por apellido materno (soporta `%like%`).
 - `numero_celular` (opcional): Filtra por número de celular (soporta `%like%`).
 - `numero_telefono` (opcional): Filtra por número de teléfono (soporta `%like%`).
- **Ejemplos de URL con filtrado:**
 - **Por nombre:** `http://127.0.0.1:8000/api/personas?nombre=rog` (funciona con "Rog", "ROG", "rogelio", etc.)
 - **Por apellido paterno:** `http://127.0.0.1:8000/api/personas?apellido_paterno=vaz`
 - **Por apellido materno:** `http://127.0.0.1:8000/api/personas?apellido_materno=mer`
 - **Por número de celular:** `http://127.0.0.1:8000/api/personas?numero_celular=55`
 - **Por número de teléfono:** `http://127.0.0.1:8000/api/personas?numero_telefono=442`
 - **Combinado (nombre y apellido paterno):** `http://127.0.0.1:8000/api/personas?nombre=rogelio&apellido_paterno=vazquez`
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
      "numero_celular": "5512345678",
      "numero_telefono": "4421234567",
      "created_at": "2025-11-17T...",
      "updated_at": "2025-11-17T..."
    }
  ]
  ```
- **Respuesta esperada (404 Not Found) si no hay registros:**
  ```json
  {
    "message": "Registros no encontrados"
  }
  ```

### 1.2. Búsqueda Global de Personas (GET)
- **URL:** `http://127.0.0.1:8000/api/personas`
- **Método:** `GET`
- **Headers:**
 - `Accept`: `application/json`
 - `Authorization`: `Bearer {{your_sanctum_token}}`
- **Parámetro de Consulta (Query Parameter):** (Búsqueda insensible a mayúsculas y minúsculas)
 - `search` (opcional): Permite buscar en los campos `nombre`, `apellido_paterno`, `apellido_materno`, `numero_celular`, `numero_telefono` y `id`. Si el valor es numérico, también buscará por `id`.
- **Consideraciones y Buenas Prácticas:**
 - **Insensibilidad a mayúsculas/minúsculas:** La búsqueda ahora ignora las diferencias entre mayúsculas y minúsculas.
 - **Eficiencia:** Para búsquedas más precisas y optimizadas, es preferible usar los filtros específicos (`nombre`, `apellido_paterno`, etc.) cuando se conoce el campo exacto a buscar.
 - **Flexibilidad:** El campo `search` es ideal para una búsqueda general tipo "barra de búsqueda", donde el usuario no especifica qué campo desea filtrar.
 - **Combinación:** Este campo de búsqueda global no se combinará con los filtros específicos (`nombre`, `apellido_paterno`, etc.). Si `search` está presente, los filtros específicos serán ignorados.
- **Ejemplos de URL con búsqueda global:**
 - **Buscar por nombre o ID:** `http://127.0.0.1:8000/api/personas?search=rogelio` (funciona con "Rogelio", "ROGELIO", "rogelio", "rog", etc.) o `http://127.0.0.1:8000/api/personas?search=1`
 - **Buscar por parte del apellido:** `http://127.0.0.1:8000/api/personas?search=vaz`
 - **Buscar por número de celular/teléfono:** `http://127.0.0.1:8000/api/personas?search=55123`
- **Respuesta esperada (200 OK):** La(s) persona(s) que coinciden con el término de búsqueda.
- **Respuesta esperada (404 Not Found) si no hay registros:**
  ```json
  {
    "message": "Registros no encontrados"
  }
  ```

**Nota sobre `http://127.0.0.1:8000/api/personas/{busqueda}`:**

Aunque técnicamente es posible crear una ruta como `http://127.0.0.1:8000/api/personas/{busqueda}` para una búsqueda global, no se considera una buena práctica por las siguientes razones:
*   **Conflicto de Rutas:** Esta ruta podría entrar en conflicto con la ruta existente para obtener una persona específica por su ID (`http://127.0.0.1:8000/api/personas/{id}`). Laravel podría intentar interpretar `{busqueda}` como un ID, lo que llevaría a un comportamiento impredecible o errores si el valor no es un ID válido.
*   **Claridad de la API:** Las URLs deben ser predecibles y describir el recurso que se está accediendo. Los parámetros de consulta (`?key=value`) son la forma estándar y más clara de aplicar filtros o búsquedas a una colección de recursos, sin alterar la identificación del recurso base.

Por estas razones, se recomienda encarecidamente utilizar el parámetro de consulta `search` (`http://127.0.0.1:8000/api/personas?search={busqueda}`) para realizar búsquedas globales, ya que es la forma estándar, flexible y sin conflictos de implementar esta funcionalidad en una API RESTful.

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