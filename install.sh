#!/usr/bin/env bash
# SEO Analyzer Pro - Instalador Linux/MacOS
# Uso: bash install.sh
set -e

CYAN='\033[0;36m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN} SEO Analyzer Pro - Instalador${NC}"
echo -e "${CYAN} JZ Design Solutions${NC}"
echo -e "${CYAN}========================================${NC}"

# 1) Detectar PHP
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
  echo -e "${YELLOW}[!] PHP no detectado.${NC}"
  if command -v apt-get >/dev/null 2>&1; then
    echo "Instalando php-cli con apt..."; sudo apt-get update -qq && sudo apt-get install -y -qq php-cli php-sqlite3 php-mbstring
  elif command -v brew >/dev/null 2>&1; then
    echo "Instalando php con brew..."; brew install php
  elif command -v dnf >/dev/null 2>&1; then
    echo "Instalando php con dnf..."; sudo dnf install -y php-cli php-sqlite3 php-mbstring
  else
    echo -e "${RED}No se pudo instalar PHP automaticamente. Instalalo manualmente y vuelve a ejecutar.${NC}"
    exit 1
  fi
  PHP_BIN="$(command -v php)"
fi

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
echo -e "${GREEN}[OK] PHP $PHP_VERSION en $PHP_BIN${NC}"

# 2) Verificar extensiones
MISSING=$("$PHP_BIN" -r '
foreach (["pdo_sqlite","mbstring","openssl","dom"] as $ext) {
  if (!extension_loaded($ext)) echo $ext . " ";
}
')
if [ -n "$MISSING" ]; then
  echo -e "${RED}[!] Faltan extensiones: $MISSING${NC}"
  exit 1
fi
echo -e "${GREEN}[OK] Extensiones requeridas presentes${NC}"

# 3) Crear data/ y dar permisos
mkdir -p "$ROOT/data/reports"
chmod -R u+rw "$ROOT/data" 2>/dev/null || true

# 4) Permisos de ejecucion para el CLI
chmod +x "$ROOT/seo-analyzer.php" 2>/dev/null || true

# 5) Alias recomendado
echo -e "${GREEN}[OK] Instalacion lista.${NC}"
echo ""
echo -e "${CYAN}Uso:${NC}"
echo "  php seo-analyzer.php analyze https://tusitio.com"
echo "  php seo-analyzer.php serve      # web local en http://127.0.0.1:8099"
echo "  php seo-analyzer.php tui        # menu interactivo"
echo ""
echo -e "${CYAN}Opcional: agregar alias en ~/.bashrc${NC}"
echo "  alias seo='php $ROOT/seo-analyzer.php'"