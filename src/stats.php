<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function socRequire($relative, $fallback) {
    $primary = __DIR__ . '/' . $relative;
    $secondary = __DIR__ . '/' . $fallback;
    if (file_exists($primary)) {
        require_once $primary;
        return;
    }
    if (file_exists($secondary)) {
        require_once $secondary;
    }
}

socRequire('parsers/parser.php', 'parser.php');
socRequire('parsers/parser_skanowanie_wew.php', 'parser_skanowanie_wew.php');
socRequire('parsers/parser_host_logowanie.php', 'parser_host_logowanie.php');
socRequire('parsers/parser_skanowanie_zew.php', 'parser_skanowanie_zew.php');
socRequire('parsers/parser_odrzuconych_polaczen_wew.php', 'parser_odrzuconych_polaczen_wew.php');
socRequire('parsers/parser_odrzucownych_polaczen_zew.php', 'parser_odrzucownych_polaczen_zew.php');
socRequire('parsers/parser_polaczen_niestandardowe_porty.php', 'parser_polaczen_niestandardowe_porty.php');
socRequire('parsers/parser_uzytkownicy_bledne_logowanie.php', 'parser_uzytkownicy_bledne_logowanie.php');

$danePath = __DIR__ . '/dane/';
$period = isset($_GET['period']) ? (int)$_GET['period'] : 7;
if (!in_array($period, [3, 7, 30], true)) $period = 7;

function socCleanLabel($value, $fallback = 'Nieznany') {
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\xC2\xA0", '&nbsp;', 'Â ', 'Â '], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    $value = preg_replace('/\s*[-–]?\s*\([\d\s,]+\)\s*$/u', '', $value);
    $value = trim($value, " \t\n\r\0\x0B-–—");
    return $value !== '' ? $value : $fallback;
}

function socAdd(&$bucket, $label, $count, $fallback = 'Nieznany') {
    $label = socCleanLabel($label, $fallback);
    if ($label === '-' || $label === '') return;
    $bucket[$label] = ($bucket[$label] ?? 0) + max(1, (int)$count);
}

