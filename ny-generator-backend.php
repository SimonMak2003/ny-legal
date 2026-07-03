<?php
/**
 * ══════════════════════════════════════════════════════════
 *  ★  NEW YORK STATE TEMPLATE GENERATOR  ·  BACKEND v1.0  ★
 *  Empire State of New York  ·  BBCode Template Engine
 *  EXCELSIOR  ·  Ever Upward
 * ══════════════════════════════════════════════════════════
 *  Generates BBCode templates for politsim.ru
 *  Contact: Charles Lee
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) { echo json_encode(['success' => false, 'error' => 'Empty body.']); exit; }

$body = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'JSON error: ' . json_last_error_msg()]);
    exit;
}
if (empty($body['template']) || !array_key_exists('data', $body)) {
    echo json_encode(['success' => false, 'error' => 'Missing fields: template, data.']);
    exit;
}

$tpl  = (string) $body['template'];
$data = (array)  $body['data'];

/* ════════════════════════════════════════════════════════════
   UTILITIES
   ════════════════════════════════════════════════════════════ */

function str(mixed $v): string { return trim((string)($v ?? '')); }

function rom(int $n): string {
    static $map = [1000=>'M',900=>'CM',500=>'D',400=>'CD',100=>'C',90=>'XC',
                    50=>'L',40=>'XL',10=>'X',9=>'IX',5=>'V',4=>'IV',1=>'I'];
    $r = '';
    foreach ($map as $v => $s) { while ($n >= $v) { $r .= $s; $n -= $v; } }
    return $r;
}

function sColor(string $s): string {
    return match(true) {
        in_array($s,['Принят','Принята','Принято','Действующий','Вступило в силу'],true) => '#2ECC71',
        in_array($s,['Отклонён','Отклонена','Отклонено','Отменён','Утратил силу'],true)  => '#B03A2E',
        in_array($s,['На рассмотрении','Проект'],true)                                   => '#E8A000',
        in_array($s,['Обжаловано'],true)                                                 => '#5B9BD5',
        default => '#9E9E9E'
    };
}

function uRef(string $t): string {
    return preg_replace_callback(
        '/
\[([^\|\]]+)\|(\d+)\]/',
        fn($m) => '[URL=https://politsim.ru/members/'.$m[2].'/]'.$m[1].'[/URL]',
        $t
    ) ?? $t;
}

function uLink(string $name, string $id): string {
    if ($name === '') return '';
    return $id !== '' ? '[URL=https://politsim.ru/members/'.$id.'/]'.$name.'[/URL]' : $name;
}

/* ════════════════════════════════════════════════════════════
   NY BRAND COMPONENTS
   ════════════════════════════════════════════════════════════ */

function dGold(): string {
    return '[COLOR=#FFB81C]══════════════════════ ✦ ══════════════════════[/COLOR]';
}
function dLight(): string {
    return '[COLOR=#E8E6E1]─────────────────────────────────────────────[/COLOR]';
}
function dThin(): string {
    return '[COLOR=#9E9E9E]─────────────────────────────────────────────[/COLOR]';
}

function nyHeader(string $ru, string $en, string $docType): string {
    $o  = '[CENTER]'."\n";
    $o .= '[COLOR=#002D72][B]★  ШТАТ НЬЮ-ЙОРК  ·  STATE OF NEW YORK  ★[/B][/COLOR]'."\n";
    $o .= dGold()."\n";
    if ($docType !== '') $o .= '[COLOR=#5B9BD5][I]'.$docType.'[/I][/COLOR]'."\n";
    if ($ru      !== '') $o .= '[COLOR=#002D72][SIZE=6][B]'.$ru.'[/B][/SIZE][/COLOR]'."\n";
    if ($en      !== '') $o .= '[COLOR=#9E9E9E][I]'.$en.'[/I][/COLOR]'."\n";
    $o .= dGold()."\n";
    $o .= '[/CENTER]'."\n\n";
    return $o;
}

function nyFooter(): string {
    return "\n".dGold()."\n"
        .'[CENTER][SIZE=2]'
        .'[COLOR=#9E9E9E]🦅  [B]Empire State of New York[/B]  ·  [/COLOR]'
        .'[COLOR=#FFB81C][B]EXCELSIOR[/B][/COLOR]'
        .'[COLOR=#9E9E9E]  ·  Ever Upward  🦅[/COLOR]'
        .'[/SIZE][/CENTER]'."\n";
}

