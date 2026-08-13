# SEO Analyzer Pro - Instalador PowerShell (estilo opencode)
# Uso:
#   irm https://raw.githubusercontent.com/jz507design/seo-analyzer-pro/main/install.ps1 | iex
# Instala el CLI y deja el comando `seo` disponible.
# Opcional: $env:SEO_INSTALL_DIR apunta a otra ubicacion (default $HOME\seo-analyzer).

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$Repo = "jz507design/seo-analyzer-pro"
$Branch = "main"
$InstallDir = if ($env:SEO_INSTALL_DIR) { $env:SEO_INSTALL_DIR } else { Join-Path $HOME "seo-analyzer" }

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "[OK] $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "[!] $msg" -ForegroundColor Yellow }

function Invoke-Git {
  param([string[]]$GitArgs)
  # git escribe progreso a stderr; en PS 5.1 eso genera ErrorRecord y con
  # ErrorActionPreference=Stop rompe el flujo. Forzamos Continue y usamos
  # $LASTEXITCODE como fuente de verdad.
  $prev = $ErrorActionPreference
  $ErrorActionPreference = "Continue"
  $out = & git @GitArgs 2>&1
  $code = $LASTEXITCODE
  $ErrorActionPreference = $prev
  if ($code -ne 0) {
    throw "git fallo (codigo $code): $($out -join ' ')"
  }
  return $out
}

Write-Host ""
Write-Host "  SEO Analyzer Pro - Instalador (JZ Design Solutions)" -ForegroundColor White
Write-Host "  https://github.com/$Repo" -ForegroundColor DarkGray
Write-Host ""

# 1) Detectar git
$git = Get-Command git -ErrorAction SilentlyContinue
if (-not $git) {
  Write-Warn "Git no detectado. Instalando git..."
  winget install --id Git.Git -e --accept-source-agreements --accept-package-agreements --silent 2>$null
  $git = Get-Command git -ErrorAction SilentlyContinue
  if (-not $git) {
    Write-Host "[ERROR] No se pudo instalar git. Instalalo desde https://git-scm.com/ y repite." -ForegroundColor Red
    return
  }
}
Write-Ok "Git: $($git.Source)"

# 2) Detectar PHP
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
  Write-Warn "PHP no detectado. Instalando PHP 8.3 via winget..."
  winget install --id PHP.PHP -e --accept-source-agreements --accept-package-agreements --silent 2>$null
  # winget de PHP no agrega al PATH de la sesion actual
  $phpCandidates = @(
    "$env:ProgramFiles\PHP",
    "$env:ProgramFiles\php",
    "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP*",
    "C:\php",
    "C:\tools\php"
  )
  foreach ($c in $phpCandidates) {
    $candidates = Get-ChildItem -Path $c -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($candidates) { $php = $candidates; break }
  }
  if (-not $php) {
    Write-Host "[ERROR] PHP no detectado. Descargalo de https://windows.php.net/download/ (thread-safe x64), agrega la carpeta al PATH y repite." -ForegroundColor Red
    return
  }
  Write-Ok "PHP encontrado: $($php.FullName)"
} else {
  Write-Ok "PHP: $($php.Source)"
}

$phpExe = if ($php.Path) { $php.Path } else { $php.FullName }

# 3) Verificar extensiones
Write-Step "Verificando extensiones PHP..."
$missing = & $phpExe -r "foreach(['pdo_sqlite','mbstring','openssl','dom'] as `$e){if(!extension_loaded(`$e)){echo `$e.' ';}}"
if ($missing) {
  Write-Host "[ERROR] Faltan extensiones: $missing. Descomentalas en php.ini (extension=pdo_sqlite, mbstring, openssl, dom) y repite." -ForegroundColor Red
  return
}
Write-Ok "Extensiones OK (pdo_sqlite, mbstring, openssl, dom)"

# 4) Descargar el codigo (clone ligero)
Write-Step "Descargando SEO Analyzer Pro a $InstallDir"
if (Test-Path (Join-Path $InstallDir "seo-analyzer.php")) {
  Write-Ok "Ya existe. Actualizando con git pull..."
  Invoke-Git @("-C", $InstallDir, "pull", "--ff-only") | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
} else {
  New-Item -ItemType Directory -Force -Path (Split-Path $InstallDir) | Out-Null
  Invoke-Git @("clone", "--depth", "1", "--branch", $Branch, "https://github.com/$Repo.git", $InstallDir) | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
}

# 5) Preparar data/ (sqlite + reportes)
$dataDir = Join-Path $InstallDir "data\reports"
New-Item -ItemType Directory -Force -Path $dataDir | Out-Null
Write-Ok "Carpeta de datos lista: $dataDir"

# 6) Definir funcion 'seo' para la sesion actual
function global:seo {
  param([Parameter(ValueFromRemainingArguments = $true)][string[]]$A)
  & $phpExe (Join-Path $InstallDir "seo-analyzer.php") @A
}

# 7) Persistir en el perfil de PowerShell
$profilePath = $PROFILE.CurrentUserAllHosts
$profileDir = Split-Path $profilePath
New-Item -ItemType Directory -Force -Path $profileDir | Out-Null
$seoBlock = @"

# SEO Analyzer Pro (instalado automaticamente)
function global:seo {
  param([Parameter(ValueFromRemainingArguments = `$true)][string[]]`$a)
  & "$phpExe" "$InstallDir\seo-analyzer.php" @`$a
}
"@
if (-not (Test-Path $profilePath)) { New-Item -ItemType File -Force -Path $profilePath | Out-Null }
# Get-Content -Raw devuelve $null en archivo vacio; $null -notmatch evalua a
# $null (falsy) y el bloque nunca se agregaria. Normalizar a string vacio.
$profileContent = [string](Get-Content $profilePath -Raw -ErrorAction SilentlyContinue)
if ($profileContent -notmatch "SEO Analyzer Pro") {
  Add-Content -Path $profilePath -Value $seoBlock
  Write-Ok "Alias 'seo' agregado a tu perfil PowerShell ($profilePath)"
} else {
  Write-Ok "Alias 'seo' ya estaba en tu perfil"
}

Write-Host ""
Write-Host "  Instalacion completada." -ForegroundColor Green
Write-Host "  Prueba ahora:  seo analyze https://tusitio.com" -ForegroundColor White
Write-Host "  O ya mismo en esta sesion:  seo help" -ForegroundColor DarkGray
Write-Host ""