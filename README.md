# Prsíčko ☔

Sčítání her v prší. Po každém kole platí poražený (zůstal s kartami) vítězi (zavřel):

```
platba = zaokrouhlit(body v ruce poraženého × koeficient) × násobek zavření
```

- **Body v ruce** – posuvník nebo pole (0–999)
- **Násobek** – ×1, ×2, ×3, ×4 (např. zavření červeným svrškem = ×4)
- **Koeficient platby** – v Nastavení, výchozí 0,1 (zaokrouhluje se na celé, 0,5 nahoru)

Příklad s koeficientem 0,1: 67 b → 6,7 → **7** × 2 = **14** · 64 b → 6,4 → **6** × 2 = **12**

Ostatní hráči u stolu se zapisují jen jako přihlížející – vypořádává se pouze vítěz s poraženým.

## Herní den

1. Na začátku večera **Začít herní den**
2. Zapisuješ jednotlivé hry – nahoře běží průběžný žebříček dne
3. Na konci **Ukončit herní den** – spočítá se žebříček dne (král / utopenec večera) a vyrovnání dne: dluhy mezi dvojicemi se vzájemně odečtou (A dluží B 92, B dluží A 40 → A dluží B 52)
4. Celkové dluhy za všechny dny jsou v záložce **Bilance**, kde se dají označit jako vyrovnané

Kronika ukazuje všechny herní dny s jejich žebříčkem; hry každého dne se rozbalí pod ním.

> Na GitHub Pages (bez PHP) běží aplikace v offline režimu – data se ukládají jen v prohlížeči. Pro sdílení mezi telefony je potřeba server s PHP (níže).

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

