# Panel analityczny raportów SOC Logsign

## Dokumentacja techniczna projektu

**Wersja dokumentu:** 1.0  
**Typ projektu:** aplikacja webowa PHP do analizy raportów SOC / Logsign  
**Środowisko uruchomieniowe:** Docker, Nginx, PHP-FPM, MySQL 8.0  
**Główne funkcje:** import raportów HTML z paczek ZIP, parsowanie danych, prezentacja dashboardów, statystyki zbiorcze i eksport PDF.

---

## 1. Cel projektu

Projekt jest aplikacją webową służącą do importowania, parsowania, wizualizacji i agregowania raportów HTML generowanych z systemu Logsign / SOC. System wspiera analityka bezpieczeństwa w szybkim przeglądaniu raportów, identyfikowaniu aktywnych hostów, wykrywaniu skanowania portów, analizie błędnych prób logowania, odrzuconych połączeń oraz ruchu na niestandardowych portach.

Aplikacja została przygotowana jako lekki panel analityczny uruchamiany w kontenerach Docker. Dane wejściowe są dostarczane jako pliki HTML spakowane do archiwów ZIP. Po imporcie raporty są porządkowane według dat, a następnie analizowane przez dedykowane parsery PHP.

---

## 2. Zakres funkcjonalny

Aplikacja realizuje następujące funkcje:

1. Import paczek ZIP z raportami HTML.
2. Obsługa zagnieżdżonych archiwów ZIP, czyli ZIP-w-ZIP.
3. Automatyczne rozpakowanie i uporządkowanie raportów według dat.
4. Normalizacja nazw plików raportów.
5. Parsowanie różnych typów raportów Logsign.
6. Wyświetlanie raportów w dedykowanych widokach.
7. Przeglądanie archiwum raportów według dat.
8. Usuwanie katalogów raportów z poziomu panelu.
9. Filtrowanie i wyszukiwanie danych w tabelach.
10. Analiza godzinowa zdarzeń.
11. Budowanie statystyk zbiorczych dla ostatnich 3, 7 lub 30 dni.
12. Generowanie wykresów i eksport zbiorczego dashboardu do PDF.

---

## 3. Architektura systemu

System składa się z trzech głównych usług kontenerowych:

| Komponent | Technologia | Rola |
|---|---|---|
| `web` | Nginx | Serwer HTTP i reverse proxy dla aplikacji PHP |
| `app` | PHP / PHP-FPM | Warstwa aplikacyjna i parsery raportów |
| `db` | MySQL 8.0 | Warstwa bazodanowa przygotowana pod dalszy rozwój |

Aplikacja korzysta z wolumenów montowanych z katalogu projektu. Kod źródłowy jest udostępniany do kontenerów jako `/var/www/html`.

### 3.1. Warstwa HTTP

Nginx nasłuchuje na porcie `80` wewnątrz kontenera i jest mapowany na port `8080` hosta. Lokalny adres aplikacji:

```text
http://localhost:8080
```

Żądania PHP są przekazywane przez FastCGI do usługi `app` na porcie `9000`.

### 3.2. Warstwa aplikacyjna

Aplikacja jest napisana w PHP. Główne pliki odpowiadają za dashboard, upload, statystyki, parsery i widoki raportów.

| Plik | Rola |
|---|---|
| `index.php` | Główny dashboard, routing raportów, drzewo archiwum i dynamiczne ładowanie widoków |
| `upload.php` | Obsługa uploadu ZIP, rozpakowywanie, normalizacja nazw, zapis do `/dane` |
| `stats.php` | Statystyki zbiorcze SOC, wykresy i eksport PDF |
| `parser.php` | Parser raportów transferu |
| `parser_skanowanie_wew.php` | Parser hostów wewnętrznych skanujących porty |
| `parser_skanowanie_zew.php` | Parser hostów zewnętrznych skanujących porty |
| `parser_host_logowanie.php` | Parser prób logowania według hostów |
| `parser_uzytkownicy_bledne_logowanie.php` | Parser błędnych prób logowania według użytkowników |
| `parser_odrzuconych_polaczen_wew.php` | Parser odrzuconych połączeń z hostów wewnętrznych |
| `parser_odrzucownych_polaczen_zew.php` | Parser odrzuconych połączeń z hostów zewnętrznych |
| `parser_polaczen_niestandardowe_porty.php` | Parser połączeń wychodzących na niestandardowe porty |
| `view_*.php` | Widoki prezentujące dane konkretnego typu raportu |

### 3.3. Warstwa danych

Raporty po imporcie są zapisywane w strukturze:

```text
/dane/YYYY-MM-DD/nazwa_raportu.html
```

Katalog `temp/` służy do tymczasowego rozpakowywania archiwów. Po zakończonym imporcie jego zawartość jest czyszczona.

Baza MySQL jest skonfigurowana w środowisku Docker, jednak aktualna logika aplikacji bazuje głównie na plikach HTML przechowywanych lokalnie w katalogu `/dane`.

---

## 4. Wymagania środowiskowe

Do uruchomienia projektu wymagane są:

```text
Docker
Docker Compose
Przeglądarka internetowa
Dostęp do portów 8080 i 3306 na hoście
```

Aplikacja korzysta z bibliotek ładowanych przez CDN:

```text
Tailwind CSS
Chart.js
Lucide Icons
html2canvas
jsPDF
Google Fonts Inter
```

W środowisku bez internetu część interfejsu może działać ograniczenie, szczególnie style, ikony, wykresy i eksport PDF.

---

## 5. Instalacja i uruchomienie

### 5.1. Rekomendowana struktura katalogów

```text
projekt/
├── docker-compose.yml
├── Dockerfile
├── nginx.conf
├── upload.ini
├── src/
│   ├── index.php
│   ├── upload.php
│   ├── stats.php
│   ├── dane/
│   ├── temp/
│   ├── parsers/
│   └── inc/
└── README.md
```

W obecnej wersji część parserów może znajdować się bezpośrednio w katalogu aplikacji. Kod zawiera mechanizmy zgodności, które próbują ładować parsery zarówno z katalogu `parsers/`, jak i z lokalizacji głównej.

### 5.2. Uruchomienie aplikacji

```bash
docker compose up -d --build
```

Po starcie aplikacja będzie dostępna pod adresem:

```text
http://localhost:8080
```

### 5.3. Zatrzymanie środowiska

```bash
docker compose down
```

### 5.4. Usunięcie danych bazy MySQL

```bash
docker compose down -v
```

Uwaga: powyższa komenda usuwa wolumen `db_data`, czyli dane MySQL.

---

## 6. Konfiguracja

### 6.1. Konfiguracja Nginx

Nginx obsługuje aplikację na porcie `80`, wskazuje katalog główny `/var/www/html` i przekazuje pliki PHP do kontenera aplikacyjnego przez FastCGI.

Najważniejsze ustawienia:

```nginx
client_max_body_size 120M;
fastcgi_pass app:9000;
```

Limit `120M` umożliwia przesyłanie większych paczek ZIP z raportami.

### 6.2. Konfiguracja PHP upload

Konfiguracja uploadu zwiększa limity PHP:

```ini
upload_max_filesize = 120M
post_max_size = 120M
memory_limit = 512M
```

Dzięki temu aplikacja może obsługiwać większe archiwa i przetwarzać rozbudowane raporty HTML.

### 6.3. Konfiguracja Docker Compose

Środowisko definiuje trzy usługi:

```text
web  -> nginx
app  -> PHP application
db   -> MySQL 8.0
```

Mapowanie portów hosta:

```text
8080 -> Nginx
3306 -> MySQL
```

---

## 7. Przepływ danych

Standardowy przepływ danych wygląda następująco:

1. Operator otwiera panel raportów.
2. Operator przesyła paczkę ZIP z raportami HTML.
3. `upload.php` zapisuje ZIP do katalogu tymczasowego.
4. System rozpakowuje archiwum.
5. Jeżeli w środku znajduje się kolejny ZIP, aplikacja rozpakowuje go rekurencyjnie.
6. System wyszukuje pliki `.html`.
7. Dla każdego pliku określany jest timestamp na podstawie czasu modyfikacji pliku.
8. Plik trafia do katalogu `/dane/YYYY-MM-DD/`.
9. Nazwa pliku jest czyszczona i normalizowana.
10. Operator wybiera raport z archiwum.
11. `index.php` wykrywa typ raportu po nazwie pliku.
12. Odpowiedni parser analizuje HTML.
13. Wynik parsowania trafia do dedykowanego widoku.
14. Użytkownik analizuje dane, filtruje wyniki, rozwija szczegóły i sprawdza rozkład godzinowy.
15. Moduł `stats.php` agreguje wiele raportów do dashboardu zbiorczego.

---

## 8. Typy obsługiwanych raportów

Aplikacja rozpoznaje typ raportu po nazwie pliku.

