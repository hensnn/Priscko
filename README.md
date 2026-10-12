# ☔ Prsíčko

Absurdně deštivé počítadlo karetního **prší** mezi kamarády, stavěné hlavně pro mobil.
*Úřad pro evidenci deště karet – prší, nebo platíš.*

<p align="center"><img src="docs/nahled.png" alt="Náhled Prsíčka: herní den, zápis hry, výsledky dnů a dluhy" width="100%"></p>

- 🃏 **Herní den:** večer otevřeš den a zapisuješ hru za hrou. Formulář další hry je hned nahoře.
- 🎚️ **Rychlý zápis:** kdo zavřel, kdo zůstal s kartami, posuvník s body a násobek hned vedle. Velká částka se počítá živě.
- 💰 **Platba:** body v ruce poraženého × koeficient, zaokrouhlit, × násobek zavření. Vždy celé koruny.
- 👑 **Žebříček dne:** po ukončení dne král a utopenec večera a vyrovnání toho dne.
- 📅 **Bilance:** tabulka výsledků po dnech, celkový žebříček, kdo komu dluží a statistiky.
- 🤝 **Vyrovnání:** dluhy mezi dvojicí se vzájemně odečtou, zaplacené se jedním ťuknutím odškrtnou.
- 🎯 **Žetony u stolu:** hráč má celou přezdívku, u stolu jen krátkou zkratku – vždy jedinečnou.
- ✏️ **Opravy:** upravit jde úplně všechno – hry, data dnů, hráče, vyrovnání, násobky.
- 👥 **Sdílené pro všechny:** data leží na serveru, každý vidí totéž a stránka se sama obnovuje.
- 🔒 **Heslo pro zápis:** prohlížet může kdokoli, zapisovat jen ten, kdo zná heslo.

