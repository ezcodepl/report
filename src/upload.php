<?php
/**
 * Skrypt odpowiada za bezpieczne odebranie pliku ZIP, wypakowanie go do folderu /temp/,
 * odczytanie daty z nazw plików HTML za pomocą wyrażenia regularnego,
 * przeniesienie ich do odpowiedniego katalogu w folderze /dane/ oraz posprzątanie po sobie.
 */

// Uruchomienie buforowania wyjścia, aby zapobiec problemom z nagłówkami przekierowania HTTP
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    $zipFile = $_FILES['zip_file']['tmp_name'];

    $tempDir = __DIR__ . '/temp/';
    $daneDir = __DIR__ . '/dane/';

    // Tworzenie wymaganych folderów z wyciszeniem ewentualnych ostrzeżeń systemowych
    if (!file_exists($tempDir)) @mkdir($tempDir, 0777, true);
    if (!file_exists($daneDir)) @mkdir($daneDir, 0777, true);

    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        // Wypakowanie archiwum do folderu tymczasowego
        @$zip->extractTo($tempDir);
        $zip->close();

        // Rekurencyjne przeszukiwanie folderu tymczasowego w celu znalezienia wszystkich plików .html
        if (file_exists($tempDir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir));
            $processedCount = 0;

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
                    $filename = $file->getFilename();

                    // Wyrażenie regularne szukające daty w formacie RRRR-MM-DD
                    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                        $dateFolder = $matches[1];
                        $targetFolder = $daneDir . $dateFolder . '/';

                        // Tworzenie folderu dla konkretnej daty
                        if (!file_exists($targetFolder)) {
                            @mkdir($targetFolder, 0777, true);
                        }

                        // Kopiowanie pliku do odpowiednio nazwanego folderu daty
                        @copy($file->getRealPath(), $targetFolder . $filename);
                        $processedCount++;
                    }
                }
            }
        }

        // Czyszczenie wyłącznie zawartości katalogu tymczasowego (bez usuwania folderu głównego temp)
        // Zapobiega to błędom uprawnień (Permission denied) na zmapowanych wolumenach Docker
        clearTempDirectoryContents($tempDir);

        // Wyczyszczenie bufora i bezpieczne przekierowanie do dashboardu
        ob_end_clean();
        header("Location: index.php?upload_success=" . $processedCount);
        exit;
    } else {
        ob_end_clean();
        header("Location: index.php?upload_error=1");
        exit;
    }
} else {
    ob_end_clean();
    header("Location: index.php");
    exit;
}

/**
 * Bezpiecznie usuwa wszystkie podfoldery oraz pliki z folderu tymczasowego,
 * pozostawiając nienaruszony katalog główny.
 */
function clearTempDirectoryContents($dir) {
    if (!file_exists($dir)) return;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $realPath = $fileinfo->getRealPath();
        if ($fileinfo->isDir()) {
            @rmdir($realPath);
        } else {
            @unlink($realPath);
        }
    }
}