| Typ raportu | Parser | Opis |
|---|---|---|
| Transfer | `RaportParser` | Analiza ruchu, hostów, transferu RX/TX i usług |
| Hosty wewnętrzne skanujące porty | `RaportWewnSkanujaceParser` | Analiza skanowania wykonywanego przez hosty wewnętrzne |
| Hosty zewnętrzne skanujące porty | `RaportZewnSkanujaceParser` | Analiza zewnętrznych agresorów skanujących porty |
| Hosty z błędnymi próbami logowania | `RaportHostLogowanieParser` | Analiza prób logowania według hostów |
| Użytkownicy z błędnymi próbami logowania | `RaportBedneLogowaniaUzytkownicyParser` | Analiza prób logowania według użytkowników |
| Odrzucone połączenia z hostów wewnętrznych | `RaportOdrzuconeWewnParser` | Analiza blokowanego ruchu wychodzącego lub wewnętrznego |
| Odrzucone połączenia z hostów zewnętrznych | `RaportOdrzuconeZewnParser` | Analiza blokowanego ruchu przychodzącego z zewnątrz |
| Połączenia wychodzące na niestandardowe porty | `RaportWychodzaceNiestandardoweParser` | Analiza nietypowego ruchu wychodzącego |

---

## 9. Główny dashboard - `index.php`

Plik `index.php` odpowiada za główną obsługę aplikacji. Jego zadania:

1. Rejestracja klas parserów.
2. Odczyt katalogu `/dane`.
3. Budowanie drzewa raportów według dat.
4. Obsługa wyboru daty z archiwum.
5. Obsługa wyboru konkretnego pliku raportu.
6. Wykrywanie typu raportu na podstawie nazwy pliku.
7. Uruchomienie właściwego parsera.
8. Dynamiczne dołączenie odpowiedniego widoku z katalogu `inc/`.
9. Obsługa usuwania katalogu raportów.
10. Wyświetlanie komunikatów o imporcie i usunięciu danych.

Dashboard pełni rolę centralnego routera aplikacji. Nie przechowuje logiki parsowania, tylko deleguje ją do wyspecjalizowanych parserów.

---

## 10. Import raportów - `upload.php`

Moduł uploadu odpowiada za przyjmowanie paczek ZIP z raportami.

### 10.1. Obsługa uploadu

Skrypt przyjmuje plik z formularza `zip_file`, zapisuje go tymczasowo w katalogu `temp/`, a następnie rozpoczyna proces rozpakowywania.

### 10.2. Obsługa ZIP-w-ZIP

Aplikacja rozpakowuje archiwum w pętli. Po wypakowaniu sprawdza, czy w katalogu tymczasowym pojawił się kolejny plik ZIP. Jeżeli tak, przetwarza go jako następne archiwum.

### 10.3. Organizacja plików HTML

Po rozpakowaniu system rekurencyjnie wyszukuje pliki `.html`. Każdy plik zostaje zapisany do katalogu odpowiadającego dacie modyfikacji pliku:

```text
/dane/2026-05-23/raport_2026-05-23_22-00-33.html
```

### 10.4. Normalizacja nazw

Funkcja przygotowująca nazwę pliku:

1. usuwa prefiksy organizacyjne,
2. usuwa starą datę z nazwy,
3. dodaje dokładny timestamp,
4. naprawia problemy z kodowaniem znaków,
5. normalizuje polskie znaki,
6. ogranicza ryzyko błędnych lub nieczytelnych nazw plików.

### 10.5. Czyszczenie katalogu tymczasowego

Po zakończonym imporcie katalog `temp/` jest czyszczony. Dzięki temu aplikacja nie gromadzi zbędnych plików roboczych.

---

## 11. Moduł statystyk - `stats.php`

Moduł `stats.php` agreguje dane z wielu raportów HTML znajdujących się w katalogu `/dane`.

Obsługiwane zakresy:

```text
3 dni
7 dni
30 dni
```

Zakres liczony jest względem najnowszego dostępnego raportu, a nie względem bieżącej daty systemowej.

### 11.1. Agregowane metryki

Dashboard statystyczny prezentuje między innymi:

1. liczbę przetworzonych plików,
2. łączną liczbę zdarzeń,
3. zakres dat,
4. liczbę błędów parsowania,
5. top hostów według transferu,
6. top użytkowników z błędnymi próbami logowania,
7. top kraje źródłowe ataków,
8. top godziny występowania zdarzeń,
9. top porty,
10. top aplikacje,
11. top usługi.

### 11.2. Wykresy

Do wizualizacji danych wykorzystywany jest Chart.js. Dane są przekazywane z PHP do JavaScript w formacie JSON.

### 11.3. Eksport PDF

