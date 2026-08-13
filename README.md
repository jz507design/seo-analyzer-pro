# SEO Analyzer Pro

Herramienta de auditoria SEO tecnica completa, desarrollada por **JZ Design Solutions**. Analiza rendimiento, seguridad, SEO on-page, accesibilidad y tecnologias de cualquier sitio web. Funciona 100% local: **terminal (CLI/TUI)** o **web local**. La API key de DeepSeek (IA) se ingresa en el momento y **nunca se guarda en disco**.

## Requisitos

- **PHP 8.1+** con extensiones: `pdo_sqlite`, `mbstring`, `openssl`, `dom`
- Sin dependencias externas (sin Composer, sin npm, sin librerias)

## Instalacion

### Windows
```cmd
install.bat
```
Si no tienes PHP: descargalo de https://windows.php.net/download/ y agregalo al PATH (o usa XAMPP y agrega `C:\xampp\php`).

### Linux / MacOS
```bash
bash install.sh
```
El instalador detecta e instala PHP automaticamente (apt/brew/dnf) si falta.

### Verificar instalacion
```bash
php seo-analyzer.php help
```

## Uso rapido

### Terminal (CLI)
```bash
# Auditoria basica
php seo-analyzer.php analyze https://tusitio.com

# Auditoria con analisis IA (pide la API key, no la guarda)
php seo-analyzer.php analyze https://tusitio.com --ai
php seo-analyzer.php analyze https://tusitio.com --ai --api-key sk-XXXX

# Salida JSON (para automatizar)
php seo-analyzer.php analyze https://tusitio.com --json

# Comparativa con competidores (2-3 URLs)
php seo-analyzer.php compare https://tusitio.com https://competidor.com
php seo-analyzer.php compare https://tusitio.com https://competidor.com --pdf --out comparativa.pdf

# Crawl completo del sitio (multi-pagina)
php seo-analyzer.php crawl https://tusitio.com --max-pages 10

# Reporte PDF/HTML para el cliente
php seo-analyzer.php report https://tusitio.com --pdf
php seo-analyzer.php report https://tusitio.com --html

# Historial (SQLite local)
php seo-analyzer.php history --limit 10

# Monitor de uptime
php seo-analyzer.php monitor https://tusitio.com --interval 60
```

### Menu interactivo (TUI)
```bash
php seo-analyzer.php tui
```

### Web local
```bash
php seo-analyzer.php serve
# Abre http://127.0.0.1:8099/
```
La web soporta: auditoria completa, analisis IA, **descarga de PDF/HTML con branding JZDS**, **crawl** y **comparativa** visual.

## API key

- La key se pasa por `--api-key` o variable de entorno `DEEPSEEK_API_KEY`, o se ingresa en el prompt.
- En la web se escribe en el formulario y solo se envia por POST al backend (en memoria).
- **Nunca** se escribe en archivos de configuracion, base de datos ni localStorage.

## Estructura

```
seo-analyzer/
├── seo-analyzer.php     # CLI/TUI principal
├── install.sh           # instalador Linux/Mac
├── install.bat          # instalador Windows
├── bin/router.php       # router del servidor web local
├── config/config.php    # config (sin API key)
├── src/
│   ├── SEOAnalyzer.php  # motor de auditoria
│   ├── HttpFetch.php    # cliente HTTP
│   ├── DeepSeekAPI.php  # integracion IA
│   ├── Database.php     # historial SQLite
│   ├── Crawler.php      # crawl multi-pagina
│   ├── Comparador.php   # comparativa
│   ├── ReportPDF.php    # generador PDF puro
│   └── ReportHTML.php   # reporte HTML
├── public/              # web local (index.html + api/)
└── data/                # sqlite + reportes (gitignored)
```

## Comandos disponibles

| Comando | Descripcion |
|---|---|
| `analyze <url>` | Auditoria completa |
| `compare <u1> <u2> [u3]` | Comparativa 2-3 sitios |
| `crawl <url>` | Crawl multi-pagina |
| `report <url> --pdf/--html` | Reporte para cliente |
| `history` | Historial SQLite |
| `monitor <url>` | Monitor de uptime |
| `serve` | Web local |
| `tui` | Menu interactivo |

---
**JZ Design Solutions** &middot; https://jzds.me &middot; contact@jzds.me &middot; +507 6070-0978