function metaRow(array $pairs): string {
    $parts = [];
    foreach ($pairs as [$label, $val]) {
        if ($val !== '') $parts[] = '[COLOR=#9E9E9E]'.$label.':[/COLOR] [B]'.$val.'[/B]';
    }
    if (empty($parts)) return '';
    return '[CENTER]'.implode('   ·   ', $parts).'[/CENTER]'."\n\n";
}

function secHead(string $text, string $icon = ''): string {
    $pfx = $icon !== '' ? $icon.' ' : '';
    return '[COLOR=#002D72][B]'.$pfx.mb_strtoupper($text,'UTF-8').'[/B][/COLOR]'."\n".dLight()."\n";
}

function nySig(string $name, string $id, string $role, string $img = ''): string {
    if ($name === '' && $role === '') return '';
    $o  = "\n".dThin()."\n".'[RIGHT]';
    if ($img  !== '') $o .= '[IMG]'.$img.'[/IMG]'."\n";
    if ($name !== '') $o .= '[B]'.uLink($name, $id).'[/B]'."\n";
    if ($role !== '') $o .= '[COLOR=#5B9BD5][I]'.$role.'[/I][/COLOR]'."\n";
    $o .= '[/RIGHT]'."\n";
    return $o;
}

function nyArticles(array $arts, string $word = 'ARTICLE'): string {
    $o = '';
    foreach ($arts as $ai => $art) {
        $title = str($art['title'] ?? '');
        $o .= '[COLOR=#002D72][B]'.$word.' '.rom($ai+1);
        if ($title !== '') $o .= '. '.mb_strtoupper($title,'UTF-8');
        $o .= '[/B][/COLOR]'."\n";
        $o .= '[COLOR=#FFB81C]──────────────────────────────────────[/COLOR]'."\n";
        foreach (($art['sections'] ?? []) as $si => $sec) {
            $sLabel = ($ai+1).'.'.($si+1);
            $sT  = str($sec['title'] ?? '');
            $sTx = str($sec['text']  ?? '');
            $o .= '[INDENT][B]Section '.$sLabel.($sT !== '' ? '. '.$sT : '').'[/B]'."\n";
            if ($sTx !== '') $o .= $sTx."\n";
            foreach (($sec['subsections'] ?? []) as $ssi => $sub) {
                $st = str($sub['text'] ?? '');
                if ($st !== '') $o .= '[INDENT]'.chr(97+$ssi).') '.$st.'[/INDENT]'."\n";
            }
            $o .= '[/INDENT]'."\n";
        }
        $o .= "\n";
    }
    return $o;
}

function personTable(array $persons): string {
    if (empty($persons)) return '';
    $o  = '[TABLE]'."\n";
    $o .= '[TR][TH][COLOR=#002D72]№[/COLOR][/TH][TH][COLOR=#002D72]Имя[/COLOR][/TH][TH][COLOR=#002D72]Должность[/COLOR][/TH][/TR]'."\n";
    foreach ($persons as $i => $p) {
        $nm = uLink(str($p['name'] ?? ''), str($p['id'] ?? ''));
        $rl = str($p['role'] ?? '');
        $o .= '[TR][TD]'.($i+1).'[/TD][TD]'.$nm.'[/TD][TD]'.$rl.'[/TD][/TR]'."\n";
    }
    $o .= '[/TABLE]'."\n\n";
    return $o;
}

/* ════════════════════════════════════════════════════════════
   TEMPLATE GENERATORS
   ════════════════════════════════════════════════════════════ */

function genLaw(array $d): string {
    $ru  = str($d['titleRu']  ?? ''); $en  = str($d['titleEn']  ?? '');
    $st  = str($d['status']   ?? 'Проект');
    $ses = str($d['session']  ?? ''); $pre = str($d['preamble'] ?? '');

    $o  = nyHeader($ru, $en, 'Закон Штата Нью-Йорк  ·  Law of the State of New York');
    $o .= metaRow([['Статус','[COLOR='.sColor($st).']'.$st.'[/COLOR]'],['Сессия',$ses]]);
    if ($pre !== '') { $o .= secHead('Преамбула'); $o .= '[INDENT][I]'.$pre.'[/I][/INDENT]'."\n\n"; }
    $o .= dGold()."\n\n";
    $o .= nyArticles($d['articles'] ?? [], 'СТАТЬЯ');
    $o .= nyFooter();
    return $o;
}