Dashboard zbiorczy można wyeksportować do PDF. Eksport działa po stronie przeglądarki i wykorzystuje biblioteki `html2canvas` oraz `jsPDF`.

---

## 12. Parsery raportów

Parsery odpowiadają za odczyt struktury HTML raportów Logsign i przekształcenie jej do jednolitego modelu danych PHP.

Wspólne cechy parserów:

1. użycie `DOMDocument`,
2. użycie `DOMXPath`,
3. wymuszenie kodowania UTF-8,
4. normalizacja tekstu,
5. usuwanie encji HTML i twardych spacji,
6. obsługa wartości z licznikami w nawiasach,
7. wyliczanie liczby zdarzeń,
8. budowanie rozkładu godzinowego,
9. wykrywanie unikalnych IP, hostów lub użytkowników,
10. przypisywanie poziomu zagrożenia tam, gdzie ma to sens.

### 12.1. Ważna zasada parsowania

W raportach Logsign licznik zdarzeń może znajdować się w różnych kolumnach w zależności od typu raportu. Parsery nie powinny automatycznie nadpisywać pełnej liczby zdarzeń sumą wpisów z `Time.Generated`, ponieważ ta kolumna często zawiera tylko próbkę, listę TOP albo wybrane timestampy.

Przykład:

```text
EventMap.Info (Value) -> pełna liczba zdarzeń
Destination.Port      -> fallback liczby zdarzeń
Time.Generated        -> rozkład godzinowy, nie zawsze pełna suma
```

To jest krytyczne dla poprawności statystyk.

---

## 13. Widoki raportów

Widoki znajdują się w katalogu `inc/` i są dołączane dynamicznie przez `index.php`.

Typowe elementy widoku:

1. kafelki KPI,
2. tabela rekordów,
3. wyszukiwarka,
4. przycisk pokazania szczegółów,
5. rozkład godzinowy,
6. oznaczanie poziomu zagrożenia,
7. flagi krajów,
8. szczegółowe dane techniczne,
9. panele rozwijane,
10. ograniczenie liczby domyślnie widocznych rekordów.

---

## 14. Model danych wyjściowych parserów

Standardowy format danych dla wielu parserów:

```php
[
    'meta' => [
        'nazwa_pliku' => '',
        'suma_zdarzen' => 0,
        'unikalne_ip' => 0,
        'najbardziej_aktywny_ip' => '',
        'urzadzenie' => ''
    ],
    'scans' => []
]
```

Dla raportów logowania stosowany jest klucz:

```php
'records' => []
```

Dla raportów transferu:

```php
'top_hosts' => []
'selected_host' => []
```

---

## 15. Bezpieczeństwo

### 15.1. Ograniczenie ścieżek

Przy wyborze pliku raportu aplikacja korzysta z `realpath()` i sprawdza, czy wskazana ścieżka znajduje się wewnątrz katalogu `/dane`. Ogranicza to ryzyko path traversal.

### 15.2. Escape danych wyjściowych

Dane prezentowane w HTML są zabezpieczane przez `htmlspecialchars()`, co zmniejsza ryzyko XSS przy wyświetlaniu wartości pochodzących z raportów.

### 15.3. Czyszczenie uploadu

Po imporcie katalog tymczasowy jest czyszczony, dzięki czemu aplikacja nie przechowuje zbędnych archiwów ani plików pośrednich.

### 15.4. Rekomendowane usprawnienia bezpieczeństwa

W obecnej formie projekt warto rozszerzyć o:

1. autoryzację użytkowników,
2. kontrolę ról,
3. walidację MIME przesyłanego pliku,
4. limit liczby plików w ZIP,
5. limit głębokości rozpakowywania ZIP-w-ZIP,
6. ochronę przed ZIP bomb,
7. logowanie operacji administracyjnych,
8. wymuszenie HTTPS w środowisku produkcyjnym,
9. wyłączenie `display_errors` na produkcji,
10. przeniesienie sekretów do `.env`.

---

## 16. Obsługa błędów

| Scenariusz | Zachowanie |
|---|---|
| Brak katalogu `/dane` | Panel pokazuje stan pusty |
| Brak raportów | Użytkownik otrzymuje komunikat o braku danych |
| Błąd uploadu | Użytkownik zostaje przekierowany z parametrem `upload_error=1` |
| Brak dopasowanego parsera | Domyślnie używany jest parser transferu |
| Błąd parsowania w statystykach | Plik jest pomijany, a licznik błędów rośnie |
| Brak danych do wykresu | Wyświetlany jest komunikat „Brak danych do wykresu” |

---

