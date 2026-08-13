@echo off
REM SEO Analyzer Pro - Instalador Windows
REM Uso: install.bat  (ejecutar desde cmd, no PowerShell)

chcp 65001 >nul
echo ========================================
echo  SEO Analyzer Pro - Instalador
echo  JZ Design Solutions
echo ========================================
echo.

REM 1) Detectar PHP
where php >nul 2>nul
if %errorlevel% neq 0 (
  echo [!] PHP no detectado en PATH.
  echo     Opcion A: descarga PHP desde https://windows.php.net/download/
  echo     Opcion B: usa XAMPP y agrega C:\xampp\php al PATH.
  echo     Luego vuelve a ejecutar este instalador.
  pause
  exit /b 1
)

for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set PHPV=%%v
echo [OK] PHP %PHPV%

REM 2) Verificar extensiones
php -r "foreach(['pdo_sqlite','mbstring','openssl','dom'] as $e){if(!extension_loaded($e)){echo $e.' ';}}" > "%TEMP%\seo_missing.txt"
set /p MISSING=<"%TEMP%\seo_missing.txt"
if not "%MISSING%"=="" (
  echo [!] Faltan extensiones: %MISSING%
  echo     Edita php.ini y descomenta: extension=pdo_sqlite, extension=mbstring, extension=openssl, extension=dom
  pause
  exit /b 1
)
echo [OK] Extensiones requeridas presentes

REM 3) Crear data\
if not exist "%~dp0data\reports" mkdir "%~dp0data\reports"
echo [OK] Instalacion lista.
echo.
echo Uso:
echo   php seo-analyzer.php analyze https://tusitio.com
echo   php seo-analyzer.php serve      rem web local en http://127.0.0.1:8099
echo   php seo-analyzer.php tui        rem menu interactivo
echo.
echo Opcional: crea un alias en PowerShell:
echo   Set-Alias seo "php %~dp0seo-analyzer.php"
pause