function genResolution(array $d): string {
    $num = str($d['number'] ?? ''); $dt  = str($d['date']   ?? '');
    $st  = str($d['status'] ?? 'Проект'); $ttl = str($d['title']  ?? '');
    $pre = str($d['preamble'] ?? '');
    $useSt = (bool)($d['useStructure'] ?? false);
    $txt = str($d['simpleText'] ?? ''); $art = $d['articles'] ?? [];

    $o  = nyHeader($ttl, $num !== '' ? 'Resolution '.$num : '', 'Резолюция Легислатуры  ·  Legislature Resolution');
    $o .= metaRow([['№',$num],['Дата',$dt],['Статус','[COLOR='.sColor($st).']'.$st.'[/COLOR]']]);
    if ($pre !== '') { $o .= secHead('Преамбула'); $o .= '[INDENT][I]'.$pre.'[/I][/INDENT]'."\n\n"; }
    $o .= dGold()."\n\n";
    $o .= ($useSt && !empty($art)) ? nyArticles($art) : ($txt !== '' ? '[INDENT]'.$txt.'[/INDENT]'."\n\n" : '');
    $o .= nyFooter();
    return $o;
}

function genExecutive(array $d): string {
    $num = str($d['number'] ?? ''); $dt  = str($d['date']   ?? '');
    $st  = str($d['status'] ?? 'Действующий'); $ttl = str($d['title']  ?? '');
    $pre = str($d['preamble'] ?? '');
    $useSt = (bool)($d['useStructure'] ?? false);
    $txt = str($d['simpleText'] ?? ''); $art = $d['articles'] ?? [];
    $sN  = str($d['signerName'] ?? ''); $sId = str($d['signerId']  ?? '');
    $sR  = str($d['signerRole'] ?? ''); $sImg= str($d['signature'] ?? '');

    $o  = nyHeader($ttl, $num !== '' ? 'Executive Order '.$num : '', 'Исполнительный Указ Губернатора  ·  Governor\'s Executive Order');
    $o .= metaRow([['Номер',$num],['Дата',$dt],['Статус','[COLOR='.sColor($st).']'.$st.'[/COLOR]']]);
    if ($pre !== '') { $o .= secHead('Преамбула'); $o .= '[INDENT][I]'.$pre.'[/I][/INDENT]'."\n\n"; }
    $o .= dGold()."\n\n";
    $o .= ($useSt && !empty($art)) ? nyArticles($art) : ($txt !== '' ? '[INDENT]'.$txt.'[/INDENT]'."\n\n" : '');
    $o .= nySig($sN, $sId, $sR, $sImg);
    $o .= nyFooter();
    return $o;
}

function genMandate(array $d): string {
    $type = str($d['type'] ?? 'senator'); $num  = str($d['number']   ?? '');
    $name = str($d['name'] ?? '');        $uId  = str($d['userId']   ?? '');
    $pos  = str($d['position'] ?? '');    $dt   = str($d['date']     ?? '');
    $iN   = str($d['issuerName'] ?? '');  $iId  = str($d['issuerId'] ?? '');
    $iR   = str($d['issuerRole'] ?? '');  $sigU = str($d['signature']?? '');

    $labels = [
        'senator'   => ['Мандат Сенатора',           "Senator's Mandate"],
        'elector'   => ['Мандат Выборщика',           "Elector's Mandate"],
        'deputy'    => ['Мандат Депутата',            "Deputy's Mandate"],
        'minister'  => ['Мандат Министра',            "Minister's Mandate"],
        'judge'     => ['Мандат Судьи',               "Judge's Mandate"],
        'governor'  => ['Мандат Губернатора',         "Governor's Mandate"],
        'other'     => ['Мандат',                     'Mandate'],
    ];
    [$labelRu, $labelEn] = $labels[$type] ?? $labels['other'];

    $o  = nyHeader($name !== '' ? $labelRu.': '.$name : $labelRu, $labelEn, 'Официальный Мандат  ·  Official Mandate');
    $o .= metaRow([['Мандат №', $num], ['Дата', $dt], ['Статус', '[COLOR='.sColor('Действующий').']Действующий[/COLOR]']]);
    $o .= secHead('Сведения о владельце', '🏛');
    $o .= '[INDENT]'."\n";
    $o .= '[COLOR=#9E9E9E]Имя:[/COLOR] [B]'.uLink($name, $uId).'[/B]'."\n";
    if ($pos !== '') $o .= '[COLOR=#9E9E9E]Должность:[/COLOR] [B][COLOR=#5B9BD5]'.$pos.'[/COLOR][/B]'."\n";
    if ($uId !== '') $o .= '[COLOR=#9E9E9E]ID на форуме:[/COLOR] [B]'.$uId.'[/B]'."\n";
    $o .= '[/INDENT]'."\n\n";
    $o .= nySig($iN, $iId, $iR, $sigU);
    $o .= nyFooter();
    return $o;
}