👉 **Vyzkoušet:** [hensnn.github.io/Priscko](https://hensnn.github.io/Priscko/) *(bez serveru – data jen ve tvém prohlížeči)*

---

## 🧮 Jak se počítá

### 💸 Platba za hru

Platí jen **poražený** (zůstal s kartami) **vítězi** (zavřel). Ostatní u stolu jen přihlížejí a moknou.

```
platba = zaokrouhlit(body v ruce × koeficient) × násobek zavření
```

| Koeficient | Hra | Výpočet | Platí |
|:---:|---|---|---:|
| 0,1 | 67 b, ×2 | 6,7 → 7 × 2 | **14 Kč** |
| 0,1 | 64 b, ×2 | 6,4 → 6 × 2 | **12 Kč** |
| 0,1 | 65 b, ×1 | 6,5 → 7 | **7 Kč** |
| 0,1 | 23 b, ×4 *(červený svršek)* | 2,3 → 2 × 4 | **8 Kč** |
| 1 | 67 b, ×2 | 67 × 2 | **134 Kč** |

- Zaokrouhluje se na **celé koruny**, 0,5 nahoru. Je-li násobek desetinný (např. ×1,5), zaokrouhlí se i výsledek.
- Koeficient (výchozí 0,1), měnu a násobky (výchozí ×1 obyčejně, ×2, ×3, ×4 červeným svrškem) nastavíš v **⚙️ Nastavení**. Násobky jde přidávat, přepisovat i mazat.
- Změna koeficientu platí jen pro nové hry. Už zapsané hry si pamatují koeficient, se kterým se hrály.

### 🃏 Herní den

1. Na začátku večera **🌧️ Začít herní den** (datum jde zvolit i zpětně).
2. Zapisuješ hry. Pod formulářem jsou hry dne a **průběžný žebříček**.
3. Úplně dole **🪑 Kdo dnes hraje** (kdo se nabízí u zápisu; když nevybereš nikoho, nabízejí se všichni) a rychlé **➕ Přisednout** pro nového hráče.
4. **🏁 Ukončit herní den** – spočítá se žebříček dne (👑 král večera, ☔ utopenec večera) a vyrovnání dne.

Poslední ukončený den jde znovu otevřít a dopsat hry (Bilance → ťukni na den → ↩️ Znovu otevřít den).

### 🤝 Vyrovnání

**Dluhy se počítají po dvojicích a vzájemně se odečtou:**

| Hra | Dluh |
|---|---|
| Kuba prohrál s Frantou | Kuba → Franta 14 Kč |
| Franta prohrál s Kubou | Franta → Kuba 12 Kč |
| **Po započtení** | **Kuba → Franta 2 Kč** |

- Nic se nepřevádí přes třetího hráče – každá dvojice se vyrovnává zvlášť.
- **Vyrovnání dne** (v herním dni a v podrobnostech dne) je přehled, kdo komu dluží za ten večer.
- **Bilance → Kdo komu dluží** je stav za **všechny dny dohromady** mínus to, co už se zaplatilo.
- **🤝 Vyrovnáno** zapíše platbu – celou částku nebo jen část. Omylem zapsanou platbu jde opravit nebo smazat.
- Placení **nemění žebříček** – pořadí se počítá jen z odehraných her.

### 🎯 Zkratky hráčů

Hráč má celou přezdívku, u stolu se ukazuje žetonem se zkratkou. Zkratka je **vždy jedinečná**:

| Přidaný hráč | Zkratka | Proč |
|---|:---:|---|
| Pepík | **Pe** | první dvě písmena |
| Petr | **Pt** | „Pe“ je obsazené → další písmeno |
| Pepa | **Pp** | „Pe“ je obsazené → další písmeno |
| Petr Novák | **Pn** | iniciály |

Při přidávání se zkratka vyplní sama a jde ji přepsat. Obsazenou zkratku aplikace nepovolí.

### ✏️ Opravy

S heslem jde upravit všechno, co se zapsalo:

| Co | Kde |
|---|---|
| hra – vítěz, poražený, kdo seděl u stolu, body, násobek, poznámka | **✏️ Upravit** u hry |
| datum herního dne | ✏️ v hlavičce dne nebo **📅 Změnit datum** v Bilanci |
| přezdívka a zkratka hráče | v řádku hráče v záložce **Hráči** |
| částka zaplaceného vyrovnání | **✏️ Částka** v Bilanci |
| koeficient, měna, násobky, heslo | **⚙️ Nastavení** |

Hráče s historií nejde smazat (rozbila by se bilance) – jde ho **poslat domů**, pak se u stolu nenabízí.

---

## 🚀 Instalace

Stačí jakýkoli webový server s **PHP 7.4+** (Caddy, nginx, Apache, sdílený webhosting…).

### Ručně

1. Nahraj `index.html` a `api.php` do jedné složky na webu.
2. Vedle nich vytvoř složku `data/` a dej webovému serveru (PHP) právo do ní zapisovat.
3. Doporučeno: zakaž přímý přístup ke složce `data/` (data se čtou jen přes `api.php`, je tam i otisk hesla).
4. Otevři stránku → **⚙️ Nastavení** → zvol heslo pro zápis.

> ⚠️ **Heslo nastav hned po instalaci.** Dokud není nastavené, zvolí ho ten, kdo stránku otevře první.

Hotovo. Data se ukládají do `data/prsicko.json`, otisk hesla do `data/auth.json`.

### Automaticky (Debian / Ubuntu + Caddy)

Pro server s Debianem nebo Ubuntu, na kterém už běží Caddy, je připravený skript. Spusť ho jako `root`:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/hensnn/Priscko/main/install-caddy.sh)
```

Skript nainstaluje PHP, stáhne aplikaci do `/var/www/prsicko` a přidá do `Caddyfile` **vlastní web na portu 8080** (původní `Caddyfile` předtím zazálohuje). Ostatní weby na serveru nechá být – Prsíčko tak může běžet vedle [Šachového Orákula](https://github.com/hensnn/sachove-orakulum).

Jiný port nebo složka:

```bash
PRSICKO_PORT=8090 PRSICKO_DIR=/var/www/prsi bash <(curl -fsSL https://raw.githubusercontent.com/hensnn/Priscko/main/install-caddy.sh)
```

HTTPS a doménu si vyřeš podle sebe, např. v Nginx Proxy Manageru přidej Proxy Host → `http://<IP serveru>:8080`.

**Aktualizace:** spusť stejný příkaz znovu. Data, heslo i `Caddyfile` zůstanou.

### Bez vlastního serveru (jen na vyzkoušení)

Bez PHP (např. na GitHub Pages) běží Prsíčko v **offline režimu**: všechno funguje, ale data i heslo se ukládají jen v prohlížeči, ve kterém ho máš otevřené. Sdílení mezi telefony potřebuje server s PHP.

---

## 💾 Záloha

Zkopíruj si `data/prsicko.json`, nebo ve stránce použij **⚙️ Nastavení → Stáhnout databázi**.
Při každém zápisu se navíc automaticky uloží předchozí verze do `data/prsicko.bak.json`.

Obnova: nahraj zálohu zpět jako `data/prsicko.json`.

---

## 📁 Soubory

| Soubor | K čemu je |
|---|---|
| `index.html` | celá aplikace (vzhled a logika) |
| `api.php` | ukládání dat na serveru, kontrola hesla, výpočty |
| `install-caddy.sh` | automatická instalace a aktualizace pro Debian + Caddy |
| `docs/nahled.png` | náhled do tohoto README |
