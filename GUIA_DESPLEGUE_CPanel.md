# Guía de Despliegue - SEO Analyzer Pro en cPanel

## Requisitos Previos

- Hosting con cPanel activo
- PHP 8.0 o superior (verificar en cPanel > Select PHP Version)
- Dominio o subdominio configurado

---

## Paso 1: Preparar los Archivos

### 1.1 Verificar la estructura del proyecto

Tu proyecto debe tener esta estructura:

```
seo-analyzer/
├── public/
│   ├── api/
│   │   └── analyze.php
│   ├── .htaccess
│   └── index.html
├── src/
│   ├── SEOAnalyzer.php
│   └── DeepSeekAPI.php
├── config/
│   └── config.php
├── assets/
│   ├── css/
│   └── js/
└── README.md
```

### 1.2 Comprimir el proyecto

1. Ve a la carpeta `D:\DEV\tools\seo-analyzer\`
2. Selecciona **todos los archivos y carpetas**
3. Crea un archivo ZIP llamado `seo-analyzer.zip`

---

## Paso 2: Configurar Dominio en cPanel

### Opción A: Dominio Principal

Si quieres que la herramienta esté en tu dominio principal (ej: `tudominio.com`):

1. Inicia sesión en cPanel
2. Ve a **Administrador de Archivos** (File Manager)
3. Navega a `public_html`
4. Sube el archivo ZIP aquí

### Opción B: Subdominio (Recomendado)

1. Inicia sesión en cPanel
2. Ve a **Dominios** > **Subdominios**
3. Crea un subdominio, ejemplo: `seo.tudominio.com`
4. El directorio raíz será algo como `public_html/seo`
5. Sube el archivo ZIP en esa carpeta

### Opción C: Addon Domain

1. Inicia sesión en cPanel
2. Ve a **Dominios** > **Addon Domains**
3. Agrega tu dominio adicional
4. Sube el archivo ZIP en la carpeta asignada

---

## Paso 3: Subir y Extraer Archivos

### 3.1 Subir el ZIP

1. En cPanel, abre **Administrador de Archivos**
2. Navega al directorio de tu dominio/subdominio
3. Haz clic en **Cargar** (Upload)
4. Selecciona `seo-analyzer.zip`
5. Espera a que la carga termine (barra verde al 100%)

### 3.2 Extraer el ZIP

1. En el Administrador de Archivos, haz clic derecho sobre `seo-analyzer.zip`
2. Selecciona **Extract** (Extraer)
3. Confirma la extracción
4. Elimina el archivo ZIP después de extraer

### 3.3 Mover archivos al lugar correcto

**IMPORTANTE:** El contenido de la carpeta `public/` debe estar en la **raíz del dominio**.

Si subiste a `public_html/seo/`:

```
# Estructura INCORRECTA:
public_html/seo/
├── public/
│   ├── index.html
│   ├── api/
│   └── .htaccess
├── src/
├── config/
└── assets/

# Estructura CORRECTA (mover archivos):
public_html/seo/
├── index.html          <--contenido de public/
├── api/
├── .htaccess           <--contenido de public/
├── src/
├── config/
└── assets/
```

**Para mover:**
1. Entra a la carpeta `public/`
2. Selecciona todos los archivos (`index.html`, `api/`, `.htaccess`)
3. Haz clic en **Mover** (Move)
4. Mueve al directorio padre (ej: `public_html/seo/`)
5. Elimina la carpeta `public/` vacía

---

## Paso 4: Configurar PHP

### 4.1 Verificar versión de PHP

1. En cPanel, ve a **Select PHP Version** o **MultiPHP Manager**
2. Asegúrate de usar **PHP 8.0** o superior
3. Si no está disponible, contacta a tu hosting

### 4.2 Habilitar extensiones requeridas

En **Select PHP Version** > **Extensions**, verifica que estén activadas:

- ✅ `curl` (requerida para hacer peticiones HTTP)
- ✅ `mbstring` (manejo de strings multibyte)
- ✅ `json` (generalmente activada por defecto)
- ✅ `openssl` (para HTTPS)
- ✅ `dom` (para parsear HTML)
- ✅ `libxml` (para parsear XML)

### 4.3 Configurar php.ini (si es necesario)

Si tu hosting permite editar php.ini, ajusta:

```ini
max_execution_time = 120
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
allow_url_fopen = On
```

---

## Paso 5: Configurar .htaccess

El archivo `.htaccess` ya está incluido en `public/`. Si no es visible:

1. En el Administrador de Archivos, haz clic en **Configuración** (Settings)
2. Marca **Mostrar archivos ocultos (dotfiles)**
3. Verifica que `.htaccess` exista en la raíz del dominio

Contenido esperado de `.htaccess`:

```apache
RewriteEngine On
RewriteBase /