function genSession(array $d): string {
    $num  = str($d['number']    ?? ''); $dt   = str($d['date']      ?? '');
    $top  = str($d['topic']     ?? '');
    $cN   = str($d['chairName'] ?? ''); $cId  = str($d['chairId']   ?? '');
    $cR   = str($d['chairRole'] ?? '');
    $agenda    = (array)($d['agenda']    ?? []);
    $delegates = (array)($d['delegates'] ?? []);

    $o  = nyHeader('Заседание Легислатуры '.$num, 'Legislature Session '.$num, 'Официальное Заседание  ·  Official Session');
    $o .= metaRow([['Дата', $dt], ['Тема', $top]]);

    if ($cN !== '') {
        $o .= secHead('Председательствующий', '⚖️');
        $o .= '[INDENT][B]'.uLink($cN, $cId).'[/B]';
        if ($cR !== '') $o .= '  —  [COLOR=#5B9BD5][I]'.$cR.'[/I][/COLOR]';
        $o .= '[/INDENT]'."\n\n";
    }

    if (!empty($agenda)) {
        $o .= secHead('Повестка заседания', '📋');
        foreach ($agenda as $i => $item) {
            $item = str($item);
            if ($item !== '') $o .= '[INDENT]'.($i+1).'. '.$item.'[/INDENT]'."\n";
        }
        $o .= "\n";
    }

    if (!empty($delegates)) {
        $o .= secHead('Присутствующие делегаты', '🏛');
        $o .= personTable($delegates);
    }

    $o .= nyFooter();
    return $o;
}

function genMeeting(array $d): string {
    $loc  = str($d['location']    ?? ''); $dt  = str($d['date']  ?? '');
    $top  = str($d['topic']       ?? '');
    $participants = (array)($d['participants'] ?? []);

    $o  = nyHeader('Встреча / Приём', 'Official Meeting', 'Официальная Встреча  ·  Official Meeting');
    $o .= metaRow([['Место', $loc], ['Дата', $dt]]);

    if ($top !== '') {
        $o .= secHead('Тема встречи', '💬');
        $o .= '[INDENT][I]'.$top.'[/I][/INDENT]'."\n\n";
    }

    if (!empty($participants)) {
        $o .= secHead('Участники', '👥');
        $o .= personTable($participants);
    }

    $o .= nyFooter();
    return $o;
}

function genVoteOpen(array $d): string {
    $num  = str($d['number']     ?? '');
    $body = str($d['body']       ?? 'legislature');
    $subj = str($d['subject']    ?? '');
    $desc = str($d['desc']       ?? '');
    $sD   = str($d['startDate']  ?? ''); $eD = str($d['endDate'] ?? '');
    $link = str($d['link']       ?? '');
    $aN   = str($d['authorName'] ?? ''); $aId = str($d['authorId'] ?? '');
    $aR   = str($d['authorRole'] ?? '');
    $opts = (array)($d['options'] ?? []);

    $bodyLabels = [
        'legislature' => 'Легислатура Штата Нью-Йорк',
        'senate'      => 'Сенат Штата Нью-Йорк',
        'house'       => 'Ассамблея Штата Нью-Йорк',
        'committee'   => 'Комитет Легислатуры',
    ];
    $bodyLabel = $bodyLabels[$body] ?? 'Легислатура Штата Нью-Йорк';

    $o  = nyHeader('Голосование '.$num, 'Vote '.$num, 'Открытие Голосования  ·  Vote Opening');
    $o .= '[CENTER][COLOR=#FFB81C][B]⚡ ГОЛОСОВАНИЕ ОТКРЫТО ⚡[/B][/COLOR][/CENTER]'."\n\n";
    $o .= metaRow([['Орган', $bodyLabel], ['Предмет', $subj]]);
    $o .= metaRow([['Открыто', $sD], ['Закрывается', $eD]]);

    if ($desc !== '') {
        $o .= secHead('Описание', '📄');
        $o .= '[INDENT][I]'.$desc.'[/I][/INDENT]'."\n\n";
    }

    if (!empty($opts)) {
        $o .= secHead('Варианты голосования', '🗳');
        foreach ($opts as $i => $opt) {
            $opt = str($opt);
            if ($opt !== '') $o .= '[INDENT][B]'.($i+1).'. '.$opt.'[/B][/INDENT]'."\n";
        }
        $o .= "\n";
    }

    if ($link !== '') {
        $o .= '[CENTER][URL='.$link.'][COLOR=#5B9BD5]🔗 Перейти к обсуждению[/COLOR][/URL][/CENTER]'."\n\n";
    }

    $o .= nySig($aN, $aId, $aR);
    $o .= nyFooter();
    return $o;
}

