<?php
/**
 * Główny plik Dashboardu aplikacji.
 * Odpowiada za wyświetlanie drzewa plików z katalogu /dane/, dynamiczną detekcję
 * typu wybranego raportu oraz renderowanie dedykowanego interfejsu (Transfer lub Skanowanie).
 * * Powiązany z fizycznymi nazwami plików parserów na serwerze użytkownika.
 */

require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/parser_skanowanie_wew.php';
require_once __DIR__ . '/parser_bledne_proby_logowania.php';
require_once __DIR__ . '/parser_skanowanie_zew.php';
require_once __DIR__ . '/parser_odrzuconych_polaczen_wew.php';
require_once __DIR__ . '/parser_odrzucownych_polaczen_zew.php'; // Uwzględniono literówkę z serwera
require_once __DIR__ . '/parser_polaczen_niestandardowe_porty.php';
require_once __DIR__ . '/parser_uzytkownicy_bledne_logowanie.php';

$danePath = __DIR__ . '/dane/';
$selectedFile = isset($_GET['file']) ? $_GET['file'] : null;
$parsedData = null;
$reportType = 'transfer'; // Domyślny typ raportu

// Dynamiczny filtr dnia (domyślnie 'all' czyli Łącznie)
$filterDay = isset($_GET['filter_day']) ? $_GET['filter_day'] : 'all';

// Dynamiczny aktywny IP do ładowania w karcie analizy
$activeIp = isset($_GET['active_ip']) ? $_GET['active_ip'] : '';

// Pomocnicza funkcja do konwersji jednostek transferu na MB w celach wyliczania procentowego udziału na wykresie
if (!function_exists('convertToMb')) {
    function convertToMb($valueString) {
        $valueString = str_replace(' ', '', $valueString);
        $val = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $valueString));
        if (stripos($valueString, 'GB') !== false) {
            return $val * 1024;
        } elseif (stripos($valueString, 'KB') !== false) {
            return $val / 1024;
        } elseif (stripos($valueString, 'B') !== false && stripos($valueString, 'MB') === false) {
            return $val / (1024 * 1024);
        }
        return $val;
    }
}

// Obsługa usuwania katalogu z plikami
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dir' && isset($_POST['dir_name'])) {
    $dirToDelete = $_POST['dir_name'];
    $targetDir = realpath($danePath . $dirToDelete);

    // Zabezpieczenie przed Directory Traversal (tylko usuwanie wewnatrz katalogu /dane/)
    if ($targetDir && strpos($targetDir, realpath($danePath)) === 0 && is_dir($targetDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($targetDir);

        header("Location: index.php?delete_success=" . urlencode($dirToDelete));
        exit;
    }
}

// Budowanie drzewa plików i folderów z katalogu /dane/
$tree = [];
if (file_exists($danePath)) {
    $folders = array_diff(scandir($danePath), array('..', '.'));
    rsort($folders); // Najnowsze katalogi (daty) na górzę listy
    foreach ($folders as $folder) {
        if (is_dir($danePath . $folder)) {
            $files = array_diff(scandir($danePath . $folder), array('..', '.'));
            if (!empty($files)) {
                $tree[$folder] = $files;
            }
        }
    }
}

// Wybór pierwszego dostępnego pliku jako domyślny na start
if (!$selectedFile && !empty($tree)) {
    $firstFolder = array_key_first($tree);
    $firstFile = reset($tree[$firstFolder]);
    $selectedFile = $firstFolder . '/' . $firstFile;
}

