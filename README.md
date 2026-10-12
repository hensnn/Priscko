# Prsíčko ☔

Sčítání her v prší. Po každém kole platí poražený (zůstal s kartami) vítězi (zavřel):

```
platba = body v ruce poraženého × násobek zavření × cena bodu
```

- **Body v ruce** – posuvník nebo pole (0–999)
- **Násobek** – ×1, ×2, ×3, ×4 (např. zavření červeným svrškem = ×4)
- **Cena bodu** – v Nastavení, výchozí 1 Kč

Ostatní hráči u stolu se zapisují jen jako přihlížející – vypořádává se pouze vítěz s poraženým.

Hráči se zadávají celou přezdívkou, u stolu se zobrazují jako žeton s prvními dvěma písmeny (Pepík → **Pe**).

## Soubory

| Soubor | Co dělá |
|---|---|
| `index.html` | celá aplikace (vzhled + logika) |
| `api.php` | čte a zapisuje databázi |
| `data/prsicko.json` | databáze – vytvoří se sama při prvním zápisu |
| `data/prsicko.bak.json` | automatická záloha předchozí verze |

Když `api.php` neběží (např. otevřeš `index.html` jen jako soubor), aplikace přepne do **offline režimu** a ukládá do prohlížeče.

## Nasazení na Caddy LXC (krok za krokem)

**1. Nainstaluj PHP** (v konzoli Caddy kontejneru):

```bash
apt update && apt install -y php-fpm
ls /run/php/
```

Poslední příkaz vypíše název socketu, např. `php8.2-fpm.sock` (Debian 12) nebo `php8.4-fpm.sock` (Debian 13). Ten budeš potřebovat v kroku 3.

**2. Nahraj soubory** do `/var/www/html/prsicko/` a povol zápis do složky data:

```bash
mkdir -p /var/www/html/prsicko/data
# sem nakopíruj index.html a api.php
chown -R www-data:www-data /var/www/html/prsicko/data
```

**3. Uprav Caddyfile** (`/etc/caddy/Caddyfile`) – do bloku webu přidej:

```
# Prsíčko – PHP pro api.php (číslo verze podle kroku 1)
php_fastcgi unix//run/php/php8.2-fpm.sock

# Databázi nepouštět ven napřímo
@prsidata path /prsicko/data/*
respond @prsidata 403
```

Pak `systemctl reload caddy`.

**4. Otevři** `http://<adresa-serveru>/prsicko/` – dole v patičce musí být „sdíleno přes server“.

Pokud chceš vlastní adresu (např. `prsi.duckdns.org`), přidej v Nginx Proxy Manageru nový Proxy Host stejně jako u šachů a v Caddy na něj nasměruj kořen `/var/www/html/prsicko`.

## Příznaky kola

Sedmičková smršť 7️⃣ · Eso stojí 🛑 · Svršek na konec 🔀 · Plná ruka 🤲 · Bleskovka ⚡ · Nekonečný liják 🌧️ · Podezření z podvodu 🕵️ · Polito pivem 🍺 · Usnul u stolu 😴 · Kecal do karet 🗣️ · Odveta 🔁 · Zázrak 🦄

Další se přidávají v `index.html` v poli `FLAGS`.
