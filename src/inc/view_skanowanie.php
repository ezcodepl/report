<!-- ========================================== -->
<!-- WIDOK: SKANOWANIE I NARUSZENIA BEZPIECZEŃSTWA -->
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
                <h3 class="mt-2 text-md font-bold text-red-700 font-mono truncate" title="<?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?>"><?php echo htmlspecialchars($parsedData['meta']['najbardziej_aktywny_ip']); ?></h3>
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

<!-- Tabela Skonfigurowana dla Skanowania -->
<div class="mb-8 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center mb-6">
        <div>
            <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-ping"></span>
                Analiza raportu zdarzeń i naruszenia reguł bezpieczeństwa Firewall / Auth
            </h3>
            <p class="text-xs text-slate-400 mt-1">Zewnętrzne lub wewnętrzne hosty generujące próby połączeń, skanowań lub nieudanych logowań.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Kraj (Flaga)</th>
                    <th class="py-3 px-4">Źródło (Source IP)</th>
                    <th class="py-3 px-4 w-1/3">Cele (Dest IP & Port)</th>
                    <th class="py-3 px-4 text-center">Zdarzenia / Próby</th>
                    <th class="py-3 px-4 text-center">Zagrożenie</th>
                    <th class="py-3 px-4">Aplikacja / Protokół</th>
                    <th class="py-3 px-4">Szczegóły zdarzenia</th>
                    <th class="py-3 px-4 text-center">Analiza IP źródłowego</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-xs font-medium">
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
                            <td class="py-3.5 px-4">
                                <span class="text-xl inline-block align-middle" title="<?php echo htmlspecialchars($scan['source_country']); ?>">
                                    <?php echo $parser->getCountryFlag($scan['source_country']); ?>
                                </span>
                                <span class="text-slate-500 text-[10px] ml-1.5 align-middle block sm:inline"><?php echo htmlspecialchars($scan['source_country']); ?></span>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900 font-mono">
                                <div class="flex flex-col">
                                    <span><?php echo htmlspecialchars($scan['source_ip']); ?></span>
                                    <span class="text-[10px] text-slate-400 font-normal">Host</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-medium">
                                <div class="flex flex-col gap-2 max-h-40 overflow-y-auto py-1">
                                    <?php
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
                                            <div class="flex gap-1">
                                                <a href="https://www.abuseipdb.com/check/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener" class="rounded p-1 bg-red-50 text-red-600 hover:bg-red-100" title="AbuseIPDB">
                                                    <i data-lucide="shield-alert" class="h-3 w-3"></i>
                                                </a>
                                                <a href="https://www.virustotal.com/gui/ip-address/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener" class="rounded p-1 bg-slate-100 text-slate-700 hover:bg-slate-200" title="VirusTotal">
                                                    <i data-lucide="globe" class="h-3 w-3"></i>
                                                </a>
                                                <a href="https://www.whois.com/whois/<?php echo urlencode($single_ip_clean); ?>" target="_blank" rel="noopener" class="rounded p-1 bg-blue-50 text-blue-600 hover:bg-blue-100" title="WHOIS">
                                                    <i data-lucide="search" class="h-3 w-3"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-900 font-mono">
                                <?php echo number_format($scan['events_count'], 0, ',', ' '); ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase <?php echo $badgeClass; ?>">
                                    <?php echo $scan['danger_level']; ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-slate-900 font-bold"><?php echo htmlspecialchars($scan['application']); ?></div>
                                <div class="text-[10px] text-slate-400 font-bold font-mono"><?php echo htmlspecialchars($scan['protocol']); ?> / <?php echo htmlspecialchars($scan['service']); ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500 max-w-[150px] truncate" title="<?php echo htmlspecialchars($scan['event_desc']); ?>">
                                <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($scan['event_info']); ?></div>
                                <div class="text-[10px]"><?php echo htmlspecialchars($scan['event_desc']); ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="<?php echo $scan['abuse_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50/50 px-2 py-1 text-[10px] font-bold text-red-700 hover:bg-red-100 transition">
                                        <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i> AbuseIPDB
                                    </a>
                                    <a href="<?php echo $scan['virustotal_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-100 transition">
                                        <i data-lucide="globe" class="h-3.5 w-3.5"></i> VT
                                    </a>
                                    <a href="<?php echo $scan['whois_url']; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50/50 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-100 transition">
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
