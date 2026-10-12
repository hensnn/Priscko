<?php
// Prsíčko – miniaturní API nad JSON souborem.
// GET  api.php            -> vrátí celou databázi
// POST api.php {action..} -> provede operaci a vrátí novou databázi
// Zápis je chráněný zámkem (flock), takže dva telefony najednou nic nerozbijí.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dir  = __DIR__ . '/data';
$file = $dir . '/prsicko.json';

function empty_db() {
    return [
        'players'     => [],
        // herní dny: {id, date: "YYYY-MM-DD", startTs, closedTs|null}; otevřený může být jen jeden
        'sessions'    => [],
        'games'       => [],
        'settlements' => [],
        // pointValue = kolik stojí jeden bod (body v ruce poraženého × násobek × pointValue)
        'settings'    => ['pointValue' => 1, 'currency' => 'Kč'],
        'rev'         => 0,
    ];
}

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
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

if ($method === 'GET') {
    flock($fh, LOCK_UN); fclose($fh);
    echo json_encode($db, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') fail(405, 'Jen GET nebo POST');

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['action'])) fail(400, 'Chybí action');

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
        // name = celá přezdívka; zkratka u stolu (první dvě písmena) se počítá v prohlížeči
        $db['players'][] = [
            'id'   => uid(),
            'name' => $name,
        ];
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
        // Body v ruce poraženého × násobek zavření (×1–×4) × cena bodu
        $points = (int)($in['points'] ?? -1);
        if ($points < 0 || $points > 999) fail(400, 'Body v ruce musí být 0–999');
        $mult = (int)($in['mult'] ?? 1);
        if (!in_array($mult, [1, 2, 3, 4], true)) fail(400, 'Násobek může být jen ×1, ×2, ×3 nebo ×4');
        $pv = (float)$db['settings']['pointValue'];
        $stake = round($points * $mult * $pv, 2);
        $parts = array_values(array_unique(array_filter((array)($in['participants'] ?? []), $hasPlayer)));
        if (!in_array($w, $parts, true)) $parts[] = $w;
        if (!in_array($l, $parts, true)) $parts[] = $l;
        $flags = array_values(array_slice(array_map(function ($f) { return clean_str($f, 24); }, (array)($in['flags'] ?? [])), 0, 20));
        $db['games'][] = [
            'id'           => uid(),
            'sessionId'    => $db['sessions'][$si]['id'],
            'ts'           => time(),
            'participants' => $parts,
            'winner'       => $w,
            'loser'        => $l,
            'points'       => $points,
            'mult'         => $mult,
            'pointValue'   => $pv,
            'stake'        => $stake,
            'flags'        => $flags,
            'note'         => clean_str($in['note'] ?? '', 120),
        ];
        break;

    case 'delete_game':
        $db['games'] = array_values(array_filter($db['games'], function ($g) use ($in) { return $g['id'] !== ($in['id'] ?? ''); }));
        break;

    case 'settle':
        $f = $in['from'] ?? ''; $t = $in['to'] ?? '';
        $amt = round((float)($in['amount'] ?? 0), 2);
        if (!$hasPlayer($f) || !$hasPlayer($t) || $f === $t || $amt <= 0) fail(400, 'Neplatné vyrovnání');
        $db['settlements'][] = ['id' => uid(), 'ts' => time(), 'from' => $f, 'to' => $t, 'amount' => $amt];
        break;

    case 'delete_settlement':
        $db['settlements'] = array_values(array_filter($db['settlements'], function ($s) use ($in) { return $s['id'] !== ($in['id'] ?? ''); }));
        break;

    case 'settings':
        $pv = round((float)($in['pointValue'] ?? $db['settings']['pointValue']), 2);
        if ($pv <= 0 || $pv > 10000) fail(400, 'Cena bodu musí být větší než 0');
        $db['settings']['pointValue'] = $pv;
        $cur = clean_str($in['currency'] ?? $db['settings']['currency'], 8);
        if ($cur !== '') $db['settings']['currency'] = $cur;
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

echo $json;
