<?php
/**
 * CUP SEAL – ajánlatkérő űrlap feldolgozása.
 * Az adatokat nem menti adatbázisba: e-mailben továbbítja a CIMZETT címre.
 * JSON-t ad vissza, ha a kérés "Accept: application/json" fejlécet küld (JS-es beküldés),
 * egyébként a köszönőoldalra irányít át.
 *
 * Élesítés (tarhely.com / mhosting):
 *  1. A tárhely adminfelületén hozd létre a FELADO postafiókot (pl. noreply@cupseal.hu).
 *  2. Töltsd fel a fájlt, küldj egy próbát az oldalról, és nézd meg a Gmailben (a Spam mappát is).
 */
declare(strict_types=1);

// ===== BEÁLLÍTÁS =====
const CIMZETT       = 'mullerdanielev@gmail.com';  // ide érkeznek az ajánlatkérések
const FELADO        = 'noreply@cupseal.hu';        // létező postafiók a cupseal.hu tárhelyen (SPF/DKIM így megfelel)
const FELADO_NEV    = 'CUP SEAL weboldal';
const TARGY_ELOTAG  = '[CUP SEAL] Ajánlatkérés';
const VISSZAIGAZOLAS = true;                       // küldjön-e „megkaptuk” levelet az érdeklődőnek
const ENGEDETT_HOSTOK = ['cupseal.hu', 'www.cupseal.hu'];
const MAX_KULDES_ORANKENT = 5;                     // ugyanarról az IP-ről
const MIN_KITOLTESI_IDO = 3;                       // másodperc; ennél gyorsabb beküldés robotgyanús
// =====================

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Budapest');
$jsonKell = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function valasz(bool $ok, string $uzenet, array $mezok = [], int $kod = 200): void
{
    global $jsonKell;
    http_response_code($kod);
    if ($jsonKell) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'uzenet' => $uzenet, 'mezok' => $mezok], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('Location: koszonjuk.html', true, 303);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $u = htmlspecialchars($uzenet, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=hu><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'>"
       . "<title>Hiba az ajánlatkérésben – CUP SEAL</title><link rel=stylesheet href=assets/fonts.css><link rel=stylesheet href=assets/oldal.css>"
       . "<main class=wrap style='padding:80px 0'><div class=head><span class=eyebrow style='--k:var(--deep)'>Hiba</span>"
       . "<h1 class=disp>Nem ment el.</h1><p class=lead>$u</p><div class=actions><a class=btn href='javascript:history.back()'>Vissza az űrlaphoz</a></div></div></main>";
    exit;
}

/** UTF-8 szöveges levél küldése base64 törzzsel (hosszú sorok és ékezetek biztonságosan). */
function level(string $cimzett, string $targy, string $torzs, string $valaszCim): bool
{
    $fejlecek = implode("\r\n", [
        'From: ' . mb_encode_mimeheader(FELADO_NEV, 'UTF-8', 'B') . ' <' . FELADO . '>',
        'Reply-To: ' . $valaszCim,
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@cupseal.hu>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: cupseal-urlap',
    ]);
    return @mail(
        $cimzett,
        mb_encode_mimeheader($targy, 'UTF-8', 'B', "\r\n"),
        rtrim(chunk_split(base64_encode($torzs), 76, "\r\n")),
        $fejlecek,
        '-f' . FELADO
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    valasz(false, 'Az űrlapot az oldalról küldd el.', [], 405);
}

// Csak a saját oldalunkról érkező beküldést fogadjuk el (ha a böngésző megadja a forrást).
$forras = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($forras !== '') {
    $host = strtolower((string)parse_url($forras, PHP_URL_HOST));
    $sajat = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && $host !== $sajat && !in_array($host, ENGEDETT_HOSTOK, true)) {
        valasz(false, 'Az űrlapot a cupseal.hu oldalról küldd el.', [], 403);
    }
}

// Spamcsapdák: a rejtett mezőt ember nem tölti ki, és ember nem küldi be 3 mp alatt.
if (trim((string)($_POST['weboldal'] ?? '')) !== '') {
    valasz(true, 'Köszönjük!');
}
$kitoltes = mezo('kt', 12);   // a böngésző méri: betöltés és beküldés között eltelt ms; JS nélkül üres
if ($kitoltes !== '' && ctype_digit($kitoltes) && (int)$kitoltes < MIN_KITOLTESI_IDO * 1000) {
    valasz(true, 'Köszönjük!');
}

// Egyszerű ütemkorlát IP-kivonat alapján (az IP-t nem tároljuk, csak a sóval képzett kivonatát, 1 óráig).
$kivonat = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . __FILE__);
$korlatFajl = sys_get_temp_dir() . '/cupseal-' . substr($kivonat, 0, 32);
$most = time();
$korabbiak = array_filter(
    is_file($korlatFajl) ? (array)json_decode((string)@file_get_contents($korlatFajl), true) : [],
    fn($t) => is_int($t) && $t > $most - 3600
);
if (count($korabbiak) >= MAX_KULDES_ORANKENT) {
    valasz(false, 'Túl sok küldés rövid idő alatt. Kérjük, próbáld újra egy óra múlva.', [], 429);
}

