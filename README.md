# Prsíčko ☔

Sčítání her v prší. Po každém kole platí poražený (zůstal s kartami) vítězi (zavřel):

```
platba = zaokrouhlit(body v ruce poraženého × koeficient) × násobek zavření
```

- **Body v ruce** – posuvník nebo pole (0–999)
- **Násobek** – výchozí ×1, ×2, ×3, ×4 (např. zavření červeným svrškem = ×4); dají se měnit, přidávat i mazat v záložce **Nastavení** (i desetinné, např. ×1,5)
- **Koeficient platby** – v Nastavení, výchozí 0,1. Platba je vždy v **celých korunách** (0,5 nahoru): body × koeficient se zaokrouhlí, a pokud je násobek desetinný, zaokrouhlí se i výsledek. S koeficientem 1 se nic nezaokrouhluje (67 b × 2 = 134).

Příklad s koeficientem 0,1: 67 b → 6,7 → **7** × 2 = **14** · 64 b → 6,4 → **6** × 2 = **12**

Ostatní hráči u stolu se zapisují jen jako přihlížející – vypořádává se pouze vítěz s poraženým.

## Heslo pro úpravy

- Bez hesla je aplikace **jen pro čtení** – každý vidí hry naživo, žebříčky a bilanci.
- Heslo má vlastní kolonku v záložce **Nastavení** (tlačítko 🔒 v hlavičce tam přímo přepne):
  - poprvé (heslo ještě není) – rovnou si heslo zvolíš,
  - potom – zadáš heslo a telefon si ho zapamatuje,
  - když je odemčeno, je tam i zamknutí a změna hesla.
- Na serveru je uložené jen jako otisk v `data/auth.json` a ven se nikdy neposílá. Každý zápis server znovu ověří.
- **Heslo nastav hned po nasazení** – dokud není nastavené, může ho zvolit kdokoli, kdo stránku otevře první.


## Úpravy (s heslem)

Upravit jde všechno, co se zapsalo:

- **hra** – ✏️ Upravit u hry (v herním dni i v podrobnostech dne v Bilanci): vítěz, poražený, kdo seděl u stolu, body, násobek, poznámka. Částka se přepočítá s koeficientem, se kterým se hra hrála; hra dostane značku „upraveno“.
- **datum herního dne** – ✏️ v hlavičce běžícího dne, nebo 📅 Změnit datum v podrobnostech dne v Bilanci
- **hráč** – přezdívka i zkratka přímo v jeho řádku v záložce Hráči (zkratka se při přejmenování nemění)
- **zaplacené vyrovnání** – ✏️ Částka v Bilanci
- **koeficient, měna, násobky, heslo** – záložka Nastavení

## Herní den

1. Na začátku večera **Začít herní den**
2. Nahoře je rovnou formulář **Další hra**, pod ním hry dne a průběžný žebříček, úplně dole **Kdo dnes hraje** a ukončení dne
3. Na konci **Ukončit herní den** – spočítá se žebříček dne (král / utopenec večera) a vyrovnání dne: dluhy mezi dvojicemi se vzájemně odečtou (A dluží B 92, B dluží A 40 → A dluží B 52)
4. Záložka **Bilance**: nahoře tabulka **Výsledky herních dnů** (dny × hráči, vítěz dne zeleně s 👑, nejhorší červeně, dole součet a počet vyhraných dnů; **ťuknutím na den** se pod tabulkou rozbalí jeho hry, vyrovnání dne a případně „Znovu otevřít den“), pod ní **Celkový žebříček**, pak **Kdo komu dluží** (označení jako vyrovnané) a statistiky


> Na GitHub Pages (bez PHP) běží aplikace v offline režimu – data se ukládají jen v prohlížeči. Pro sdílení mezi telefony je potřeba server s PHP (níže).

Hráči se zadávají celou přezdívkou, u stolu se zobrazují jako žeton se zkratkou. Zkratka je **vždy jedinečná**:

- výchozí jsou první dvě písmena (Pepík → **Pe**)
- když už je obsazená, vezme se další písmeno jména (Petr → **Pt**, Pepa → **Pp**), u dvou slov iniciály (Petr Novák → **Pn**), jinak číslo (**P2**)
- při přidávání hráče je vedle přezdívky kolonka **Zkratka** – vyplní se sama, ale jde přepsat; u existujících hráčů jde zkratka přepsat v jejich řádku (1–3 znaky); obsazenou zkratku aplikace nepovolí

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

