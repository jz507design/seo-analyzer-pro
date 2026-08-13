# SEO Analyzer Pro — Actualizacion v2.0 (13 Ago 2026)

## Que se hizo
Mejora integral solicitada por el dueno: ampliar estadisticas tecnicas y rediseno tecnico/futurista
para usarlo como herramienta de venta (mostrarle a clientes las fallas de su web + mejoras).

## Backend (src/SEOAnalyzer.php) — de ~16KB a ~38KB
Nuevas categorias de analisis:
- **Rendimiento**: TTFB, version HTTP, compresion gzip/brotli, tamano HTML, scripts/CSS totales,
  render-blocking (JS sin defer/async + CSS sin media), peticiones estimadas, JS/CSS inline,
  imagenes lazy, imagenes sin dimensiones, formatos modernos (WebP/AVIF).
- **Seguridad**: HTTPS, HSTS, X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy,
  Permissions-Policy, Server header, X-Powered-By, cookies (Secure/HttpOnly/SameSite),
  mixed content, certificado SSL (sujeto, emisor, expiracion, dias restantes via openssl).
- **SEO avanzado**: JSON-LD (tipos detectados), Open Graph completo (5 tags requeridos),
  Twitter Cards, hreflang, favicon, apple-touch-icon, theme-color, generator.
  **Nuevo: sitemap.xml y robots.txt verificados server-side** (no solo en JS).
- **Accesibilidad**: enlaces sin texto, inputs sin label, iframes sin title, idioma.
- **Tecnologias**: deteccion de WordPress, Wix, Shopify, Squarespace, Webflow, React, Next.js,
  Vue, Angular, Bootstrap, Tailwind, jQuery, GSAP, GA, Meta Pixel, TikTok Pixel, Cloudflare,
  Laravel, Django, Font Awesome, Alpine.js, HTMX, Lodash, etc.
- Score penaliza por rendimiento/seguridad ademas de meta/estructura.

## Empaquetado: repositorio instalable + CLI/TUI + web local (misma sesion)
El dueno pidio: usar la herramienta desde terminal/TUI, desplegarla en web local, que este en un
repositorio, instalable en cualquier PC con un comando, y que la API key se ponga en el momento
sin guardarse nunca.

### Nuevos modulos (src/)
- **HttpFetch.php**: cliente HTTP compartido (gzip/deflate/brotli, TTFB, headers).
- **Database.php**: historial en SQLite (PDO, WAL), sin API key.
- **Crawler.php**: crawl multi-pagina (extrae enlaces internos, analiza hasta N paginas).
- **Comparador.php**: comparativa 2-3 URLs (tu web vs competidor) con ganador.
- **ReportPDF.php**: generador PDF en PHP puro (sin librerias), branding JZDS, multipagina.
- **ReportHTML.php**: reporte HTML autocontenido imprimible para email/entrega.

### CLI/TUI (seo-analyzer.php)
- Comandos: `analyze`, `compare`, `crawl`, `report` (--pdf/--html), `history`, `monitor`, `serve`, `tui`.
- API key por `--api-key`, env `DEEPSEEK_API_KEY` o prompt. **Nunca en disco.**
- `askApiKey()` no bloquea en stdin no interactivo (degrade elegante).

### Web local (`php seo-analyzer.php serve` -> http://127.0.0.1:8099/)
- `bin/router.php`: sirve estaticos + ejecuta `.php` de `/api/` (antes los servia crudos).
- Endpoints nuevos: `api/report.php` (PDF/HTML), `api/compare.php`, `api/crawl.php`,
  `api/download.php` (con proteccion path traversal).
- Front: **API key ya NO se persiste en localStorage** (solo en memoria/sesion).
- Botones nuevos: PDF, HTML, Crawl, Comparar (paneles dinamicos).

### Instaladores y docs
- `install.sh` (Linux/Mac: detecta/instala PHP via apt/brew/dnf) + `install.bat` (Windows).
- `README.md` con instrucciones completas. `data/` y configs locales gitignored.
- Git repo iniciado: `git init` + 2 commits (v2.0 base + fix stdin).

## Pruebas realizadas
- `php -l` limpio en todos los modulos y endpoints.
- CLI real: analyze example.com -> 44/100 (con issues y check sitemap/robots 404);
  crawl jzds.me -> 3 paginas score 87; compare jzds.me vs example.com -> 87 vs 44 con ganador.
- PDF validado estructuralmente: xref correcto, todos los offsets apuntan a objetos validos
  (reporte 11KB, comparativa 6KB). PDFs via web: firma %PDF-1.4, download 200.
- Web endpoints HTTP 200: analyze (score 44), report pdf, crawl, compare.
- Front: 243 divs balanceados, 99 IDs JS existen (2 son paneles dinamicos), DOM via Edge
  headless carga con botones nuevos.
- TUI: menu completo con analisis integrado (44 lineas, score + Adios).
- Monitor: UP HTTP 200 en 335 ms.

## Deploy
- `D:\DEV\tools\seo-analyzer-deploy\` actualizado previamente con v2.0 base.
- Para subir a cPanel: ver `GUIA_DESPLEGUE_CPanel.md`. Nota: los endpoints nuevos
  (report/compare/crawl/download) requieren subir tambien `src/ReportPDF.php`,
  `src/ReportHTML.php`, `src/Comparador.php`, `src/Crawler.php`, `src/HttpFetch.php`,
  `src/Database.php` y crear `data/reports/` con permisos de escritura.

## Pendiente / Notas
- DeepSeek API key NO configurada por defecto. Para demos con IA, el dueno ingresa su key
  en el momento (front o CLI). **Nunca se guarda.**
- Probar con PHP real en otra maquina (Linux) via `install.sh` cuando haya oportunidad.
- Opcional futuro: `monitor` con alertas por email; comparativa con mas de 3 URLs;
  panel admin multi-cliente (licencias).
- El sujeto SSL puede mostrar el cert por defecto del servidor (ej. cpcontacts.jzds.me en
  LiteSpeed) si el hosting no presenta SNI; los dias restantes siguen siendo validos.
- Servidor local:
  `Start-Process -FilePath "C:\php\php.exe" -ArgumentList "-S","127.0.0.1:8099","-t","D:\DEV\tools\seo-analyzer\public","D:\DEV\tools\seo-analyzer\bin\router.php" -WindowStyle Hidden`
  -> abrir http://127.0.0.1:8099/