function genVoteResults(array $d): string {
    $num    = str($d['number']    ?? '');
    $body   = str($d['body']      ?? 'legislature');
    $subj   = str($d['subject']   ?? '');
    $dt     = str($d['date']      ?? '');
    $result = str($d['result']    ?? 'accepted');
    $cN     = str($d['chairName'] ?? ''); $cId = str($d['chairId']   ?? '');
    $cR     = str($d['chairRole'] ?? ''); $sig = str($d['signature'] ?? '');
    $opts   = (array)($d['options'] ?? []);
    $voters = (array)($d['voters']  ?? []);

    $bodyLabels = [
        'legislature' => 'Легислатура Штата Нью-Йорк',
        'senate'      => 'Сенат Штата Нью-Йорк',
        'house'       => 'Ассамблея Штата Нью-Йорк',
        'committee'   => 'Комитет Легислатуры',
    ];
    $bodyLabel = $bodyLabels[$body] ?? 'Легислатура Штата Нью-Йорк';

    $resultMap = [
        'accepted'  => ['✅ ПРИНЯТО',   '#2ECC71'],
        'rejected'  => ['❌ ОТКЛОНЕНО', '#B03A2E'],
        'no-quorum' => ['⚠️ НЕТ КВОРУМА','#E8A000'],
    ];
    [$resLabel, $resColor] = $resultMap[$result] ?? $resultMap['accepted'];

    $o  = nyHeader('Итоги голосования '.$num, 'Vote Results '.$num, 'Итоги Голосования  ·  Vote Results');
    $o .= '[CENTER][COLOR='.$resColor.'][B][SIZE=5]'.$resLabel.'[/SIZE][/B][/COLOR][/CENTER]'."\n\n";
    $o .= metaRow([['Орган', $bodyLabel], ['Предмет', $subj], ['Дата', $dt]]);

    if (!empty($opts)) {
        $o .= secHead('Результаты по вариантам', '📊');
        $total = array_sum(array_column($opts, 'count'));
        foreach ($opts as $opt) {
            $oName  = str($opt['name']  ?? '');
            $oCount = (int)($opt['count'] ?? 0);
            $pct    = $total > 0 ? round($oCount / $total * 100) : 0;
            $bar    = str_repeat('█', (int)($pct / 5)).str_repeat('░', 20 - (int)($pct / 5));
            $o .= '[INDENT][B]'.$oName.'[/B]  —  [COLOR=#FFB81C][B]'.$oCount.'[/B][/COLOR] голос(ов)  ('.$pct.'%)'."\n";
            $o .= '[COLOR=#5B9BD5]'.$bar.'[/COLOR][/INDENT]'."\n";
        }
        $o .= "\n";
    }

    if (!empty($voters)) {
        $o .= secHead('Проголосовавшие', '🗳');
        $o .= '[TABLE]'."\n";
        $o .= '[TR][TH][COLOR=#002D72]№[/COLOR][/TH][TH][COLOR=#002D72]Имя[/COLOR][/TH][TH][COLOR=#002D72]Голос[/COLOR][/TH][/TR]'."\n";
        foreach ($voters as $i => $v) {
            $vN    = uLink(str($v['name'] ?? ''), str($v['id'] ?? ''));
            $vV    = str($v['vote'] ?? '');
            $vCol  = match(true) {
                mb_stripos($vV, 'за')      !== false => '#2ECC71',
                mb_stripos($vV, 'против')  !== false => '#B03A2E',
                mb_stripos($vV, 'воздерж') !== false => '#E8A000',
                default => '#9E9E9E'
            };
            $o .= '[TR][TD]'.($i+1).'[/TD][TD]'.$vN.'[/TD][TD][COLOR='.$vCol.'][B]'.$vV.'[/B][/COLOR][/TD][/TR]'."\n";
        }
        $o .= '[/TABLE]'."\n\n";
    }

    $o .= nySig($cN, $cId, $cR, $sig);
    $o .= nyFooter();
    return $o;
}