# Redirigir todo el tráfico a HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Proteger archivos sensibles
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Habilitar compresión
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>

# Cache para assets estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

---

## Paso 6: Configurar Permisos

En el Administrador de Archivos:

| Archivo/Carpeta | Permisos |
|-----------------|----------|
| Carpetas (`src/`, `config/`, `assets/`) | `755` |
| Archivos PHP (`.php`) | `644` |
| Archivos HTML (`.html`) | `644` |
| `.htaccess` | `644` |

**Para cambiar permisos:**
1. Clic derecho sobre el archivo/carpeta
2. Selecciona **Change Permissions** (Cambiar permisos)
3. Ajusta los valores
4. Guarda

---

## Paso 7: Verificar la Instalación

### 7.1 Acceder a la herramienta

Abre tu navegador y visita:
- Subdominio: `https://seo.tudominio.com`
- Dominio principal: `https://tudominio.com`
- Subcarpeta: `https://tudominio.com/seo/`

### 7.2 Checklist de verificación

- [ ] La página carga correctamente
- [ ] El diseño se ve bien (Tailwind CSS funciona)
- [ ] El modo oscuro/claro funciona
- [ ] Puedes ingresar una URL y analizar
- [ ] Los resultados se muestran correctamente
- [ ] El historial funciona
- [ ] La exportación JSON/TXT funciona
- [ ] El panel de logs muestra actividad

### 7.3 Probar la API directamente

Visita: `https://tudominio.com/api/analyze.php`

Deberías ver:
```json
{"error":"Método no permitido"}
```

Esto confirma que el endpoint está activo.

---

## Paso 8: Configurar API Key de DeepSeek

1. En la interfaz, haz clic en **API Key**
2. Ingresa tu clave de DeepSeek (obtenida en https://platform.deepseek.com/)
3. Haz clic en **Guardar**
4. Marca la casilla **Usar IA**
5. Realiza un análisis para verificar que funciona

---

## Solución de Problemas

### Error 404 - Página no encontrada

1. Verifica que `index.html` esté en la raíz del dominio
2. Revisa que `.htaccess` esté presente
3. Confirma que el dominio/subdominio apunta al directorio correcto

### Error 500 - Internal Server Error

1. Verifica la versión de PHP (debe ser 8.0+)
2. Revisa los permisos de archivos (PHP: 644, Carpetas: 755)
3. Habilita la visualización de errores temporalmente:
   - Agrega al inicio de `api/analyze.php`:
     ```php
     ini_set('display_errors', 1);
     error_reporting(E_ALL);
     ```
4. Revisa los logs de error en cPanel > **Errors**

### La API no responde

1. Verifica que `curl` esté habilitado en PHP
2. Asegúrate de que `allow_url_fopen = On` en php.ini
3. Algunos hostings bloquean peticiones HTTP salientes. Contacta soporte.

### Tailwind CSS no carga

- Tailwind se carga desde CDN (`https://cdn.tailwindcss.com`)
- Si no funciona, verifica que tu hosting no bloquee recursos externos
- Alternativa: compila Tailwind localmente y sube el CSS

### Chart.js no funciona

- Chart.js se carga desde CDN (`https://cdn.jsdelivr.net/npm/chart.js`)
- Mismo diagnóstico que Tailwind

---

## Estructura Final Esperada en el Hosting

```
public_html/ (o carpeta del subdominio)
├── .htaccess
├── index.html
├── api/
│   └── analyze.php
├── src/
│   ├── SEOAnalyzer.php
│   └── DeepSeekAPI.php
├── config/
│   └── config.php
└── assets/
    ├── css/
    └── js/
```

---

## Notas de Seguridad

1. **Protege el archivo config.php**: Agrega al `.htaccess`:
   ```apache
   <Files "config.php">
       Order allow,deny
       Deny from all
   </Files>
   ```

2. **No compartas tu API Key**: Se almacena solo en el navegador del usuario (localStorage)

3. **HTTPS obligatorio**: El `.htaccess` ya redirige a HTTPS automáticamente

4. **Actualiza regularmente**: Mantén PHP y las librerías actualizadas

---

## Contacto con Soporte del Hosting

Si tienes problemas, proporciona esta información a tu hosting:

```
Requisitos del proyecto:
- PHP 8.0+
- Extensiones: curl, mbstring, json, openssl, dom, libxml
- allow_url_fopen: On
- mod_rewrite: Activado
- mod_deflate: Activado (opcional)
- mod_expires: Activado (opcional)
```

---

**¡Listo!** Tu SEO Analyzer Pro debería estar funcionando correctamente.
