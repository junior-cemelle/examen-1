# Examen 1 – APIs de Servicios

Este repositorio contiene la implementación correspondiente al primer examen.  
El proyecto expone una serie de servicios accesibles desde el servidor mediante endpoints HTTP.

## URL Base

Los servicios estarán disponibles bajo la siguiente estructura:

http://{SERVER}/M[no_control]/examen-1/api/[NOMBRE_DEL_SERVICIO]

Donde:

- {SERVER} → Dirección del servidor donde está alojado el proyecto.
- M[no_control] → Carpeta correspondiente al número de control.
- [NOMBRE_DEL_SERVICIO] → Nombre del servicio que se desea consumir.

## Servicios Disponibles

### password

Servicio para generar contraseñas seguras.

Endpoint:
http://{SERVER}/M[no_control]/examen-1/api/password

Descripción:
Genera contraseñas seguras de forma automática.

---

### qr

Servicio para generar códigos QR.

Endpoint:
http://{SERVER}/M[no_control]/examen-1/api/qr

Descripción:
Genera códigos QR a partir de texto o enlaces proporcionados.

---

### short

Servicio para acortar URLs con estadísticas.

Endpoint:
http://{SERVER}/M[no_control]/examen-1/api/short

Descripción:
Permite acortar URLs y proporciona estadísticas sobre su uso.

## Notas

- Cada servicio funciona como un endpoint independiente dentro de la carpeta `api`.
- Los servicios pueden consumirse mediante solicitudes HTTP.



# Estructura de directorios

```
examen-1/
├── .htaccess                  ← Router raíz (enruta /api/* a cada servicio)
│
└── api/
    ├── password/              ← Servicio 1: generación de contraseñas
    │   ├── index.php          ← Endpoint principal (GET+POST /api/password, POST /api/passwords)
    │   ├── validate.php       ← POST /api/password/validate
    │   ├── GenPassword.php    ← Clase de generación criptográfica
    │   ├── PasswordService.php← Capa de servicio / orquestador
    │   ├── PasswordValidator.php ← Evaluador de fortaleza
    │   └── Response.php       ← Helper de respuestas JSON uniformes
    │
    ├── qr/                    ← Servicio 2: generación de QR (pendiente)
    │   └── ...
    │
    └── short/                 ← Servicio 3: acortador de URLs (pendiente)
        └── ...
```

---

## Endpoints — Servicio `password`

### 1. Generar una contraseña

```
GET /api/password?length=12&includeUppercase=true&includeLowercase=true&includeNumbers=true
```

```
POST /api/password
Content-Type: application/json

{
  "length": 12,
  "includeUppercase": true,
  "includeLowercase": true,
  "includeNumbers": true,
  "includeSymbols": false
}
```

**Parámetros**

| Parámetro        | Tipo    | Default | Descripción                        |
|------------------|---------|---------|------------------------------------|
| length           | int     | 16      | Longitud (4–128)                   |
| includeUppercase | bool    | true    | Incluir A-Z                        |
| includeLowercase | bool    | true    | Incluir a-z                        |
| includeNumbers   | bool    | true    | Incluir 0-9                        |
| includeSymbols   | bool    | false   | Incluir !@#$...                    |
| excludeAmbiguous | bool    | true    | Excluir Il1O0o                     |
| exclude          | string  | ""      | Caracteres adicionales a excluir   |

**Respuesta 200**
```json
{
  "success": true,
  "data": {
    "password": "kR7mNpX2wQ4v",
    "length": 12,
    "options": {
      "includeUppercase": true,
      "includeLowercase": true,
      "includeNumbers": true,
      "includeSymbols": false,
      "excludeAmbiguous": true,
      "customExclude": ""
    }
  }
}
```

---

### 2. Generar múltiples contraseñas

```
POST /api/passwords
Content-Type: application/json

{
  "count": 5,
  "length": 16,
  "includeSymbols": true,
  "excludeAmbiguous": true
}
```

**Respuesta 200**
```json
{
  "success": true,
  "data": {
    "passwords": ["...", "...", "...", "...", "..."],
    "count": 5,
    "length": 16,
    "options": { ... }
  }
}
```

---

### 3. Validar una contraseña

```
POST /api/password/validate
Content-Type: application/json

{
  "password": "MiContraseña123!",
  "requirements": {
    "minLength": 8,
    "requireUppercase": true,
    "requireNumbers": true,
    "requireSymbols": true
  }
}
```

**Respuesta 200 (válida) / 422 (no cumple requisitos)**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "strength": "strong",
    "score": 6,
    "checks": {
      "minLength": true,
      "maxLength": true,
      "hasUppercase": true,
      "hasNumbers": true,
      "hasSymbols": true
    },
    "suggestions": []
  }
}
```

---

### 4. Error handling

```
GET /api/password?length=1000
```

```json
{
  "success": false,
  "error": {
    "code": 400,
    "message": "El parámetro length debe estar entre 4 y 128."
  }
}
```


---

## Endpoints — Servicio `qr`

Genera códigos QR en formato PNG a partir de diferentes tipos de contenido.

> **Requisito:** La extensión GD de PHP debe estar habilitada en el servidor.  
> **Librería:** [phpqrcode](https://sourceforge.net/projects/phpqrcode/) — incluida en `api/qr/lib/phpqrcode/`

### Parámetros comunes

| Parámetro        | Tipo   | Default | Descripción                        |
|------------------|--------|---------|------------------------------------|
| size             | int    | 300     | Tamaño aproximado en px (100–1000) |
| errorCorrection  | string | M       | Nivel L / M / Q / H               |
| margin           | int    | 1       | Margen en módulos (0–10)           |
| json             | bool   | false   | Si `true`, devuelve base64 en JSON |

---

### 1. QR genérico

```
GET /api/qr?type=url&url=https://example.com&size=300
```

```
POST /api/qr/url
Content-Type: application/json

{
  "url": "https://example.com",
  "size": 300,
  "errorCorrection": "M"
}
```

---

### 2. QR de texto plano

```
POST /api/qr/text
Content-Type: application/json

{
  "text": "Hola mundo",
  "size": 300
}
```

---

### 3. QR de URL

```
POST /api/qr/url
Content-Type: application/json

{
  "url": "https://example.com",
  "size": 400,
  "errorCorrection": "H"
}
```

---

### 4. QR de red WiFi

```
POST /api/qr/wifi
Content-Type: application/json

{
  "ssid": "MiRedCasa",
  "password": "12345678",
  "encryption": "WPA",
  "size": 300
}
```

| Campo      | Valores aceptados      |
|------------|------------------------|
| encryption | `WPA`, `WEP`, `nopass` |

---

### 5. QR de geolocalización

```
POST /api/qr/geo
Content-Type: application/json

{
  "lat": 20.5937,
  "lng": -100.3921,
  "size": 300
}
```

---

### Respuestas

**Imagen directa** (default) — el servidor devuelve el PNG directamente:

```
Content-Type: image/png
```

**JSON con base64** — agrega `"json": true` al body o `?json=true` al query string:

```json
{
  "success": true,
  "data": {
    "format": "png",
    "mimeType": "image/png",
    "size": 300,
    "image": "iVBORw0KGgoAAAANSUhEUgAA..."
  }
}
```

---

## Errores HTTP usados

| Código | Significado                             |
|--------|-----------------------------------------|
| 200    | OK                                      |
| 400    | Parámetros inválidos                    |
| 404    | Ruta no encontrada                      |
| 405    | Método HTTP no permitido                |
| 422    | Contraseña no cumple requisitos         |
| 500    | Error interno                           |