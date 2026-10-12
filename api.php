<?php
// Prsíčko – miniaturní API nad JSON souborem.
// GET  api.php            -> vrátí celou databázi
// POST api.php {action..} -> provede operaci a vrátí novou databázi
// Zápis je chráněný zámkem (flock), takže dva telefony najednou nic nerozbijí.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dir  = __DIR__ . '/data';
$file = $dir . '/prsicko.json';
$authFile = $dir . '/auth.json';   // hash hesla pro úpravy – nikdy se neposílá ven

function default_mults() {
    return [
        ['m' => 1, 't' => 'obyčejně'],
        ['m' => 2, 't' => 'dvojitě'],
        ['m' => 3, 't' => 'trojitě'],
        ['m' => 4, 't' => 'červeným svrškem'],
    ];
}

function empty_db() {
    return [
        'players'     => [],
        // herní dny: {id, date: "YYYY-MM-DD", startTs, closedTs|null}; otevřený může být jen jeden
        'sessions'    => [],
        'games'       => [],
        'settlements' => [],
        // pointValue = koeficient platby; platba = round(body × koeficient) × násobek, vždy celé koruny
        // mults = násobky zavření, upravitelné v Nastavení
        'settings'    => ['pointValue' => 0.1, 'currency' => 'Kč', 'mults' => default_mults()],
        'rev'         => 0,
    ];
}

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- zkratky hráčů (žeton u stolu) – vždy jedinečné ---
function mk_abbr($a, $b = '') { return mb_strtoupper($a) . mb_strtolower($b); }
function base_abbr($name) {
    $ch = preg_split('//u', preg_replace('/\s+/u', '', $name), -1, PREG_SPLIT_NO_EMPTY);
    return $ch ? mk_abbr($ch[0], $ch[1] ?? '') : '?';
}
function abbr_of($p) { return !empty($p['abbr']) ? $p['abbr'] : base_abbr($p['name']); }
function taken_abbrs($players, $exceptId = null) {
    $t = [];
    foreach ($players as $p) if ($p['id'] !== $exceptId) $t[mb_strtolower(abbr_of($p))] = $p['name'];
    return $t;
}
// Pepík = Pe; Petr → Pe obsazeno → Pt; Petr Novák → PN; jinak P2, P3…
function unique_abbr($name, $taken) {
    $ch = preg_split('//u', preg_replace('/\s+/u', '', $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$ch) return '?';
    $cand = [mk_abbr($ch[0], $ch[1] ?? '')];
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > 1) $cand[] = mk_abbr(mb_substr($words[0], 0, 1), mb_substr($words[1], 0, 1));
    for ($i = 2; $i < count($ch); $i++) $cand[] = mk_abbr($ch[0], $ch[$i]);
    for ($n = 2; $n <= 99; $n++) $cand[] = mk_abbr($ch[0]) . $n;
    foreach ($cand as $c) if (!isset($taken[mb_strtolower($c)])) return $c;
    return mk_abbr($ch[0]) . substr(uid(), 0, 2);
}
function clean_abbr($a) {
    $a = preg_replace('/\s+/u', '', (string)$a);
    $ch = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
    if (!$ch) return '';
    return mk_abbr($ch[0], implode('', array_slice($ch, 1, 2)));   // max 3 znaky
}

function uid() { return bin2hex(random_bytes(6)); }

function clean_str($s, $max = 40) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_substr($s, 0, $max);
}

if (!is_dir($dir) && !@mkdir($dir, 0775, true)) fail(500, 'Nelze vytvořit složku data/');

$fh = fopen($file, 'c+');
if (!$fh) fail(500, 'Nelze otevřít data/prsicko.json (práva k zápisu?)');

$method = $_SERVER['REQUEST_METHOD'];
flock($fh, $method === 'POST' ? LOCK_EX : LOCK_SH);

$raw = stream_get_contents($fh);
$db  = $raw ? json_decode($raw, true) : null;
if (!is_array($db)) $db = empty_db();
$db = array_merge(empty_db(), $db);
$db['settings'] = array_merge(empty_db()['settings'], (array)$db['settings']);

// --- heslo pro úpravy ---
$hash = null;
if (is_file($authFile)) {
    $a = json_decode((string)@file_get_contents($authFile), true);
    $hash = $a['hash'] ?? null;
}
function out($db, $hasPw) {
    $db['auth'] = ['hasPassword' => (bool)$hasPw];
    echo json_encode($db, JSON_UNESCAPED_UNICODE);
}
function deny() {
    sleep(1);   // zpomalení hádání hesla
    http_response_code(403);
    echo json_encode(['error' => 'Špatné heslo – zápis zamítnut', 'auth' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    flock($fh, LOCK_UN); fclose($fh);
    out($db, $hash);
    exit;
}

if ($method !== 'POST') fail(405, 'Jen GET nebo POST');

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['action'])) fail(400, 'Chybí action');

