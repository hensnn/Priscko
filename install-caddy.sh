#!/usr/bin/env bash
# =====================================================================
#  Prsíčko – instalace na vlastní server s Caddy
#  Instaluje se vedle ostatních webů: vlastní složka a vlastní port
#  (výchozí /var/www/prsicko a 8080), ostatní weby nechá být.
#  Stejný příkaz slouží i k aktualizaci – data, heslo i Caddyfile zůstanou.
#  Spuštění (jako root):
#    bash <(curl -fsSL https://raw.githubusercontent.com/hensnn/Priscko/main/install-caddy.sh)
#  Jiný port nebo složka:
#    PRSICKO_PORT=8090 PRSICKO_DIR=/var/www/prsi bash <(curl -fsSL …)
# =====================================================================
set -euo pipefail

WEB="${PRSICKO_DIR:-/var/www/prsicko}"
PORT="${PRSICKO_PORT:-8080}"
REPO=https://raw.githubusercontent.com/hensnn/Priscko/main
MARK="# --- Prsíčko ---"

if [ "$(id -u)" -ne 0 ]; then echo "❌ Spusť mě jako root."; exit 1; fi
if ! command -v caddy >/dev/null 2>&1; then
  echo "❌ Caddy tu není. Nejdřív nainstaluj Caddy, pak spusť tenhle skript znovu."; exit 1
fi

echo "☔ 1/6 Instaluji PHP…"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-fpm php-cli php-mbstring curl >/dev/null
SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)
if [ -z "$SOCK" ]; then
  systemctl restart "$(systemctl list-unit-files 'php*-fpm.service' --no-legend | awk '{print $1}' | head -1)"
  sleep 2
  SOCK=$(ls /run/php/php*-fpm.sock | head -1)
fi
echo "   PHP běží na $SOCK"

echo "☔ 2/6 Stahuji Prsíčko do $WEB…"
mkdir -p "$WEB/data"
curl -fsSL "$REPO/index.html" -o "$WEB/index.html"
curl -fsSL "$REPO/api.php"    -o "$WEB/api.php"

echo "☔ 3/6 Připravuji úložiště dat…"
if [ -s "$WEB/data/prsicko.json" ]; then
  echo "   Data už existují – nechávám je být."
else
  echo "   Databáze se založí sama při prvním zápisu."
fi
chown -R www-data:www-data "$WEB/data"
chmod 750 "$WEB/data"

echo "☔ 4/6 Nastavuji Caddy (port $PORT)…"
CF=/etc/caddy/Caddyfile
touch "$CF"
if grep -qF "$MARK" "$CF"; then
  echo "   Caddyfile už Prsíčko obsahuje – nechávám ho být (aktualizace)."
else
  cp "$CF" "$CF.zaloha-$(date +%Y%m%d-%H%M%S)"
  cat >> "$CF" <<EOF

$MARK
# Prsíčko – vlastní web na portu $PORT (HTTPS a doménu řeší reverzní proxy)
:$PORT {
        root * $WEB

        # Databáze a heslo nikdo nestáhne napřímo, jen přes api.php
        respond /data/* 403

        # PHP pro api.php
        php_fastcgi unix/$SOCK

        file_server
}
EOF
  if ! caddy validate --config "$CF" --adapter caddyfile >/dev/null 2>&1; then
    echo "❌ Caddyfile po úpravě neprošel kontrolou. Vracím zálohu."
    cp "$(ls -t "$CF".zaloha-* | head -1)" "$CF"
    caddy validate --config "$CF" --adapter caddyfile || true
    exit 1
  fi
  echo "   Přidán blok :$PORT (původní Caddyfile zazálohován)."
fi
# Caddy musí smět mluvit s PHP (socket patří skupině www-data)
id caddy >/dev/null 2>&1 && usermod -aG www-data caddy
systemctl restart caddy

echo "☔ 5/6 Kontrola…"
sleep 1
if curl -fsS "http://127.0.0.1:$PORT/api.php" | grep -q '"players"'; then
  echo "   api.php odpovídá."
else
  echo "❌ api.php neodpovídá. Pošli výpis: journalctl -u caddy -n 30 --no-pager"
  exit 1
fi

echo "☔ 6/6 Hotovo!"
IP=$(hostname -I | awk '{print $1}')
echo
echo "✅ Prsíčko běží na http://$IP:$PORT/"
echo "   1) Otevři ho a HNED v ⚙️ Nastavení zvol heslo pro úpravy."
echo "      (Dokud heslo není, může ho zvolit kdokoli, kdo stránku otevře první.)"
echo "   2) Pro přístup zvenku nasměruj na http://$IP:$PORT svou reverzní proxy (HTTPS, doména)."
echo "   Data: $WEB/data/prsicko.json (zálohuj si ho občas)"