function socAddTerms(&$bucket, $rawText, $fallbackCount = 1, $fallbackLabel = 'Nieznany', $skipNoise = true) {
    $rawText = html_entity_decode((string)$rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rawText = str_replace(["\xC2\xA0", '&nbsp;', 'Â ', 'Â '], ' ', $rawText);
    $rawText = preg_replace('/\s+/u', ' ', trim($rawText));

    $added = false;
    if (preg_match_all('/([^()\n]+?)\s*\(([\d\s,]+)\)/u', $rawText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $label = socCleanLabel($match[1], $fallbackLabel);
            if ($skipNoise && preg_match('/^(reserved|unknown|nieznany|brak|dowolny)$/i', $label)) continue;
            $count = (int)str_replace([' ', ','], '', $match[2]);
            if ($label !== '') {
                $bucket[$label] = ($bucket[$label] ?? 0) + max(1, $count);
                $added = true;
            }
        }
    }

    if (!$added && $rawText !== '') {
        $label = socCleanLabel($rawText, $fallbackLabel);
        if (!($skipNoise && preg_match('/^(reserved|unknown|nieznany|brak|dowolny)$/i', $label))) {
            $bucket[$label] = ($bucket[$label] ?? 0) + max(1, (int)$fallbackCount);
        }
    }
}

function socMergeHourly(&$target, $source) {
    for ($i = 0; $i < 24; $i++) {
        $target[$i] += (int)($source[$i] ?? 0);
    }
}

function socHourlyFromItem($item) {
    $hours = array_fill(0, 24, 0);
    if (!empty($item['hourly_stats']) && is_array($item['hourly_stats'])) {
        foreach ($item['hourly_stats'] as $hour => $count) {
            $hour = (int)$hour;
            if ($hour >= 0 && $hour <= 23) $hours[$hour] += (int)$count;
        }
    }
    if (!empty($item['time_generated_list']) && is_array($item['time_generated_list'])) {
        foreach ($item['time_generated_list'] as $timeItem) {
            $hour = isset($timeItem['hour']) ? (int)$timeItem['hour'] : -1;
            $count = (int)($timeItem['count'] ?? 1);
            if ($hour >= 0 && $hour <= 23) $hours[$hour] += max(1, $count);
        }
    }
    if (array_sum($hours) === 0 && !empty($item['time_generated'])) {
        $raw = (string)$item['time_generated'];
        if (preg_match_all('/\d{4}-\d{2}-\d{2}\s+(\d{2}):\d{2}:\d{2}(?:\s*\(([\d\s,]+)\))?/u', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $hour = (int)$match[1];
                $count = isset($match[2]) && trim($match[2]) !== '' ? (int)str_replace([' ', ','], '', $match[2]) : 1;
                if ($hour >= 0 && $hour <= 23) $hours[$hour] += max(1, $count);
            }
        }
    }
    return $hours;
}

function socFormatMb($mb) {
    $mb = (float)$mb;
    if ($mb >= 1000000) return number_format($mb / 1000000, 2, ',', ' ') . ' TB';
    if ($mb >= 1000) return number_format($mb / 1000, 2, ',', ' ') . ' GB';
    return number_format($mb, 1, ',', ' ') . ' MB';
}

function socTop($items, $limit = 10) {
    arsort($items);
    return array_slice($items, 0, $limit, true);
}

function socReportParserForFile($fullPath) {
    $filename = basename($fullPath);
    if (mb_stripos($filename, 'transfer') !== false || mb_stripos($filename, 'transfe') !== false) {
        return ['transfer', class_exists('RaportParser') ? new RaportParser($fullPath, 'all') : null];
    }
    if (mb_stripos($filename, 'wewnetrzne_skanujace_porty') !== false) {
        return ['skanowanie_port_host_wew', class_exists('RaportWewnSkanujaceParser') ? new RaportWewnSkanujaceParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Hosty_z_bednymi_probami_logowania') !== false) {
        return ['host_logowanie', class_exists('RaportHostLogowanieParser') ? new RaportHostLogowanieParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'zewnetrzne_skanujace_porty') !== false) {
        return ['skanowanie_port_host_zew', class_exists('RaportZewnSkanujaceParser') ? new RaportZewnSkanujaceParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_wewnetrznych') !== false) {
        return ['skanowanie_odrzucone_host_wew', class_exists('RaportOdrzuconeWewnParser') ? new RaportOdrzuconeWewnParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_zewnetrznych') !== false) {
        return ['skanowanie_odrzucone_host_zew', class_exists('RaportOdrzuconeZewnParser') ? new RaportOdrzuconeZewnParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Poaczenia_wychodzace') !== false) {
        return ['skanowanie_niestandardowe_porty', class_exists('RaportWychodzaceNiestandardoweParser') ? new RaportWychodzaceNiestandardoweParser($fullPath) : null];
    }
    if (mb_stripos($filename, 'Uzytkownicy_z_bednymi_probami_logowania') !== false) {
        return ['uzytkownicy_logowanie', class_exists('RaportBedneLogowaniaUzytkownicyParser') ? new RaportBedneLogowaniaUzytkownicyParser($fullPath) : null];
    }
    return ['unknown', null];
}

$dates = [];
if (file_exists($danePath)) {
    foreach (array_diff(scandir($danePath), ['.', '..']) as $folder) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $folder) && is_dir($danePath . $folder)) {
            $dates[] = $folder;
        }
    }
}
rsort($dates);
$latestDate = $dates[0] ?? null;
$startDate = $latestDate ? date('Y-m-d', strtotime($latestDate . ' -' . ($period - 1) . ' days')) : null;

$filesParsed = 0;
$filesFailed = 0;
$totalEvents = 0;
$transferHosts = [];
$failedUsers = [];
$attackCountries = [];
$hourCounts = array_fill(0, 24, 0);
$portCounts = [];
$appCounts = [];
$serviceCounts = [];
$filesByType = [];

foreach ($dates as $date) {
    if ($startDate && $date < $startDate) continue;
    $dir = $danePath . $date . '/';
    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $fullPath = realpath($dir . $file);
        if (!$fullPath || !is_file($fullPath) || strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'html') continue;

        [$type, $parser] = socReportParserForFile($fullPath);
        if (!$parser || !method_exists($parser, 'parse')) continue;

        try {
            $data = $parser->parse();
            $filesParsed++;
            $filesByType[$type] = ($filesByType[$type] ?? 0) + 1;
        } catch (Throwable $e) {
            $filesFailed++;
            continue;
        }

        if ($type === 'transfer') {
            foreach (($data['top_hosts'] ?? []) as $host) {
                $name = socCleanLabel(($host['ip'] ?? '') . (!empty($host['opis']) ? ' — ' . $host['opis'] : ''), 'Host');
                $transferHosts[$name] = ($transferHosts[$name] ?? 0) + (float)($host['suma_raw'] ?? 0);
                foreach (($host['events'] ?? []) as $event) {
                    if (!empty($event['time']) && ($ts = strtotime($event['time']))) {
                        $hourCounts[(int)date('H', $ts)]++;
                    }
                }
                foreach (($host['uslugi'] ?? []) as $service) {
                    socAdd($serviceCounts, $service['nazwa'] ?? 'Nieznana', $service['zdarzenia'] ?? 1, 'Nieznana');
                }
                foreach (($host['aplikacje'] ?? []) as $app) {
                    socAdd($appCounts, $app['nazwa'] ?? 'Nieznana', $app['zdarzenia'] ?? 1, 'Nieznana');
                }
            }
            $eventsText = (string)($data['meta']['liczba_zdarzen'] ?? '0');
            $totalEvents += (int)preg_replace('/\D+/', '', $eventsText);
            continue;
        }

        if (isset($data['meta']['suma_zdarzen'])) {
            $totalEvents += (int)$data['meta']['suma_zdarzen'];
        }

        foreach (($data['records'] ?? []) as $record) {
            $events = max(1, (int)($record['events_count'] ?? 1));
            socAdd($failedUsers, $record['user'] ?? '-', $events, 'Użytkownik');
            socAdd($serviceCounts, $record['service_name'] ?? '-', $events, 'Nieznana');
            socMergeHourly($hourCounts, socHourlyFromItem($record));
        }

        foreach (($data['scans'] ?? []) as $scan) {
            $events = max(1, (int)($scan['event_value_count'] ?? $scan['eventmap_value_count'] ?? $scan['events_count'] ?? 1));

            socAddTerms($attackCountries, $scan['source_country_raw'] ?? ($scan['source_country'] ?? ''), $events, 'Nieznany', true);
            socAddTerms($portCounts, $scan['dest_port_raw'] ?? ($scan['dest_port'] ?? ''), $events, 'Dowolny', true);
            socAddTerms($serviceCounts, $scan['service_raw'] ?? ($scan['service'] ?? $scan['application_category'] ?? ''), $events, 'Nieznana', true);
            socAddTerms($appCounts, $scan['application_raw'] ?? ($scan['application'] ?? ''), $events, 'Nieznana', true);
            socMergeHourly($hourCounts, socHourlyFromItem($scan));
        }
    }
}

$topTransferHosts = socTop($transferHosts);
$topFailedUsers = socTop($failedUsers);
$topAttackCountries = socTop($attackCountries);
$topPorts = socTop($portCounts);
$topApps = socTop($appCounts);
$topServices = socTop($serviceCounts);
$topHoursRaw = [];
foreach ($hourCounts as $hour => $count) $topHoursRaw[sprintf('%02d:00', $hour)] = $count;
$topHours = socTop($topHoursRaw);

function socPercentValue($value, $total) {
    $total = (float)$total;
    if ($total <= 0) return 0;
    return round(((float)$value / $total) * 100, 1);
}

function socFormatStatValue($value, $unit = 'zd.', $isTransfer = false) {
    if ($isTransfer) return socFormatMb($value);
    return number_format((int)$value, 0, ',', ' ') . ' ' . $unit;
}

function socPalette($idx) {
    $colors = [
        '#2563eb', '#7c3aed', '#dc2626', '#f97316', '#16a34a',
        '#0891b2', '#db2777', '#65a30d', '#0f766e', '#475569'
    ];
    return $colors[$idx % count($colors)];
}

function renderDonutCard($id, $title, $subtitle, $items, $unit = 'zd.', $isTransfer = false, $icon = 'pie-chart') {
    $items = socTop($items, 10);
    $total = array_sum($items);
    $circumference = 2 * pi() * 82;
    ?>
    <div class="pdf-card rounded-[1.35rem] border border-slate-200/70 bg-white p-6 shadow-sm ring-1 ring-slate-100/70">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="<?php echo htmlspecialchars($icon); ?>" class="h-4.5 w-4.5"></i>
                    </div>
                    <h3 class="text-sm font-black uppercase tracking-wide text-slate-950"><?php echo htmlspecialchars($title); ?></h3>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-400"><?php echo htmlspecialchars($subtitle); ?></p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-extrabold text-slate-500">TOP 10</span>
        </div>

        <?php if (empty($items) || $total <= 0): ?>
            <div class="flex h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 text-center">
                <i data-lucide="circle-off" class="mb-2 h-8 w-8 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-400">Brak danych dla tego wykresu</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[310px_1fr] lg:items-center">
                <div class="relative mx-auto h-[310px] w-[310px]">
                    <svg viewBox="0 0 240 240" class="h-full w-full drop-shadow-sm" role="img" aria-label="<?php echo htmlspecialchars($title); ?>">
                        <circle cx="120" cy="120" r="82" fill="none" stroke="#f1f5f9" stroke-width="34"></circle>
                        <?php
                        $offset = 0.0;
                        $idxColor = 0;
                        foreach ($items as $label => $value):
                            $dash = ((float)$value / (float)$total) * $circumference;
                            $gap = $circumference - $dash;
                            $color = socPalette($idxColor++);
                        ?>
                            <circle cx="120" cy="120" r="82" fill="none"
                                    stroke="<?php echo $color; ?>" stroke-width="34" stroke-linecap="round"
                                    stroke-dasharray="<?php echo number_format($dash, 3, '.', ''); ?> <?php echo number_format($gap, 3, '.', ''); ?>"
                                    stroke-dashoffset="-<?php echo number_format($offset, 3, '.', ''); ?>"
                                    transform="rotate(-90 120 120)"></circle>
                        <?php
                            $offset += $dash;
                        endforeach;
                        ?>
                        <circle cx="120" cy="120" r="54" fill="white"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <div class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Suma</div>
                        <div class="mt-1 max-w-[150px] truncate text-xl font-black text-slate-950" title="<?php echo htmlspecialchars(socFormatStatValue($total, $unit, $isTransfer)); ?>">
                            <?php echo htmlspecialchars(socFormatStatValue($total, $unit, $isTransfer)); ?>
                        </div>
                        <div class="mt-1 text-[11px] font-bold text-blue-600">100%</div>
                    </div>
                </div>

                <div class="space-y-2.5">
                    <?php
                    $i = 0;
                    foreach ($items as $label => $value):
                        $percent = socPercentValue($value, $total);
                        $color = socPalette($i);
                    ?>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: <?php echo $color; ?>"></span>
                                    <span class="truncate text-xs font-extrabold text-slate-800" title="<?php echo htmlspecialchars($label); ?>">
                                        <?php echo ($i + 1); ?>. <?php echo htmlspecialchars($label); ?>
                                    </span>
                                </div>
                                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-black text-blue-700 shadow-sm">
                                    <?php echo number_format($percent, 1, ',', ' '); ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full" style="width: <?php echo min(100, $percent); ?>%; background: <?php echo $color; ?>"></div>
                                </div>
                                <div class="w-28 shrink-0 text-right text-[11px] font-black text-slate-700">
                                    <?php echo htmlspecialchars(socFormatStatValue($value, $unit, $isTransfer)); ?>
                                </div>
                            </div>
                        </div>
                    <?php $i++; endforeach; ?>
                </div>
            </div>

            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="w-12 px-4 py-3">#</th>
                            <th class="px-4 py-3">Nazwa</th>
                            <th class="px-4 py-3 text-right">Wartość</th>
                            <th class="px-4 py-3 text-right">Udział</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row = 1; foreach ($items as $label => $value): ?>
                            <tr class="border-t border-slate-50">
                                <td class="px-4 py-2.5 text-xs font-black text-slate-400"><?php echo $row++; ?></td>
                                <td class="px-4 py-2.5 text-xs font-bold text-slate-700"><span class="line-clamp-1" title="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars($label); ?></span></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs font-black text-blue-700"><?php echo htmlspecialchars(socFormatStatValue($value, $unit, $isTransfer)); ?></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs font-black text-slate-900"><?php echo number_format(socPercentValue($value, $total), 1, ',', ' '); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

$printMode = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki SOC - Logsign</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .pdf-card { break-inside: avoid; page-break-inside: avoid; }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            header { position: static !important; }
            main { padding: 0 !important; max-width: none !important; }
            .pdf-card { box-shadow: none !important; border-color: #e2e8f0 !important; margin-bottom: 12px; }
            .print-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px !important; }
        }
    </style>
</head>
<body class="text-slate-800 antialiased">
<header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/85 backdrop-blur-md">
    <div class="flex h-16 items-center justify-between px-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-200">
                <i data-lucide="pie-chart" class="h-6 w-6"></i>
            </div>
            <div>
                <h1 class="text-lg font-black leading-none text-slate-900">Statystyki SOC z raportów Logsign</h1>
                <span class="font-mono text-xs font-medium text-slate-400">Zakres: ostatnie <?php echo (int)$period; ?> dni względem najnowszego raportu</span>
            </div>
        </div>
        <div class="no-print flex items-center gap-2">
            <a href="index.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Raporty
            </a>
            <a href="stats.php?period=<?php echo (int)$period; ?>&print=1" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-rose-500">
                <i data-lucide="file-down" class="h-4 w-4"></i>
                Exportuj raport do PDF
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-[1780px] p-6 lg:p-8">
    <section class="mb-6 flex flex-col justify-between gap-4 rounded-[1.35rem] border border-slate-100 bg-white p-5 shadow-sm lg:flex-row lg:items-center">
        <div>
            <h2 class="text-2xl font-black text-slate-950">Dashboard zbiorczy</h2>
            <p class="mt-1 text-sm text-slate-500">Agregacja ze wszystkich plików HTML w katalogu <span class="font-mono">/dane</span> dla wybranego zakresu.</p>
            <p class="mt-1 text-xs font-bold text-slate-400">
                Najnowszy raport: <?php echo htmlspecialchars($latestDate ?? 'brak'); ?> · Start zakresu: <?php echo htmlspecialchars($startDate ?? 'brak'); ?>
            </p>
        </div>
        <div class="no-print flex flex-wrap gap-2">
            <?php foreach ([3, 7, 30] as $p): ?>
                <a href="stats.php?period=<?php echo $p; ?>" class="rounded-xl px-4 py-2 text-sm font-black transition <?php echo $period === $p ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'; ?>">
                    Ostatnie <?php echo $p; ?> dni
                </a>
            <?php endforeach; ?>
            <a href="stats.php?period=<?php echo (int)$period; ?>&print=1" target="_blank" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-black text-white shadow-sm hover:bg-rose-500">
                PDF
            </a>
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[1.25rem] border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Przetworzone pliki</p><h3 class="mt-2 text-3xl font-black text-slate-900"><?php echo number_format($filesParsed, 0, ',', ' '); ?></h3></div>
        <div class="rounded-[1.25rem] border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Łącznie zdarzeń</p><h3 class="mt-2 text-3xl font-black text-red-600"><?php echo number_format($totalEvents, 0, ',', ' '); ?></h3></div>
        <div class="rounded-[1.25rem] border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Zakres danych</p><h3 class="mt-2 text-xl font-black text-blue-600"><?php echo htmlspecialchars(($startDate ?? '-') . ' → ' . ($latestDate ?? '-')); ?></h3></div>
        <div class="rounded-[1.25rem] border border-slate-100 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-wider text-slate-400">Błędy parsowania</p><h3 class="mt-2 text-3xl font-black text-amber-600"><?php echo number_format($filesFailed, 0, ',', ' '); ?></h3></div>
    </section>

    <?php if ($filesParsed === 0): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center shadow-sm">
            <i data-lucide="folder-search" class="mx-auto mb-3 h-10 w-10 text-slate-300"></i>
            <h3 class="text-lg font-bold text-slate-900">Brak danych do statystyk</h3>
            <p class="mt-2 text-sm text-slate-500">Wgraj raporty ZIP lub sprawdź, czy katalog <span class="font-mono">/dane/YYYY-MM-DD</span> zawiera pliki HTML.</p>
        </div>
    <?php else: ?>
        <section class="print-grid grid grid-cols-1 gap-6 xl:grid-cols-2">
            <?php renderDonutCard('transferHosts', 'Top 10 hostów z największym transferem', 'Wykres kołowy + procentowy udział transferu', $topTransferHosts, 'MB', true, 'server'); ?>
            <?php renderDonutCard('failedUsers', 'Top 10 użytkowników z błędnymi próbami logowania', 'Wykres kołowy + procentowy udział prób', $topFailedUsers, 'prób', false, 'user-x'); ?>
            <?php renderDonutCard('attackCountries', 'Top 10 krajów źródłowych ataków', 'Wykres kołowy + procentowy udział zdarzeń', $topAttackCountries, 'zd.', false, 'globe-2'); ?>
            <?php renderDonutCard('hours', 'Top 10 godzin z największą ilością zdarzeń', 'Wykres kołowy + procentowy udział godzin', $topHours, 'zd.', false, 'clock-3'); ?>
            <?php renderDonutCard('ports', 'Top 10 portów', 'Wykres kołowy + tabela z procentami', $topPorts, 'zd.', false, 'unplug'); ?>
            <?php renderDonutCard('apps', 'Top 10 aplikacji', 'Wykres kołowy + tabela z procentami', $topApps, 'zd.', false, 'boxes'); ?>
            <?php renderDonutCard('services', 'Top 10 usług', 'Wykres kołowy + tabela z procentami', $topServices, 'zd.', false, 'cpu'); ?>
        </section>
    <?php endif; ?>
</main>

<script>
lucide.createIcons();
<?php if ($printMode): ?>
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 450);
});
<?php endif; ?>
</script>
</body>
</html>