// Parsowanie aktywnego pliku z automatycznym rozpoznawaniem jego zawartości i odpowiednim parserem
if ($selectedFile) {
    $fullPath = realpath($danePath . $selectedFile);
    // Zabezpieczenie przed Directory Traversal (tylko odczyt wewnątrz katalogu /dane/)
    if ($fullPath && strpos($fullPath, realpath($danePath)) === 0 && file_exists($fullPath)) {

        $filename = basename($selectedFile);

        // Precyzyjna, hierarchiczna detekcja parsera na podstawie unikalnej nazwy pliku HTML
        if (mb_stripos($filename, 'transfer') !== false || mb_stripos($filename, 'transfe') !== false) {
            $reportType = 'transfer';
            $parser = new RaportParser($fullPath, $filterDay);
        } elseif (mb_stripos($filename, 'wewnetrzne_skanujace_porty') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportWewnSkanujaceParser($fullPath);
        } elseif (mb_stripos($filename, 'Hosty_z_bednymi_probami_logowania') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportBedneLogowaniaHostyParser($fullPath);
        } elseif (mb_stripos($filename, 'zewnetrzne_skanujace_porty') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportZewnSkanujaceParser($fullPath);
        } elseif (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_wewnetrznych') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportOdrzuconeWewnParser($fullPath);
        } elseif (mb_stripos($filename, 'Odrzucone_poaczenia_z_hostow_zewnetrznych') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportOdrzuconeZewnParser($fullPath);
        } elseif (mb_stripos($filename, 'Poaczenia_wychodzace') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportWychodzaceNiestandardoweParser($fullPath);
        } elseif (mb_stripos($filename, 'Uzytkownicy_z_bednymi_probami_logowania') !== false) {
            $reportType = 'skanowanie';
            $parser = new RaportBedneLogowaniaUzytkownicyParser($fullPath);
        } else {
            // Bezpieczny fallback do parsera transferów
            $reportType = 'transfer';
            $parser = new RaportParser($fullPath, $filterDay);
        }

        // Pobranie danych bazowych bezpośrednio z dedykowanego parsera (Brak dublowania parsowania!)
        $parsedData = $parser->parse();
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analityka Sieciowa - Panel Raportów</title>
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Ikony Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <!-- Górny pasek nawigacyjny -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md">
        <div class="flex h-16 items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-200">
                    <i data-lucide="shield-check" class="h-6 w-6"></i>
                </div>
                <div>
                    <h1 class="font-bold text-slate-900 leading-none text-lg">Raportownik Sieciowy</h1>
                    <span class="text-xs text-slate-400 font-medium font-mono">Status: Aktywny</span>
                </div>
            </div>
            <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors">
                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                Wgraj paczkę ZIP
            </button>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-4rem)]">

        <!-- Drzewo Archiwum Raportów (Sidebar) -->
        <aside class="w-80 border-r border-slate-200 bg-white p-6 shrink-0 hidden md:block">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">Archiwum Raportów</h2>

            <?php if (empty($tree)): ?>
                <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center">
                    <i data-lucide="folder-open" class="mx-auto h-8 w-8 text-slate-300 mb-2"></i>
                    <p class="text-sm text-slate-500 font-medium">Brak wgranych raportów.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-[calc(100vh-10rem)] overflow-y-auto pr-1">
                    <?php foreach ($tree as $date => $files): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-2">
                            <div class="flex items-center justify-between p-2">
                                <button onclick="toggleFolder('folder-<?php echo $date; ?>')" class="flex items-center gap-2 font-semibold text-slate-700 hover:text-blue-600 text-sm">
                                    <i data-lucide="calendar" class="h-4 w-4 text-blue-500"></i>
                                    <?php echo $date; ?>
                                </button>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="confirmDeleteDir('<?php echo htmlspecialchars($date); ?>')" class="rounded-lg p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 transition-colors" title="Usuń ten katalog">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    </button>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition-transform duration-200 cursor-pointer" id="icon-folder-<?php echo $date; ?>" onclick="toggleFolder('folder-<?php echo $date; ?>')"></i>
                                </div>
                            </div>

                            <div class="mt-1 space-y-1 pl-6 pr-2 pb-1" id="folder-<?php echo $date; ?>">
                                <?php foreach ($files as $file):
                                    $filePathValue = $date . '/' . $file;
                                    $isActive = ($selectedFile === $filePathValue);

                                    // Spójna detekcja ikon w pasku bocznym na podstawie nazwy pliku
                                    $isScanFile = true;
                                    if (mb_stripos($file, 'transfer') !== false || mb_stripos($file, 'transfe') !== false) {
                                        $isScanFile = false;
                                    }
                                ?>
                                    <a href="index.php?file=<?php echo urlencode($filePathValue); ?>" class="group flex items-center justify-between rounded-lg p-2 text-xs font-medium transition-all <?php echo $isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-100/80 hover:text-slate-900'; ?>">
                                        <span class="truncate pr-2" title="<?php echo htmlspecialchars($file); ?>">
                                            <?php echo htmlspecialchars(strlen($file) > 28 ? substr($file, 0, 25) . '...' : $file); ?>
                                        </span>
                                        <i data-lucide="<?php echo $isScanFile ? 'shield-alert' : 'file-text'; ?>" class="h-3.5 w-3.5 shrink-0 <?php echo $isScanFile ? 'text-red-500 opacity-100' : 'text-slate-400 opacity-60'; ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Główny obszar roboczy -->
        <main class="flex-1 p-8 overflow-y-auto">

            <?php if (isset($_GET['delete_success'])): ?>
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-800 shadow-sm">
                    <i data-lucide="trash-2" class="h-5 w-5 text-amber-600"></i>
                    <div>
                        <span class="font-semibold">Usunięto!</span> Katalog o dacie <b><?php echo htmlspecialchars($_GET['delete_success']); ?></b> wraz z raportami został pomyślnie skasowany.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['upload_success'])): ?>
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 shadow-sm">
                    <i data-lucide="check-circle" class="h-5 w-5 text-emerald-600"></i>
                    <div>
                        <span class="font-semibold">Import ukończony!</span> Pomyślnie zaimportowano <?php echo intval($_GET['upload_success']); ?> raportów sieciowych.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($parsedData): ?>

                <?php if ($reportType === 'transfer'): ?>
                    <!-- ========================================== -->
                    <!-- WIDOK 1: ANALITYKA TRANSFERU DOBOWEGO      -->
                    <!-- ========================================== -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Suma Transferu</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($parsedData['meta']['suma_transferu']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                                    <i data-lucide="arrow-left-right" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dane Pobrane (RX)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-emerald-600"><?php echo htmlspecialchars($parsedData['meta']['pobrane_rx']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600">
                                    <i data-lucide="download-cloud" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Dane Wysłane (TX)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-amber-600"><?php echo htmlspecialchars($parsedData['meta']['wyslane_tx']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-3 text-amber-600">
                                    <i data-lucide="upload-cloud" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Liczba Zdarzeń (Suma)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-violet-600"><?php echo htmlspecialchars($parsedData['meta']['liczba_zdarzen']?? ''); ?></h3>
                                </div>
                                <div class="rounded-xl bg-violet-50 p-3 text-violet-600">
                                    <i data-lucide="activity" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WYKRESY ZBIORCZE (HORYZONTALNY GRID 1x4 - 4 WYKRESY OBOK SIEBIE) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                        <!-- Wykres 1: Top 5 Najaktywniejszych Hostów -->
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Najaktywniejszych Hostów (Maksymalny Transfer)</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Hosts = array_slice($parsedData['top_hosts'], 0, 5);
                                //print_r($top5Hosts);
								
                                $maxHostTransfer = 0.001;
                                foreach ($top5Hosts as $h) {
                                    $mb = convertToMb($h['suma']);
                                    if ($mb > $maxHostTransfer) $maxHostTransfer = $mb;
                                }

                                if (empty($top5Hosts)): ?>
                                    <p class="text-xs text-slate-400 font-semibold py-4 text-center">Brak danych</p>
                                <?php else: ?>
                                    <?php foreach ($top5Hosts as $h):
									    
                                        $currentMb = convertToMb($h['suma'] ?? 0);

													$percent = $maxHostTransfer > 0
														? min(100, ($currentMb / $maxHostTransfer) * 100)
														: 0;

													$ip =trim(preg_replace('/\s*\([^)]*\)/', '', $h['ip'] ?? '')) ?? 'unknown';
													$opis = $h['opis'] ?? '';
                                                    //echo $h['opis'];
													$displayName = $ip;

													if (!empty($opis) &&
														$opis !== 'Brak nazwy (DHCP)' &&
														$opis !== 'Urządzenie DHCP'
													) {
														$opis;
													}
                                    ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span class="font-mono text-slate-900 truncate max-w-[150px] inline-block" title="<?php echo htmlspecialchars($displayName?? ''); ?>"><?php echo htmlspecialchars($displayName); ?></span><span><?php echo htmlspecialchars($opis); ?></span></span>
                                                <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($h['suma']?? 0); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Wykres 2: Top 5 Kierunków Docelowych -->
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Kierunków Docelowych (Zewnętrzne IP)</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Kierunki = array_slice($parsedData['selected_host']['kierunki'] ?? [], 0, 5);
                                if (empty($top5Kierunki)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="info" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych docelowych IP</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Kierunki as $k): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span class="font-mono text-blue-600"><?php echo htmlspecialchars($k['ip']); ?></span>
                                                <span class="text-emerald-600 font-bold"><?php echo htmlspecialchars($k['zdarzenia']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-600 rounded-full transition-all duration-500" style="width: <?php echo $k['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Wykres 3: Top 5 Krajów ze Zdarzeniami -->
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Krajów ze zdarzeniami (Geolokalizacja)</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Kraje = array_slice($parsedData['selected_host']['geolokalizacja'] ?? [], 0, 5);
                                if (empty($top5Kraje)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="globe" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Kraje as $k): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span>
                                                    <span class="text-slate-400 uppercase text-[10px] mr-1"><?php echo htmlspecialchars($k['prefiks']); ?></span>
                                                    <?php echo htmlspecialchars($k['kraj']); ?>
                                                </span>
                                                <span class="text-rose-600 font-bold"><?php echo htmlspecialchars($k['logi']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-rose-500 to-red-600 rounded-full transition-all duration-500" style="width: <?php echo $k['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Wykres 4: Top 5 Usług (Protokołów) -->
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-2 mb-6">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-600"></span>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">Top 5 Usług i Aplikacji (Ilość Zdarzeń)</h3>
                            </div>
                            <div class="space-y-4">
                                <?php
                                $top5Uslugi = array_slice($parsedData['selected_host']['uslugi'] ?? [], 0, 5);
                                if (empty($top5Uslugi)): ?>
                                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                                        <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                        <span class="text-xs font-semibold">Brak danych</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top5Uslugi as $u): ?>
                                        <div>
                                            <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                                                <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono"><?php echo htmlspecialchars($u['nazwa']); ?></span>
                                                <span class="text-amber-600 font-bold"><?php echo htmlspecialchars($u['zdarzenia']); ?></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-amber-500 to-orange-600 rounded-full transition-all duration-500" style="width: <?php echo $u['procent']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                    Top Hosty o Największym Transferze Dobowym
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">Lista najaktywniejszych adresów IP na podstawie dobowego transferu danych. Domyślnie prezentowane jest Top 5.</p>
                            </div>
                            <!-- 
                            INTERAKTYWNA SEKCJA WYBORU DNI RAPORTU
                          <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold shadow-xs">
                                <?php //foreach ($parsedData['meta']['available_days'] as $dayKey => $dayLabel): ?>
                                    <a href="index.php?file=<?php //echo urlencode($selectedFile); ?>&filter_day=<?php //echo urlencode($dayKey); ?>&active_ip=<?php //echo urlencode($activeIp); ?>"
                                       class="rounded-lg px-3 py-1.5 transition-all <?php //echo $filterDay === $dayKey ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'; ?>">
                                        <?php //echo htmlspecialchars($dayLabel); ?>
                                    </a>
                                <?php //endforeach; ?> 
                            </div>-->
                        </div>

                        <div class="overflow-x-auto font-sans">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400 font-sans">
                                        <th class="py-3 px-4 text-center">Pozycja</th>
                                        <th class="py-3 px-4">Adres IP źródłowy (Host)</th>
                                        <th class="py-3 px-4 text-right font-bold">Zdarzenia</th>
                                        <th class="py-3 px-4 text-right text-emerald-600 font-bold">Odebrane (RX)</th>
                                        <th class="py-3 px-4 text-right text-amber-600 font-bold">Wysłane (TX)</th>
                                        <th class="py-3 px-4 text-right font-bold">Łącznie (Suma)</th>
                                        <th class="py-3 px-4 font-bold">Wykorzystanie Pasma</th>
                                        <th class="py-3 px-4 text-center font-bold">Akcja</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-xs">
                                    <?php foreach ($parsedData['top_hosts'] as $index => $host): ?>
                                        <tr class="host-row hover:bg-slate-50/50 transition-colors <?php echo $index >= 5 ? 'hidden' : ''; ?>">
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full <?php echo $host['pozycja'] <= 3 ? 'bg-amber-50 text-amber-700 font-bold border border-amber-200' : 'bg-slate-100 text-slate-600'; ?>">
                                                    <?php echo $host['pozycja']; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900 font-mono"><?php echo htmlspecialchars($host['ip']); ?></div>
                                                <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($host['opis']); ?></div>
                                            </td>
                                            <td class="py-3 px-4 text-right font-semibold text-slate-600"><?php echo htmlspecialchars($host['zdarzenia']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-emerald-600"><?php echo htmlspecialchars($host['rx']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-amber-550"><?php echo htmlspecialchars($host['tx']); ?></td>
                                            <td class="py-3 px-4 text-right font-bold text-slate-900"><?php echo htmlspecialchars($host['suma']); ?></td>
                                            <td class="py-3 px-4 w-44">
                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-blue-600 rounded-full" style="width: <?php echo $host['procent_pasma']; ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <a href="index.php?file=<?php echo urlencode($selectedFile); ?>&filter_day=<?php echo urlencode($filterDay); ?>&active_ip=<?php echo urlencode($host['ip']); ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600 shadow-3xs hover:bg-slate-50 hover:text-blue-600 hover:border-blue-200 transition-all font-sans">
                                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                    Analizuj
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Przycisk Pokaż więcej pod tabelą -->
                        <?php if (count($parsedData['top_hosts']) > 5): ?>
                            <div class="mt-4 text-center border-t border-slate-100 pt-4">
                                <button id="btn-show-more" onclick="showAllHostsRows()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 shadow-xs hover:bg-slate-50 hover:text-blue-600 transition-all font-sans font-medium">
                                    <i data-lucide="chevrons-down" class="h-4 w-4"></i>
                                    Pokaż więcej (<?php echo count($parsedData['top_hosts']) - 5; ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- KARTA ANALITYCZNA DOPASOWANA DO STYLU ZE ZDJĘCIA -->
                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6 font-sans">
                            <div>
                                <h3 class="text-base font-bold text-slate-950">Karta Analityczna Wybranego Hosta</h3>
                                <p class="text-xs text-slate-400 mt-1 font-sans">Szczegółowa korelacja ruchu, lokalizacji docelowych oraz rozkładu czasowego dla wybranego IP.</p>
                            </div>
                            <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600">Wybrany: <?php echo htmlspecialchars($parsedData['selected_host']['ip'] ?? ''); ?></span>
                        </div>

                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <!-- IDENTYFIKACJA HOSTA I ROZKŁAD POBIERANIA/WYSYŁANIA -->
                            <div class="lg:border-r lg:border-slate-100 lg:pr-8 flex flex-col justify-between font-sans">
                                <div class="space-y-5">

                                    <!-- HOST -->
                                    <div class="leading-tight">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Identyfikacja Hosta
                                            </span>

                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600 font-sans">
                                                Lokalny IP
                                            </span>
                                        </div>

                                        <div class="mt-2">
                                            <h4 class="text-xl font-bold text-slate-900 font-mono">
                                                <?php echo htmlspecialchars($parsedData['selected_host']['ip'] ?? ''); ?>
                                            </h4>

                                            <p class="text-xs font-semibold text-slate-600">
                                                <?php echo htmlspecialchars($parsedData['selected_host']['nazwa'] ?? ''); ?>
                                            </p>

                                            <span class="text-[11px] text-slate-400">
                                                <?php echo htmlspecialchars($parsedData['selected_host']['domena'] ?? ''); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- RX / TX -->
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">
                                            Transfer
                                        </span>

                                        <div class="space-y-3">

                                            <!-- RX -->
                                            <div>
                                                <div class="flex justify-between text-xs font-semibold mb-1">
                                                    <span class="flex items-center gap-2 text-slate-600">
                                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                        Pobieranie
                                                    </span>

                                                    <span class="text-emerald-600 font-bold">
                                                        <?php echo htmlspecialchars($parsedData['selected_host']['pobrane_rx'] ?? '0 MB'); ?>
                                                    </span>
                                                </div>

                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-emerald-500 rounded-full" style="width: 85%"></div>
                                                </div>
                                            </div>

                                            <!-- TX -->
                                            <div>
                                                <div class="flex justify-between text-xs font-semibold mb-1">
                                                    <span class="flex items-center gap-2 text-slate-600">
                                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                                        Wysyłanie
                                                    </span>

                                                    <span class="text-amber-600 font-bold">
                                                        <?php echo htmlspecialchars($parsedData['selected_host']['wyslane_tx'] ?? '0 MB'); ?>
                                                    </span>
                                                </div>

                                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-amber-500 rounded-full" style="width: 15%"></div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- META -->
                                    <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-2">

                                                    <div class="flex justify-between">
                                                        <span>Suma transferu:</span>
                                                        <span class="font-bold text-slate-800">
                                                            <?php echo htmlspecialchars($parsedData['selected_host']['suma_transferu'] ?? '0 MB'); ?>
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between">
                                                        <span>Zdarzenia:</span>
                                                        <span class="font-bold text-slate-800">
                                                            <?php echo htmlspecialchars($parsedData['selected_host']['zdarzenia'] ?? '0'); ?>
                                                        </span>
                                                    </div>

                                                    <div class="flex justify-between items-center">
                                                        <span>System:</span>
                                                        <span class="font-semibold text-blue-600 flex items-center gap-1">
                                                            <i data-lucide="shield" class="h-3 w-3"></i>
                                                            <?php echo htmlspecialchars($parsedData['meta']['urzadzenie']); ?>
                                                        </span>
                                                    </div>

                                                </div>

                                </div>
                            </div>

                            <!-- KIERUNKI DOCELOWE (IP) Z PASKIEM PROCENTOWYM -->
                            <div class="lg:border-r lg:border-slate-100 lg:px-4">
                                <div class="mb-4 font-sans">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-medium">Kierunki Docelowe (IP)</span>
                                        <span class="text-[10px] font-semibold text-slate-400 font-sans">Pobrane bezpośrednio</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">Kliknij w adres IP, aby sprawdzić informacje w serwisie WHOIS.</p>
                                </div>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                    <?php if (empty($parsedData['selected_host']['kierunki'])): ?>
                                        <div class="flex flex-col items-center justify-center py-12 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50 font-sans">
                                            <i data-lucide="info" class="h-8 w-8 mb-2 opacity-60"></i>
                                            <span class="text-xs font-semibold">Brak danych</span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($parsedData['selected_host']['kierunki'] as $kierunek): ?>
                                            <div class="text-xs py-1.5 border-b border-slate-55 font-mono">
                                                <div class="flex items-center justify-between font-bold mb-1">
                                                    <a href="<?php echo $kierunek['whois_url']; ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline flex items-center gap-1">
                                                        <?php echo htmlspecialchars($kierunek['ip']); ?>
                                                        <i data-lucide="external-link" class="h-3 w-3 opacity-60"></i>
                                                    </a>
                                                    <span class="text-slate-700"><?php echo htmlspecialchars($kierunek['zdarzenia']); ?></span>
                                                </div>
                                                <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?php echo $kierunek['procent']; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-slate-100 pt-4 font-sans ">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3 font-sans font-medium pt-32">Rozpoznane Aplikacje</span>
                                    <div class="space-y-3 font-sans">
                                        <?php if (empty($parsedData['selected_host']['aplikacje'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                                <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold font-sans">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['aplikacje'] as $usluga): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1 font-sans">
                                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono text-[11px]"><?php echo htmlspecialchars($usluga['nazwa']); ?></span>
                                                        <span class="text-slate-600 font-bold"><?php echo htmlspecialchars($usluga['zdarzenia']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-amber-500 to-orange-600 rounded-full transition-all duration-500" style="width: <?php echo $usluga['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- GEOLOKALIZACJA I USŁUGI (PROTOKOŁY) -->
                            <div class="lg:pl-4 space-y-6">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3 font-sans font-medium">Geolokalizacja (Kraje)</span>
                                    <div class="space-y-3 font-sans">
                                        <?php if (empty($parsedData['selected_host']['geolokalizacja'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50 font-sans">
                                                <i data-lucide="globe" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['geolokalizacja'] as $krajData): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1 font-sans">
                                                        <span class="text-slate-600 font-bold font-sans">
                                                            <span class="text-slate-400 font-normal uppercase mr-1"><?php echo htmlspecialchars($krajData['prefiks']); ?></span>
                                                            <?php echo htmlspecialchars($krajData['kraj']); ?>
                                                        </span>
                                                        <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($krajData['logi']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-rose-500 to-red-600 rounded-full transition-all duration-500" style="width: <?php echo $krajData['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="border-t border-slate-100 pt-4 font-sans">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-3 font-sans font-medium">Rozpoznane Usługi (Protokoły)</span>
                                    <div class="space-y-3 font-sans">
                                        <?php if (empty($parsedData['selected_host']['uslugi'])): ?>
                                            <div class="flex flex-col items-center justify-center py-6 text-slate-400 border border-dashed border-slate-150 rounded-2xl bg-slate-50/50">
                                                <i data-lucide="layers" class="h-8 w-8 mb-2 opacity-60"></i>
                                                <span class="text-xs font-semibold font-sans">Brak danych</span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($parsedData['selected_host']['uslugi'] as $usluga): ?>
                                                <div>
                                                    <div class="flex justify-between text-xs font-semibold mb-1 font-sans">
                                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700 font-mono text-[11px]"><?php echo htmlspecialchars($usluga['nazwa']); ?></span>
                                                        <span class="text-slate-600 font-bold"><?php echo htmlspecialchars($usluga['zdarzenia']); ?></span>
                                                    </div>
                                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                        <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo $usluga['procent']; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
$maxLogi = max(array_column($parsedData['rozkład_godzinowy'], 'logi'));
?>

<div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
    <h3 class="text-base font-bold text-slate-950 mb-4">
        Rozkład czasowy zdarzeń (Aktywność dobowo-godzinowa)
    </h3>

    <div class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12">

        <?php foreach ($parsedData['rozkład_godzinowy'] as $godzina):

            $logCount = intval($godzina['logi']);

            // procent względem największej wartości
            $intensity = $maxLogi > 0
                ? ($logCount / $maxLogi) * 100
                : 0;

            // dynamiczne klasy
            if ($intensity >= 85) {
                $bgClass = 'bg-blue-900 text-white border-blue-950';
            } elseif ($intensity >= 70) {
                $bgClass = 'bg-blue-700 text-white border-blue-800';
            } elseif ($intensity >= 50) {
                $bgClass = 'bg-blue-500 text-white border-blue-600';
            } elseif ($intensity >= 30) {
                $bgClass = 'bg-blue-300 text-blue-950 border-blue-400';
            } elseif ($intensity >= 15) {
                $bgClass = 'bg-blue-100 text-blue-900 border-blue-200';
            } else {
                $bgClass = 'bg-slate-50 text-slate-500 border-slate-200';
            }

        ?>

            <div class="rounded-xl p-3 text-center border shadow-sm transition-all duration-200 hover:scale-105 flex flex-col justify-between items-center min-h-[75px] <?php echo $bgClass; ?>">

                <span class="text-[9px] font-semibold uppercase tracking-wider opacity-85">
                    <?php echo $godzina['godzina']; ?>
                </span>

                <span class="text-xs font-bold mt-1">
                    <?php echo htmlspecialchars($godzina['logi']); ?> zd.
                </span>

            </div>

        <?php endforeach; ?>

    </div>
</div>

                <?php else: ?>
                    <!-- ========================================== -->
                    <!-- WIDOK 2: DETEKCJA SKANOWANIA / ZDARZEŃ     -->
                    <!-- ========================================== -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Wykryte Zdarzenia (Suma)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-red-600"><?php echo number_format($parsedData['meta']['suma_zdarzen'], 0, ',', ' '); ?> zd.</h3>
                                </div>
                                <div class="rounded-xl bg-red-50 p-3 text-red-600 animate-pulse">
                                    <i data-lucide="shield-alert" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Agresorzy (Unikalne IP)</p>
                                    <h3 class="mt-2 text-2xl font-bold text-slate-900"><?php echo $parsedData['meta']['unikalne_ip']; ?> hostów</h3>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3 text-slate-600">
                                    <i data-lucide="shield-off" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Najbardziej Aktywny IP / Host</p>
                                    <h3 class="mt-2 text-md font-bold text-red-700 font-mono truncate" title="<?php echo $parsedData['meta']['najbardziej_aktywny_ip']; ?>"><?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?></h3>
                                </div>
                                <div class="rounded-xl bg-red-100 p-3 text-red-600">
                                    <i data-lucide="flame" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Urządzenie zabezpieczające</p>
                                    <h3 class="mt-2 text-2xl font-bold text-blue-600"><?php echo htmlspecialchars($parsedData['meta']['urzadzenie']); ?></h3>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-3 text-blue-600">
                                    <i data-lucide="shield-check" class="h-6 w-6"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela Skonfigurowana dla Skanowania i Logowań -->
                    <div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-950 flex items-center gap-2 font-sans">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-ping"></span>
                                    Analiza raportu zdarzeń i naruszenia reguł bezpieczeństwa Firewall / Auth
                                </h3>
                                <p class="text-xs text-slate-400 mt-1 font-sans font-normal">Zewnętrzne lub wewnętrzne hosty generujące próby połączeń, skanowań lub nieudanych logowań. Kliknij na odnośniki w sekcji <b>Analiza IP</b>, aby sprawdzić reputację oraz rejestr WHOIS.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto font-sans">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400 font-sans">
                                        <th class="py-3 px-4 font-bold font-sans">Kraj (Flaga)</th>
                                        <th class="py-3 px-4 font-bold font-sans">Źródło (Source IP)</th>
                                        <th class="py-3 px-4 w-1/3 font-bold font-sans">Cele (Dest IP & Port)</th>
                                        <th class="py-3 px-4 text-center font-bold font-sans">Zdarzenia / Próby</th>
                                        <th class="py-3 px-4 text-center font-bold font-sans">Zagrożenie</th>
                                        <th class="py-3 px-4 font-bold font-sans">Aplikacja / Protokół</th>
                                        <th class="py-3 px-4 font-bold font-sans">Szczegóły zdarzenia</th>
                                        <th class="py-3 px-4 text-center font-bold font-sans">Analiza IP źródłowego</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-xs font-medium font-sans">
                                    <?php if (empty($parsedData['scans'])): ?>
                                        <tr>
                                            <td colspan="8" class="py-8 text-center text-slate-400 font-semibold">Brak wykrytych rekordów w wybranym pliku raportu HTML.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($parsedData['scans'] as $scan):
                                            $badgeClass = 'bg-blue-50 text-blue-700 border-blue-100';
                                            if ($scan['danger_level'] === 'Critical') {
                                                $badgeClass = 'bg-red-50 text-red-700 border border-red-200';
                                            } elseif ($scan['danger_level'] === 'High') {
                                                $badgeClass = 'bg-orange-50 text-orange-700 border border-orange-200';
                                            }
                                        ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors">
                                                <!-- Kraj źródłowy -->
                                                <td class="py-3.5 px-4">
                                                    <span class="text-xl inline-block align-middle" title="<?php echo htmlspecialchars($scan['source_country']); ?>">
                                                        <?php echo $parser->getCountryFlag($scan['source_country']); ?>
                                                    </span>
                                                    <span class="text-slate-500 text-[10px] ml-1.5 align-middle block sm:inline"><?php echo htmlspecialchars($scan['source_country']); ?></span>
                                                </td>
                                                <!-- Agresor IP (Zewnętrzny / Wewnętrzny) -->
                                                <td class="py-3.5 px-4 font-bold text-slate-900 font-mono">
                                                    <div class="flex flex-col">
                                                        <span><?php echo htmlspecialchars($scan['source_ip']); ?></span>
                                                        <span class="text-[10px] text-slate-400 font-normal font-sans">Host</span>
                                                    </div>
                                                </td>
                                                <!-- Lista celów (Pionowo adres pod adresem z osobnymi odsyłaczami) -->
                                                <td class="py-3.5 px-4 font-mono font-medium">
                                                    <div class="flex flex-col gap-2 max-h-40 overflow-y-auto py-1">
                                                        <?php
                                                        // Rozbijamy zapisaną w stringu lista celów na osobne adresy IP
                                                        $rawDest = $scan['dest_ip'];
                                                        $dest_ips = preg_split('/[\s,\n]+/', $rawDest);
                                                        $dest_ips = array_filter(array_map('trim', $dest_ips));

                                                        foreach ($dest_ips as $single_ip):
                                                            if (empty($single_ip)) continue;
                                                            $single_ip_clean = preg_replace('/\s*\([^)]*\)/', '', $single_ip);
                                                        ?>
                                                            <div class="flex items-center justify-between bg-slate-50 rounded-lg p-1.5 border border-slate-100 hover:bg-slate-100/70 transition-all">
                                                                <div class="flex flex-col">
                                                                    <span class="font-bold text-slate-800 text-[11px]"><?php echo htmlspecialchars($single_ip_clean); ?></span>
                                                                    <span class="text-[9px] text-slate-400 font-bold">PORT: <?php echo htmlspecialchars($scan['dest_port']); ?></span>
                                                                </div>
                                                                <!-- Podręczne narzędzia do analizy tego konkretnego adresu celu -->
                                                                <div class="flex gap-1">
                                                                    <a href="https://www.abuseipdb.com/check/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-red-50 text-red-600 hover:bg-red-100" title="AbuseIPDB (Cel)">
                                                                        <i data-lucide="shield-alert" class="h-3 w-3"></i>
                                                                    </a>
                                                                    <a href="https://www.virustotal.com/gui/ip-address/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-slate-100 text-slate-700 hover:bg-slate-200" title="VirusTotal (Cel)">
                                                                        <i data-lucide="globe" class="h-3 w-3"></i>
                                                                    </a>
                                                                    <a href="https://www.whois.com/whois/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener noreferrer" class="rounded p-1 bg-blue-50 text-blue-600 hover:bg-blue-100" title="WHOIS (Cel)">
                                                                        <i data-lucide="search" class="h-3 w-3"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <!-- Zdarzenia -->
                                                <td class="py-3.5 px-4 text-center font-bold text-slate-900 font-mono">
                                                    <?php echo number_format($scan['events_count'], 0, ',', ' '); ?>
                                                </td>
                                                <!-- Zagrożenie -->
                                                <td class="py-3.5 px-4 text-center font-sans">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase <?php echo $badgeClass; ?>">
                                                        <?php echo $scan['danger_level']; ?>
                                                    </span>
                                                </td>
                                                <!-- Aplikacja/Protokół -->
                                                <td class="py-3.5 px-4 font-sans">
                                                    <div class="text-slate-900 font-bold"><?php echo htmlspecialchars($scan['application']); ?></div>
                                                    <div class="text-[10px] text-slate-400 font-bold font-mono"><?php echo htmlspecialchars($scan['protocol']); ?> / <?php echo htmlspecialchars($scan['service']); ?></div>
                                                </td>
                                                <!-- Opis zdarzenia -->
                                                <td class="py-3.5 px-4 text-slate-500 max-w-[150px] truncate font-sans font-medium" title="<?php echo htmlspecialchars($scan['event_desc']); ?>">
                                                    <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($scan['event_info']); ?></div>
                                                    <div class="text-[10px]"><?php echo htmlspecialchars($scan['event_desc']); ?></div>
                                                </td>
                                                <!-- Narzędzia analizy źródła (IP agresora) -->
                                                <td class="py-3.5 px-4 text-center font-sans">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <a href="<?php echo $scan['abuse_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50/50 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-100 transition-all font-sans font-medium">
                                                            <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i> AbuseIPDB
                                                        </a>
                                                        <a href="<?php echo $scan['virustotal_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition-all font-sans font-medium">
                                                            <i data-lucide="globe" class="h-3.5 w-3.5"></i> VT
                                                        </a>
                                                        <a href="<?php echo $scan['whois_url']; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50/50 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition-all font-sans font-medium">
                                                            <i data-lucide="search" class="h-3.5 w-3.5"></i> WHOIS
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Stan pusty (brak wgranych raportów) -->
                <div class="flex flex-col items-center justify-center min-h-[55vh] rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center font-sans">
                    <div class="rounded-2xl bg-blue-50 p-4 text-blue-600 mb-4 font-sans">
                        <i data-lucide="folder-search" class="h-10 w-10"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-950 font-sans">Brak wgranych raportów sieciowych</h3>
                    <p class="text-sm text-slate-400 max-w-md mt-2 font-sans font-normal">Wgraj plik ZIP zawierający raporty HTML wygenerowane z systemu zabezpieczającego, aby automatycznie odczytać strukturę danych i wygenerować profesjonalne analizy.</p>
                    <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors">
                        <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                        Wgraj pierwszy plik ZIP
                    </button>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Modal wgrywania ZIP -->
    <div id="upload-modal" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="upload-cloud" class="h-5 w-5 text-blue-600"></i>
                    Importuj nowy pakiet raportów
                </h3>
                <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <form action="upload.php" method="POST" enctype="multipart/form-data" class="mt-4">
                <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center hover:bg-slate-50/50 transition-colors cursor-pointer" onclick="document.getElementById('zip-input').click()">
                    <i data-lucide="folder-archive" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <p class="mt-3 text-sm font-semibold text-slate-700">Wybierz plik ZIP z dysku</p>
                    <p class="mt-1 text-xs text-slate-400">Maksymalny rozmiar pliku: 120 MB</p>
                    <input type="file" name="zip_file" id="zip-input" class="hidden" accept=".zip" required onchange="updateFileName(this)">
                    <div id="selected-file-name" class="mt-4 hidden text-xs font-bold text-blue-600 bg-blue-50 rounded-lg p-2 truncate"></div>
                </div>

                <div class="mt-6 flex justify-end gap-3 font-sans">
                    <button type="button" onclick="document.getElementById('upload-modal').classList.add('hidden')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Anuluj</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 font-sans">Rozpocznij import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Potwierdzenia Usunięcia Katalogu -->
    <div id="delete-confirm-modal" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center gap-3 text-red-600 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                <h3 class="font-bold text-slate-900 text-lg">Potwierdź usunięcie katalogu</h3>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed font-sans font-normal">
                Czy na pewno chcesz usunąć katalog <span id="delete-dir-name" class="font-bold text-slate-900"></span> wraz ze wszystkimi plikami raportów HTML? Ta operacja jest całkowicie nieodwracalna.
            </p>
            <form action="index.php" method="POST" class="mt-6 flex justify-end gap-3 font-sans">
                <input type="hidden" name="action" value="delete_dir">
                <input type="hidden" name="dir_name" id="delete-input-dir" value="">
                <button type="button" onclick="document.getElementById('delete-confirm-modal').classList.add('hidden')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Anuluj</button>
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 shadow-sm transition-all font-sans font-medium">Tak, usuń katalog</button>
            </form>
        </div>
    </div>

    <!-- Skrypt obsługi interfejsu (IKONY + TOGGLE FOLDER + MODAL USUWANIA) -->
    <script>
        // Inicjalizacja ikon Lucide
        lucide.createIcons();

        function toggleFolder(id) {
            const el = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                icon.style.transform = 'rotate(0deg)';
            } else {
                el.classList.add('hidden');
                icon.style.transform = 'rotate(-90deg)';
            }
        }

        function updateFileName(input) {
            const fileNameBox = document.getElementById('selected-file-name');
            if (input.files && input.files[0]) {
                fileNameBox.textContent = "Wybrano: " + input.files[0].name;
                fileNameBox.classList.remove('hidden');
            } else {
                fileNameBox.classList.add('hidden');
            }
        }

        function confirmDeleteDir(dirName) {
            document.getElementById('delete-dir-name').textContent = dirName;
            document.getElementById('delete-input-dir').value = dirName;
            document.getElementById('delete-confirm-modal').classList.remove('hidden');
        }

        // Funkcja rozwijająca ukryte wiersze w tabeli hostów transferu dobowego
        function showAllHostsRows() {
            const rows = document.querySelectorAll('.host-row');
            rows.forEach(row => {
                row.classList.remove('hidden');
            });
            const btn = document.getElementById('btn-show-more');
            if(btn) {
                btn.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