## 17. Utrzymanie projektu

### 17.1. Dodanie nowego typu raportu

Aby dodać nowy typ raportu, należy:

1. Utworzyć nowy parser, np. `parser_nowy_typ.php`.
2. Zaimplementować metodę `parse()`.
3. Zwracać dane w spójnym formacie.
4. Dodać `require_once` w `index.php`.
5. Dodać rozpoznawanie nazwy pliku w sekcji routingu.
6. Utworzyć widok w katalogu `inc/`.
7. Dodać `include` widoku w instrukcji `switch`.
8. Opcjonalnie dodać obsługę w `stats.php`.

### 17.2. Dodanie nowej metryki do statystyk

Aby dodać nową metrykę do `stats.php`, należy:

1. Zainicjalizować nowy bucket danych.
2. Uzupełnić go podczas iteracji po raportach.
3. Posortować przez funkcję top.
4. Dodać payload do wykresów.
5. Dodać sekcję HTML z wykresem i tabelą.
6. Dodać identyfikator wykresu do funkcji renderującej wykresy JS.

---

## 18. Znane ograniczenia

1. Aplikacja bazuje na strukturze HTML raportów Logsign; zmiana układu raportów może wymagać aktualizacji parserów.
2. Eksport PDF zależy od bibliotek ładowanych przez CDN.
3. Brak wbudowanego systemu użytkowników.
4. Brak centralnego logowania błędów do pliku.
5. Baza MySQL jest skonfigurowana, ale obecna logika aplikacji działa głównie na plikach.
6. Parsery są silnie powiązane z nazwami kolumn raportów.
7. Brak testów automatycznych.
8. Brak jawnej walidacji typu MIME uploadowanego archiwum.
9. Brak mechanizmu kolejkowania dużych importów.
10. Duże raporty mogą obciążać pamięć PHP podczas parsowania.

---

## 19. Rekomendacje rozwojowe

Rekomendowane kierunki dalszego rozwoju:

1. Dodanie logowania użytkowników.
2. Dodanie ról: administrator, analityk, tylko odczyt.
3. Przeniesienie konfiguracji do pliku `.env`.
4. Zapis metadanych importu do MySQL.
5. Indeksowanie raportów w bazie danych.
6. Dodanie historii importów.
7. Dodanie testów jednostkowych parserów.
8. Dodanie testowych plików HTML jako fixtures.
9. Dodanie walidacji struktury ZIP.
10. Dodanie limitów bezpieczeństwa dla archiwów.
11. Dodanie loggera PSR-3.
12. Dodanie trybu offline dla bibliotek JS/CSS.
13. Dodanie panelu konfiguracji typów raportów.
14. Dodanie eksportu CSV/XLSX.
15. Dodanie API REST do pobierania statystyk.

---

## 20. Procedura operacyjna dla użytkownika

### 20.1. Import raportu

1. Otworzyć aplikację w przeglądarce.
2. Kliknąć przycisk „Wgraj paczkę ZIP”.
3. Wybrać plik ZIP z raportami HTML.
4. Poczekać na zakończenie importu.
5. Po poprawnym imporcie raporty pojawią się w archiwum po lewej stronie.

### 20.2. Przeglądanie raportu

1. Wybrać datę w archiwum.
2. Kliknąć nazwę raportu.
3. Aplikacja automatycznie wykryje typ raportu.
4. Dane zostaną pokazane w odpowiednim widoku.
5. Można użyć wyszukiwarki, szczegółów i widoku godzinowego.

### 20.3. Analiza zbiorcza

1. Kliknąć „Statystyki”.
2. Wybrać zakres: 3, 7 lub 30 dni.
3. Przejrzeć wykresy i tabele TOP.
4. W razie potrzeby kliknąć „Exportuj raport do PDF”.

---

## 21. Podsumowanie

Projekt stanowi specjalistyczny panel analityczny dla raportów SOC / Logsign. Jego najmocniejszą stroną jest zestaw dedykowanych parserów, które obsługują różne układy raportów HTML i przekształcają je w czytelne dashboardy operacyjne.

Aplikacja dobrze sprawdza się jako narzędzie wsparcia analityka SOC: umożliwia szybkie przeglądanie zdarzeń, identyfikację najbardziej aktywnych hostów, analizę błędnych logowań, skanowania portów, odrzuconych połączeń oraz nietypowego ruchu wychodzącego.

W celu przygotowania systemu do środowiska produkcyjnego zaleca się przede wszystkim dodanie autoryzacji, walidacji uploadu, obsługi `.env`, logowania błędów oraz testów parserów.