function mezo(string $nev, int $max): string
{
    $v = $_POST[$nev] ?? '';
    $v = is_string($v) ? trim($v) : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
function egysoros(string $v): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $v) ?? '');
}

$nev       = egysoros(mezo('nev', 100));
$ceg       = egysoros(mezo('ceg', 120));
$telefon   = egysoros(mezo('telefon', 40));
$email     = egysoros(mezo('email', 160));
$mennyiseg = egysoros(mezo('mennyiseg', 40));
$uzenet    = mezo('uzenet', 2000);

$szinek  = ['feher' => 'Fehér', 'fekete' => 'Fekete', 'mindegy' => 'Még nem tudja'];
$mellek  = ['adapter' => 'Adapter', 'doboz' => 'Dobozok', 'fedel' => 'Fedelek'];
$meretek = ['Még nem tudom', '250–330 ml', '350–500 ml', '600–700 ml', '900–1000 ml'];

$szin  = $szinek[mezo('szin', 20)] ?? '–';
$kert  = array_filter((array)($_POST['melle'] ?? []), 'is_string');
$melle = array_values(array_intersect_key($mellek, array_flip($kert)));
$meret = in_array(mezo('meret', 40), $meretek, true) ? mezo('meret', 40) : '–';

$hibas = [];
if ($nev === '')                                            $hibas[] = 'nev';
if (!preg_match('/^[0-9+()\/\-\s]{6,40}$/', $telefon))     $hibas[] = 'telefon';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))             $hibas[] = 'email';
if (mezo('adatkezeles', 10) !== 'igen')                     $hibas[] = 'adatkezeles';
if ($hibas) {
    valasz(false, 'Kérjük, ellenőrizd a megjelölt mezőket: név, telefon, e-mail, és fogadd el az adatkezelési tájékoztatót.', $hibas, 422);
}

$torzs = implode("\r\n", [
    'Új ajánlatkérés érkezett a cupseal.hu oldalról.',
    '',
    'Név:              ' . $nev,
    'Cég / üzlet:      ' . ($ceg ?: '–'),
    'Telefon:          ' . $telefon,
    'E-mail:           ' . $email,
    'Gép színe:        ' . $szin,
    'A gép mellé kér:  ' . ($melle ? implode(', ', $melle) : '–'),
    'Dobozméret:       ' . $meret,
    'Havi darabszám:   ' . ($mennyiseg ?: '–'),
    '',
    'Mit zárna le:',
    $uzenet ?: '–',
    '',
    '—',
    'Beküldve: ' . date('Y-m-d H:i'),
    'Az adatkezelési tájékoztatót elfogadta.',
    'Válaszhoz nyomd meg a „Válasz” gombot – a levél az érdeklődőhöz megy.',
]);

if (!level(CIMZETT, TARGY_ELOTAG . ' – ' . $nev . ($ceg ? " ($ceg)" : ''), $torzs, $email)) {
    error_log('cupseal ajanlat.php: mail() sikertelen');
    valasz(false, 'Technikai hiba miatt most nem sikerült elküldeni. Kérjük, próbáld újra néhány perc múlva.', [], 500);
}

// Visszaigazolás az érdeklődőnek. Szándékosan nem ismétli meg a beküldött szöveget,
// így az űrlap nem használható idegen címekre küldött spamre.
if (VISSZAIGAZOLAS) {
    level($email, 'Megkaptuk az ajánlatkérésed – CUP SEAL', implode("\r\n", [
        'Kedves ' . mb_substr($nev, 0, 60) . '!',
        '',
        'Köszönjük, megkaptuk az ajánlatkérésed a CUP SEAL dobozzáró gépre.',
        'Hamarosan jelentkezünk telefonon vagy e-mailben, és egyeztetjük a próbazárást.',
        '',
        'Ha kérdésed van, erre a levélre válaszolva is írhatsz nekünk.',
        '',
        'Üdvözlettel:',
        'CUP SEAL',
        'https://cupseal.hu',
        '',
        '—',
        'Ezt a levelet azért kaptad, mert ezzel az e-mail-címmel ajánlatot kértél a cupseal.hu oldalon.',
        'Adatkezelési tájékoztató: https://cupseal.hu/adatkezeles.html',
    ]), CIMZETT);
}

$korabbiak[] = $most;
@file_put_contents($korlatFajl, json_encode(array_values($korabbiak)), LOCK_EX);

valasz(true, 'Köszönjük! Megkaptuk az ajánlatkérésed, hamarosan jelentkezünk.');