function genReception(array $d): string {
    $org   = str($d['org']         ?? 'Легислатура Штата Нью-Йорк');
    $nameE = str($d['nameEn']      ?? '');
    $desc  = str($d['desc']        ?? '');
    $funcs = str($d['functions']   ?? '');
    $gId   = str($d['groupId']     ?? '');
    $oRole = str($d['officerRole'] ?? '');

    $o  = nyHeader($org, $nameE, 'Приёмная / Канцелярия  ·  Reception');
    if ($desc !== '') {
        $o .= secHead('О приёмной', '🏛');
        $o .= '[INDENT]'.$desc.'[/INDENT]'."\n\n";
    }

    if ($funcs !== '') {
        $items = array_filter(array_map('trim', explode(',', $funcs)));
        $o .= secHead('Функции', '📋');
        foreach ($items as $f) {
            $o .= '[INDENT]◆ '.$f.'[/INDENT]'."\n";
        }
        $o .= "\n";
    }

    if ($oRole !== '') {
        $o .= secHead('Ответственное лицо', '👤');
        $o .= '[INDENT]';
        if ($gId !== '') $o .= '[USERGROUP='.$gId.']'.$oRole.'[/USERGROUP]';
        else             $o .= '[B]'.$oRole.'[/B]';
        $o .= '[/INDENT]'."\n\n";
    }

    $o .= nyFooter();
    return $o;
}

function genLandmark(array $d): string {
    $ru   = str($d['nameRu']      ?? ''); $en = str($d['nameEn'] ?? '');
    $hImg = str($d['headerImage'] ?? '');
    $blocks= (array)($d['blocks'] ?? []);
    $qt   = str($d['quote']       ?? ''); $qa = str($d['quoteAuthor'] ?? '');

    $o = nyHeader($ru, $en, 'Достопримечательность  ·  Landmark');
    if ($hImg !== '') $o .= '[CENTER][IMG]'.$hImg.'[/IMG][/CENTER]'."\n\n";

    foreach ($blocks as $b) {
        $bT  = str($b['title'] ?? ''); $bTx = str($b['text'] ?? '');
        $bIm = (array)($b['images'] ?? []);
        if ($bT !== '') $o .= secHead($bT);
        if ($bTx !== '') $o .= '[INDENT]'.$bTx.'[/INDENT]'."\n\n";
        foreach ($bIm as $img) {
            $img = trim($img);
            if ($img !== '') $o .= '[CENTER][IMG]'.$img.'[/IMG][/CENTER]'."\n";
        }
        if (!empty($bIm)) $o .= "\n";
    }

    if ($qt !== '') {
        $o .= dGold()."\n";
        $o .= '[CENTER][I][COLOR=#5B9BD5]«'.$qt.'»[/COLOR][/I]';
        if ($qa !== '') $o .= "\n[COLOR=#9E9E9E]— '.$qa.'[/COLOR]';
        $o .= '[/CENTER]'."\n".dGold()."\n";
    }

    $o .= nyFooter();
    return $o;
}