// Každý zápis potřebuje heslo. Jediná výjimka: úplně první nastavení hesla.
$pw = (string)($in['password'] ?? '');
if ($in['action'] === 'set_password') {
    if ($hash && !password_verify($pw, $hash)) deny();
    $new = (string)($in['newPassword'] ?? '');
    if (mb_strlen($new) < 4) fail(400, 'Heslo musí mít aspoň 4 znaky');
    if (@file_put_contents($authFile, json_encode(['hash' => password_hash($new, PASSWORD_DEFAULT)])) === false) {
        fail(500, 'Nelze uložit heslo (práva k zápisu do data/?)');
    }
    @chmod($authFile, 0600);
    out($db, true);
    exit;
}
if (!$hash) fail(403, 'Nejdřív nastav heslo pro úpravy');
if (!password_verify($pw, $hash)) deny();
if ($in['action'] === 'check') { out($db, true); exit; }   // jen ověření hesla

$playerIds = array_column($db['players'], 'id');
$hasPlayer = function ($id) use ($playerIds) { return in_array($id, $playerIds, true); };

// index otevřeného herního dne (nebo null)
function open_session_idx($db) {
    foreach ($db['sessions'] as $i => $s) if (empty($s['closedTs'])) return $i;
    return null;
}

switch ($in['action']) {

    case 'start_session':
        if (open_session_idx($db) !== null) fail(400, 'Herní den už běží – nejdřív ho ukonči');
        $date = (string)($in['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) fail(400, 'Neplatné datum');
        $db['sessions'][] = ['id' => uid(), 'date' => $date, 'startTs' => time(), 'closedTs' => null];
        break;

    case 'close_session':
        $i = open_session_idx($db);
        if ($i === null || $db['sessions'][$i]['id'] !== ($in['id'] ?? '')) fail(400, 'Tenhle herní den neběží');
        $sid = $db['sessions'][$i]['id'];
        $hasGames = false;
        foreach ($db['games'] as $g) if (($g['sessionId'] ?? '') === $sid) { $hasGames = true; break; }
        if ($hasGames) $db['sessions'][$i]['closedTs'] = time();
        else array_splice($db['sessions'], $i, 1);   // prázdný den se rovnou zahodí
        break;

    case 'reopen_session':
        if (open_session_idx($db) !== null) fail(400, 'Jiný herní den právě běží');
        $found = false;
        foreach ($db['sessions'] as &$s) {
            if ($s['id'] === ($in['id'] ?? '')) { $s['closedTs'] = null; $found = true; }
        }
        unset($s);
        if (!$found) fail(400, 'Herní den nenalezen');
        break;

    case 'add_player':
        $name = clean_str($in['name'] ?? '', 24);
        if ($name === '') fail(400, 'Hráč potřebuje jméno');
        foreach ($db['players'] as $p) {
            if (mb_strtolower($p['name']) === mb_strtolower($name)) fail(400, 'Tenhle hráč už u stolu sedí');
        }
        // name = celá přezdívka; abbr = jedinečná zkratka u stolu (výchozí první dvě písmena)
        $taken = taken_abbrs($db['players']);
        $abbr = clean_abbr($in['abbr'] ?? '');
        if ($abbr !== '' && isset($taken[mb_strtolower($abbr)])) fail(400, 'Zkratku „' . $abbr . '“ už má ' . $taken[mb_strtolower($abbr)]);
        if ($abbr === '') $abbr = unique_abbr($name, $taken);
        $db['players'][] = [
            'id'   => uid(),
            'name' => $name,
            'abbr' => $abbr,
        ];
        break;

    case 'set_abbr':
        $abbr = clean_abbr($in['abbr'] ?? '');
        if ($abbr === '') fail(400, 'Zkratka nesmí být prázdná');
        $taken = taken_abbrs($db['players'], $in['id'] ?? '');
        if (isset($taken[mb_strtolower($abbr)])) fail(400, 'Zkratku „' . $abbr . '“ už má ' . $taken[mb_strtolower($abbr)]);
        $found = false;
        foreach ($db['players'] as &$p) if ($p['id'] === ($in['id'] ?? '')) { $p['abbr'] = $abbr; $found = true; }
        unset($p);
        if (!$found) fail(400, 'Hráč nenalezen');
        break;

    case 'remove_player':
        // Hráče s historií neodstraňujeme, jen schováme (aby seděla bilance).
        foreach ($db['players'] as &$p) {
            if ($p['id'] === ($in['id'] ?? '')) $p['hidden'] = !empty($in['hidden']);
        }
        unset($p);
        break;

    case 'add_game':
        $si = open_session_idx($db);
        if ($si === null) fail(400, 'Nejdřív začni herní den');
        $w = $in['winner'] ?? ''; $l = $in['loser'] ?? '';
        if (!$hasPlayer($w) || !$hasPlayer($l)) fail(400, 'Neznámý vítěz nebo poražený');
        if ($w === $l) fail(400, 'Nemůžeš vyhrát sám nad sebou (i když by to bylo absurdní)');
        // platba = zaokrouhlit(body v ruce poraženého × koeficient) × násobek zavření (×1–×4)
        // př. koeficient 0,1: 67 b → 6,7 → 7 × 2 = 14;  64 b → 6,4 → 6 × 2 = 12
        $points = (int)($in['points'] ?? -1);
        if ($points < 0 || $points > 999) fail(400, 'Body v ruce musí být 0–999');
        // násobek musí být jeden z těch v Nastavení
        $mult = (float)($in['mult'] ?? 0);
        $multLabel = null;
        foreach ((array)$db['settings']['mults'] as $mm) {
            if (abs((float)$mm['m'] - $mult) < 0.0001) { $mult = (float)$mm['m']; $multLabel = $mm['t']; break; }
        }
        if ($multLabel === null) fail(400, 'Takový násobek v Nastavení není');
        if ($mult == (int)$mult) $mult = (int)$mult;
        $pv = (float)$db['settings']['pointValue'];
        $base  = (int)round($points * $pv + 1e-9);    // body × koeficient → celé (+1e-9 kvůli 6,5 → 7)
        $stake = (int)round($base * $mult + 1e-9);    // × násobek → vždy celé koruny
        $parts = array_values(array_unique(array_filter((array)($in['participants'] ?? []), $hasPlayer)));
        if (!in_array($w, $parts, true)) $parts[] = $w;
        if (!in_array($l, $parts, true)) $parts[] = $l;
        $db['games'][] = [
            'id'           => uid(),
            'sessionId'    => $db['sessions'][$si]['id'],
            'ts'           => time(),
            'participants' => $parts,
            'winner'       => $w,
            'loser'        => $l,
            'points'       => $points,
            'mult'         => $mult,
            'multLabel'    => $multLabel,
            'pointValue'   => $pv,
            'base'         => $base,
            'stake'        => $stake,
            'note'         => clean_str($in['note'] ?? '', 120),
        ];
        break;

    // --- úpravy už zapsaných věcí ---
    case 'set_session_date':
        $date = (string)($in['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) fail(400, 'Neplatné datum');
        $found = false;
        foreach ($db['sessions'] as &$s) if ($s['id'] === ($in['id'] ?? '')) { $s['date'] = $date; $found = true; }
        unset($s);
        if (!$found) fail(400, 'Herní den nenalezen');
        break;

    case 'update_game':
        $gi = null;
        foreach ($db['games'] as $i => $g) if ($g['id'] === ($in['id'] ?? '')) { $gi = $i; break; }
        if ($gi === null) fail(400, 'Hra nenalezena');
        $g = $db['games'][$gi];
        $w = $in['winner'] ?? $g['winner']; $l = $in['loser'] ?? $g['loser'];
        if (!$hasPlayer($w) || !$hasPlayer($l)) fail(400, 'Neznámý vítěz nebo poražený');
        if ($w === $l) fail(400, 'Vítěz a poražený nemůže být tentýž hráč');
        $points = (int)($in['points'] ?? $g['points'] ?? -1);
        if ($points < 0 || $points > 999) fail(400, 'Body v ruce musí být 0–999');
        // násobek: některý z Nastavení, nebo ten původní (i kdyby už v Nastavení nebyl)
        $mult = (float)($in['mult'] ?? $g['mult'] ?? 1);
        $multLabel = null;
        foreach ((array)$db['settings']['mults'] as $mm) {
            if (abs((float)$mm['m'] - $mult) < 0.0001) { $mult = (float)$mm['m']; $multLabel = $mm['t']; break; }
        }
        if ($multLabel === null) {
            if (abs((float)($g['mult'] ?? 1) - $mult) < 0.0001) $multLabel = $g['multLabel'] ?? '';
            else fail(400, 'Takový násobek v Nastavení není');
        }
        if ($mult == (int)$mult) $mult = (int)$mult;
        // přepočet s koeficientem, se kterým se hra hrála
        $pv = (float)($g['pointValue'] ?? $db['settings']['pointValue']);
        $base  = (int)round($points * $pv + 1e-9);
        $stake = (int)round($base * $mult + 1e-9);
        $parts = isset($in['participants'])
            ? array_values(array_unique(array_filter((array)$in['participants'], $hasPlayer)))
            : $g['participants'];
        if (!in_array($w, $parts, true)) $parts[] = $w;
        if (!in_array($l, $parts, true)) $parts[] = $l;
        $db['games'][$gi] = array_merge($g, [
            'winner' => $w, 'loser' => $l, 'participants' => $parts,
            'points' => $points, 'mult' => $mult, 'multLabel' => $multLabel,
            'pointValue' => $pv, 'base' => $base, 'stake' => $stake,
            'note' => array_key_exists('note', $in) ? clean_str($in['note'], 120) : ($g['note'] ?? ''),
            'editedTs' => time(),
        ]);
        break;

    case 'rename_player':
        $name = clean_str($in['name'] ?? '', 24);
        if ($name === '') fail(400, 'Hráč potřebuje jméno');
        foreach ($db['players'] as $p) {
            if ($p['id'] !== ($in['id'] ?? '') && mb_strtolower($p['name']) === mb_strtolower($name)) fail(400, 'Tuhle přezdívku už má jiný hráč');
        }
        $found = false;
        foreach ($db['players'] as &$p) if ($p['id'] === ($in['id'] ?? '')) {
            if (empty($p['abbr'])) $p['abbr'] = base_abbr($p['name']);   // zkratka zůstane, jak byla
            $p['name'] = $name; $found = true;
        }
        unset($p);
        if (!$found) fail(400, 'Hráč nenalezen');
        break;

    case 'update_settlement':
        $amt = (int)round((float)($in['amount'] ?? 0));
        if ($amt <= 0) fail(400, 'Částka musí být větší než 0');
        $found = false;
        foreach ($db['settlements'] as &$st) if ($st['id'] === ($in['id'] ?? '')) { $st['amount'] = $amt; $found = true; }
        unset($st);
        if (!$found) fail(400, 'Vyrovnání nenalezeno');
        break;

    case 'delete_game':
        $db['games'] = array_values(array_filter($db['games'], function ($g) use ($in) { return $g['id'] !== ($in['id'] ?? ''); }));
        break;

    case 'settle':
        $f = $in['from'] ?? ''; $t = $in['to'] ?? '';
        $amt = (int)round((float)($in['amount'] ?? 0));   // jen celé koruny
        if (!$hasPlayer($f) || !$hasPlayer($t) || $f === $t || $amt <= 0) fail(400, 'Neplatné vyrovnání');
        $db['settlements'][] = ['id' => uid(), 'ts' => time(), 'from' => $f, 'to' => $t, 'amount' => $amt];
        break;

    case 'delete_settlement':
        $db['settlements'] = array_values(array_filter($db['settlements'], function ($s) use ($in) { return $s['id'] !== ($in['id'] ?? ''); }));
        break;

    case 'settings':
        $pv = round((float)($in['pointValue'] ?? $db['settings']['pointValue']), 4);
        if ($pv <= 0 || $pv > 10000) fail(400, 'Koeficient musí být větší než 0');
        $db['settings']['pointValue'] = $pv;
        $cur = clean_str($in['currency'] ?? $db['settings']['currency'], 8);
        if ($cur !== '') $db['settings']['currency'] = $cur;
        if (isset($in['mults'])) {
            $list = []; $seen = [];
            foreach ((array)$in['mults'] as $mm) {
                $m = round((float)($mm['m'] ?? 0), 2);
                $t = clean_str($mm['t'] ?? '', 30);
                if ($m <= 0 || $m > 100) fail(400, 'Násobek musí být větší než 0 a nejvýš 100');
                if (isset($seen[(string)$m])) fail(400, 'Násobek ×' . $m . ' je tam dvakrát');
                $seen[(string)$m] = true;
                $list[] = ['m' => ($m == (int)$m ? (int)$m : $m), 't' => $t];
            }
            if (!$list) fail(400, 'Aspoň jeden násobek musí zůstat');
            if (count($list) > 10) fail(400, 'Nejvýš 10 násobků');
            usort($list, function ($a, $b) { return $a['m'] <=> $b['m']; });
            $db['settings']['mults'] = $list;
        }
        break;

    default:
        fail(400, 'Neznámá akce');
}

$db['rev'] = (int)$db['rev'] + 1;
$json = json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Záloha před zápisem (jedna předchozí verze)
if ($raw) @file_put_contents($dir . '/prsicko.bak.json', $raw);

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, $json);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

out($db, true);