function genHall(array $d): string {
    $ru   = str($d['name']        ?? ''); $en  = str($d['nameEn']     ?? '');
    $img  = str($d['headerImage'] ?? ''); $dsc = str($d['desc']       ?? '');
    $links= (array)($d['links']   ?? []);

    $o = nyHeader($ru, $en, 'Зал  ·  Hall');
    if ($img !== '') $o .= '[CENTER][IMG]'.$img.'[/IMG][/CENTER]'."\n\n";
    if ($dsc !== '') {
        $o .= secHead('Описание');
        $o .= '[INDENT]'.$dsc.'[/INDENT]'."\n\n";
    }

    if (!empty($links)) {
        $o .= secHead('Навигация', '🔗');
        foreach ($links as $lk) {
            $lT = str($lk['title'] ?? ''); $lU = str($lk['url']   ?? '');
            $lI = str($lk['image'] ?? '');
            if ($lU !== '' && $lT !== '') {
                $o .= '[INDENT]';
                if ($lI !== '') $o .= '[URL='.$lU.'][IMG]'.$lI.'[/IMG][/URL]  ';
                $o .= '[URL='.$lU.'][COLOR=#5B9BD5][B]➤ '.$lT.'[/B][/COLOR][/URL]';
                $o .= '[/INDENT]'."\n";
            }
        }
        $o .= "\n";
    }

    $o .= nyFooter();
    return $o;
}

function genCourt(array $d): string {
    $num   = str($d['number']    ?? ''); $dt    = str($d['date']      ?? '');
    $case  = str($d['case']      ?? ''); $judge = str($d['judge']     ?? '');
    $judId = str($d['judgeId']   ?? ''); $judR  = str($d['judgeRole'] ?? '');
    $sig   = str($d['signature'] ?? ''); $res   = str($d['verdict']   ?? '');
    $useSt = (bool)($d['useStructure'] ?? false);
    $txt   = str($d['simpleText'] ?? ''); $art = $d['articles'] ?? [];

    $o  = nyHeader('Решение Суда '.$num, 'Court Decision '.$num, 'Решение Суда Штата Нью-Йорк  ·  Court Decision');
    $o .= metaRow([['№ дела', $case], ['Дата', $dt]]);

    if ($res !== '') {
        $o .= '[CENTER][COLOR='.sColor($res).'][B][SIZE=5]⚖️ '.mb_strtoupper($res,'UTF-8').'[/SIZE][/B][/COLOR][/CENTER]'."\n\n";
    }

    $o .= dGold()."\n\n";
    $o .= ($useSt && !empty($art)) ? nyArticles($art) : ($txt !== '' ? '[INDENT]'.$txt.'[/INDENT]'."\n\n" : '');
    $o .= nySig($judge, $judId, $judR, $sig);
    $o .= nyFooter();
    return $o;
}

function genRegistry(array $d): string {
    $title   = str($d['title']   ?? 'Реестр');
    $desc    = str($d['desc']    ?? '');
    $cols    = array_filter(array_map('trim', explode(',', str($d['columns'] ?? ''))));
    $rows    = (array)($d['rows'] ?? []);

    $o  = nyHeader($title, '', 'Официальный Реестр  ·  Official Registry');
    if ($desc !== '') $o .= '[INDENT][I]'.$desc.'[/I][/INDENT]'."\n\n";

    if (!empty($cols)) {
        $o .= '[TABLE]'."\n".'[TR]';
        foreach ($cols as $c) $o .= '[TH][COLOR=#002D72][B]'.$c.'[/B][/COLOR][/TH]';
        $o .= '[/TR]'."\n";

        foreach ($rows as $row) {
            $cells = array_map('trim', explode(',', str($row)));
            $o .= '[TR]';
            foreach ($cells as $cell) $o .= '[TD]'.uRef($cell).'[/TD]';
            $o .= '[/TR]'."\n";
        }
        $o .= '[/TABLE]'."\n\n";
    }

    $o .= nyFooter();
    return $o;
}

/* ════════════════════════════════════════════════════════════
   ROUTER
   ════════════════════════════════════════════════════════════ */

try {
    $output = match($tpl) {
        'law'          => genLaw($data),
        'resolution'   => genResolution($data),
        'executive'    => genExecutive($data),
        'mandate'      => genMandate($data),
        'session'      => genSession($data),
        'meeting'      => genMeeting($data),
        'vote-open'    => genVoteOpen($data),
        'vote-results' => genVoteResults($data),
        'reception'    => genReception($data),
        'landmark'     => genLandmark($data),
        'hall'         => genHall($data),
        'court'        => genCourt($data),
        'registry'     => genRegistry($data),
        default        => throw new InvalidArgumentException('Unknown template: '.$tpl)
    };
    echo json_encode(['success' => true, 'output' => $output], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
