# Gmail CRM

CRM w modelu SaaS działający wewnątrz Gmaila: **rozszerzenie Chrome (Manifest V3, Vue 3, InboxSDK)** połączone z **API w Laravel 11**, z tokenami Sanctum i licencjami przypisanymi do użytkowników.

```
crm-mail/
├── backend/     API w Laravel 11 (Sanctum, licencje, kontakty, zadania, dashboard)
└── frontend/    Vue 3 + Pinia + Vue Router + Tailwind: rozszerzenie Gmail (CRM) i panel webowy (Master Admin)
```

## Jak to działa

```
Karta Gmaila                                        Service worker rozszerzenia       API Laravel
┌─────────────────────────────────────────┐        ┌──────────────────────────┐      ┌───────────────────────┐
│ content.js (InboxSDK)                   │        │ background/index.js      │      │ auth:sanctum          │
│  ├ ThreadView → panel boczny            │ axios  │  • pageWorld InboxSDK    │ fetch│ license (inaczej 402) │
│  │   └ <div> ▸ shadow root ▸ SidebarApp │──msg──▶│  • dokleja token Bearer  │─────▶│ /contacts /tasks      │
│  └ własna trasa → DashboardApp          │        │  • tylko adresy API_BASE │      │ /dashboard/summary    │
│ jeden wspólny store Pinia (auth + crm)  │        │  • 401 → czyści sesję    │      │ /admin/users/*        │
└─────────────────────────────────────────┘        └──────────────────────────┘      └───────────────────────┘
                 ▲  chrome.storage.local (sesja, wspólna dla popupu i wszystkich kart)
```

* **Izolacja interfejsu:** każda aplikacja Vue jest montowana w kontenerze `<div>` z własnym Shadow DOM.
  Tailwind jest kompilowany z `?inline` i wstrzykiwany *do środka* shadow root, więc jego reset stylów
  (preflight) nie dotyka Gmaila, a style Gmaila nie wpływają na aplikację. Jednostki rem są zamieniane
  na px podczas budowania, bo bazowy rozmiar czcionki ustala Gmail.
* **Komunikacja z API:** axios w content scripcie używa własnego adaptera, który przekazuje zapytania do
  service workera. Worker ma `host_permissions` dla API, dokleja token i odrzuca wszystko, co nie
  prowadzi do `VITE_API_BASE_URL`. Dzięki temu nie zależymy od CORS dla originu `mail.google.com`.
  API i tak ma ścisłą konfigurację CORS na wypadek bezpośrednich wywołań.
* **Licencje:** logowanie działa zawsze (żeby interfejs mógł wyjaśnić problem), ale każdy endpoint CRM
  jest chroniony middlewarem `license`, który zwraca **HTTP 402** z
  `code: license_expired | license_missing`. Przy każdym 402 rozszerzenie pokazuje komunikat o odnowieniu.

## Szybki start – krok po kroku

### 0. Wymagania

| Narzędzie | Wersja | Sprawdzenie |
|---|---|---|
| PHP z rozszerzeniami `pdo_sqlite`, `mbstring`, `xml`, `curl` | 8.2+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 20.19+ lub 22.12+ | `node -v` |
| Google Chrome | 114+ | |
| Git | dowolna | `git --version` |

### 1. Pobierz kod

```bash
git clone https://github.com/karolmacieja/crm-mail.git
cd crm-mail
git checkout claude/gmail-crm-extension-laravel-gsketl
```

### 2. Uruchom backend (terminal nr 1)

```bash
cd backend
composer install
cp .env.example .env              # Windows: copy .env.example .env
php artisan key:generate
touch database/database.sqlite    # Windows: type nul > database\database.sqlite
php artisan migrate

# utwórz restaurację (grupę) i swoje konto z licencją na rok (komenda zapyta o hasło, min. 8 znaków)
php artisan crm:license twoj@email.pl --days=365 --name="Twoje Imię" --group="Moja Restauracja"

# opcjonalnie: dane demonstracyjne z makiety GastroFlowx (hasło do wszystkich kont: password)
# php artisan db:seed

php artisan serve                 # zostaw ten terminal otwarty
```

Sprawdzenie: http://localhost:8000/up powinno pokazać stronę „Application up”.

### 3. Zdobądź identyfikator InboxSDK

1. Wejdź na https://www.inboxsdk.com/register i zarejestruj aplikację (za darmo).
2. Skopiuj identyfikator w formacie `sdk_...`.

### 4. Zbuduj rozszerzenie (terminal nr 2)

```bash
cd frontend
npm install
cp .env.example .env              # Windows: copy .env.example .env
```

W pliku `frontend/.env` uzupełnij:

```
VITE_API_BASE_URL=http://localhost:8000/api
VITE_INBOXSDK_APP_ID=sdk_twoj_identyfikator
```

Następnie:

```bash
npm run build:extension           # wynik trafia do frontend/dist
```

### 5. Załaduj rozszerzenie do Chrome

1. Otwórz `chrome://extensions`.
2. W prawym górnym rogu włącz **Tryb programisty**.
3. Kliknij **Załaduj rozpakowane** i wskaż folder `frontend/dist`.
4. Przypnij ikonę „Gmail CRM” na pasku (ikona puzzla → pinezka).

### 6. Zaloguj się i korzystaj

1. Kliknij ikonę rozszerzenia, wybierz język (**PL** / **EN**) i zaloguj się danymi z kroku 2.
2. Otwórz (lub odśwież) https://mail.google.com.
3. Otwórz dowolnego maila. W prawym pasku bocznym Gmaila pojawi się ikona **GastroFlowx** z kartą
   nadawcy: dane kontaktowe i własne pola, szybka notatka, rezerwacje, zadania, przypomnienia i oś czasu.
   Nieznanego nadawcę dodasz do CRM jednym kliknięciem. Mail trafia automatycznie na oś czasu klienta.
4. Pełny CRM (Dashboard, Klienci, Kontakty, Zadania, Przypomnienia) otworzysz pozycją **GastroFlowx**
   w lewym menu Gmaila albo przyciskiem w prawym górnym rogu. Kliknięcie maila w sekcji „Ostatnie maile”
   wysuwa prawy panel z kartą klienta, a kliknięcie klienta w zakładce „Klienci” otwiera pełny profil.

**Dane demo:** `php artisan migrate:fresh --seed` (tylko lokalnie, kasuje bazę) tworzy dane z makiety.
Hasło do wszystkich kont to `password`:

| Konto | Gdzie się loguje |
|---|---|
| `kelner@roma.test`, `manager@roma.test` | rozszerzenie Gmail (Restauracja Roma) |
| `manager@sushi.test` | rozszerzenie Gmail (inna restauracja, dla sprawdzenia izolacji danych) |
| `master@gastroflowx.test` | panel webowy Master Admina |

### Rozwiązywanie problemów

| Objaw | Co zrobić |
|---|---|
| „Brak połączenia z serwerem CRM” | Sprawdź, czy w terminalu nr 1 działa `php artisan serve` |
| Brak ikony CRM w Gmailu | Odśwież kartę Gmaila (F5). Sprawdź konsolę (F12) pod kątem błędów `[Gmail CRM]`, np. złego `VITE_INBOXSDK_APP_ID` |
| „Gmail CRM został zaktualizowany” | Po przebudowaniu rozszerzenia kliknij ⟳ przy nim w `chrome://extensions` i odśwież Gmaila |
| „Twoja licencja wygasła” | `php artisan crm:license twoj@email.pl --days=365`, potem „Licencja odnowiona – sprawdź ponownie” |
| „Zbyt wiele żądań” przy logowaniu | Limit to 5 prób na minutę. Odczekaj minutę |
| Zmieniłeś `VITE_API_BASE_URL` | Uruchom ponownie `npm run build:extension` i przeładuj rozszerzenie (adres jest wbudowywany w manifest) |

Podczas pracy nad kodem zamiast `npm run build:extension` możesz użyć `npm run dev:extension`. Zmiany w Vue przeładują się automatycznie.

### 7. Panel webowy Master Admina (przeglądarka)

```bash
cd frontend
npm run dev:admin                 # http://localhost:5173 (ten adres jest w SANCTUM_STATEFUL_DOMAINS)
```

Zaloguj się kontem Master Admina (`php artisan crm:license ty@firma.pl --admin`). Produkcyjnie:
`npm run build:admin` tworzy `frontend/dist-admin/`. Serwuj go na np. `app.domena.pl` z przekierowaniem
wszystkich ścieżek na `index.html` (SPA) – pełna instrukcja w sekcji [Wdrożenie na własny serwer](#wdrożenie-na-własny-serwer-produkcja).

## Wdrożenie na własny serwer (produkcja)

Instrukcja dla serwera VPS z **Ubuntu 24.04**, Nginx i PHP 8.3. Zamień `domena.pl` na swoją domenę.
Masz hosting współdzielony z panelem DirectAdmin (np. Seohost.pl)? Przeczytaj też
[wariant dla DirectAdmin](#wariant-hosting-współdzielony-z-directadmin-np-seohostpl).

### 0. Domeny

API i panel admina muszą być subdomenami tej samej domeny, bo od tego zależy ciasteczko sesji panelu.
W DNS dodaj dwa rekordy A wskazujące na IP serwera:

* `api.domena.pl` – backend Laravel (korzysta z niego też rozszerzenie),
* `app.domena.pl` – panel Master Admina.

### 1. Pakiety

```bash
sudo apt update
sudo apt install -y nginx git unzip mariadb-server certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# Node.js 20+ (tylko do zbudowania frontendu; może być też na Twoim komputerze)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

### 2. Kod i baza danych

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone -b claude/gmail-crm-extension-laravel-gsketl https://github.com/karolmacieja/crm-mail.git gastroflowx
sudo chown -R $USER:www-data gastroflowx && cd gastroflowx/backend
composer install --no-dev --optimize-autoloader

sudo mysql -e "CREATE DATABASE gastroflowx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gastroflowx'@'localhost' IDENTIFIED BY 'MOCNE_HASLO';
GRANT ALL ON gastroflowx.* TO 'gastroflowx'@'localhost'; FLUSH PRIVILEGES;"
```

> Migracje i testy są sprawdzone na SQLite i MariaDB 10.11 (MySQL 8 działa tak samo). W małej skali wystarczy
> też SQLite (`DB_CONNECTION=sqlite`, bez tworzenia bazy w MariaDB).

### 3. Plik `backend/.env`

```bash
cp .env.example .env && php artisan key:generate
```

Zmień w nim:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domena.pl
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gastroflowx
DB_USERNAME=gastroflowx
DB_PASSWORD=MOCNE_HASLO

WEB_PANEL_URL=https://app.domena.pl
SANCTUM_STATEFUL_DOMAINS=app.domena.pl
SESSION_DOMAIN=.domena.pl
SESSION_SECURE_COOKIE=true
CHROME_EXTENSION_IDS=          # uzupełnisz w kroku 8
```

Następnie:

```bash
php artisan migrate --force
php artisan crm:license twoj@email.pl --admin     # konto Master Admina
php artisan config:cache && php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Nie uruchamiaj `db:seed` – to dane demonstracyjne (na produkcji i tak są zablokowane).

### 4. Panel admina

```bash
cd /var/www/gastroflowx/frontend
echo "VITE_API_BASE_URL=https://api.domena.pl/api" > .env
npm ci && npm run build:admin          # wynik: frontend/dist-admin
```

### 5. Nginx

`/etc/nginx/sites-available/gastroflowx`:

```nginx
server {
    server_name api.domena.pl;
    root /var/www/gastroflowx/backend/public;
    index index.php;
    client_max_body_size 10m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}

server {
    server_name app.domena.pl;
    root /var/www/gastroflowx/frontend/dist-admin;
    location / { try_files $uri /index.html; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/gastroflowx /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 6. HTTPS (wymagane)

```bash
sudo certbot --nginx -d api.domena.pl -d app.domena.pl
```

Sprawdzenie: `https://api.domena.pl/up` zwraca 200, a `https://app.domena.pl` pokazuje logowanie Master Admina.

### 7. Cron (codzienne czyszczenie wygasłych tokenów)

```bash
(sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/gastroflowx/backend && php artisan schedule:run >> /dev/null 2>&1") | sudo crontab -u www-data -
```

### 8. Rozszerzenie Chrome

Zbuduj je z adresem produkcyjnego API (na swoim komputerze albo na serwerze). W `frontend/.env`:

```ini
VITE_API_BASE_URL=https://api.domena.pl/api
VITE_INBOXSDK_APP_ID=sdk_twoj_identyfikator
VITE_GOOGLE_OAUTH_CLIENT_ID=            # opcjonalnie, krok 9
VITE_EXTENSION_KEY=                     # stałe ID rozszerzenia, patrz niżej
```

```bash
npm run zip:extension        # tworzy gastroflowx-extension.zip
```

Dystrybucja – jedna z dwóch dróg:

* **Chrome Web Store jako „Niepubliczne” (zalecane).** Wgrywasz ZIP, osoby instalują rozszerzenie z linku,
  aktualizacje przychodzą same, a ID jest stałe. Konto dewelopera kosztuje jednorazowo 5 USD. Klucz
  publiczny z panelu Store (Pakiet → Klucz publiczny) wpisz jako `VITE_EXTENSION_KEY`.
* **Ręcznie.** Każda osoba rozpakowuje ZIP i wczytuje go w `chrome://extensions` (tryb dewelopera →
  „Załaduj rozpakowane”). Żeby ID było takie samo na każdym komputerze, wygeneruj klucz raz:

  ```bash
  openssl genrsa 2048 | openssl pkcs8 -topk8 -nocrypt -out extension-key.pem   # trzymaj w sekrecie
  openssl rsa -in extension-key.pem -pubout -outform DER | base64 -w0          # wynik wpisz w VITE_EXTENSION_KEY
  ```

Po instalacji odczytaj ID rozszerzenia z `chrome://extensions` i wpisz je na serwerze:

```bash
# backend/.env
CHROME_EXTENSION_IDS=abcdefghijklmnopabcdefghijklmnop
php artisan config:cache
```

Bez tego API odrzuci żądania rozszerzenia (CORS), bo na produkcji dowolne ID nie jest dopuszczane.

### 9. Google (opcjonalnie)

Do importu historii maili i synchronizacji z Kalendarzem Google utwórz w Google Cloud klienta OAuth typu
„Rozszerzenie Chrome” z **produkcyjnym** ID rozszerzenia z kroku 8 i dodaj użytkowników testowych
(szczegóły w sekcji [Konfiguracja Google](#konfiguracja-google-wymagana-dla-historii-maili-i-synchronizacji-kalendarza)).
Potem wpisz `VITE_GOOGLE_OAUTH_CLIENT_ID` do `frontend/.env` i zbuduj rozszerzenie ponownie.

Link kalendarza (ICS) działa od razu po kroku 6, bo Google pobiera go z publicznego adresu `https://api.domena.pl`.

### Aktualizacje

```bash
cd /var/www/gastroflowx && git pull
cd backend && composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force && php artisan config:cache && php artisan route:cache
cd ../frontend && npm ci && npm run build:admin
# rozszerzenie: npm run zip:extension i nowa wersja w Web Store albo rozesłanie ZIP-a
```

### Wariant: hosting współdzielony z DirectAdmin (np. Seohost.pl)

Na hostingu współdzielonym nie masz `sudo`, `apt`, Nginx ani Certbota. Kroki 1, 5, 6 i 7 robisz w panelu
DirectAdmin, a resztę w terminalu SSH (np. *Terminal* w DirectAdmin). Serwer to zwykle LiteSpeed lub Apache:
oba czytają pliki `.htaccess`, a Laravel ma już własny (z przekazywaniem nagłówka `Authorization`).
W przykładach `LOGIN` to login konta hostingowego.

1. **PHP:** w DirectAdmin wybierz PHP **8.2 lub 8.3** (wybór wersji PHP) z rozszerzeniami `pdo_mysql`,
   `mbstring`, `intl`, `bcmath`, `zip`, `curl`. W SSH sprawdź `php -v`. Jeśli konsola ma inną wersję niż strony,
   używaj pełnej ścieżki, np. `/opt/alt/php83/usr/bin/php` (CloudLinux).
2. **Subdomeny:** w zarządzaniu subdomenami utwórz `api` i `app`. Powstaną foldery
   `~/domains/domena.pl/public_html/api` i `~/domains/domena.pl/public_html/app`.
3. **Baza:** w zarządzaniu MySQL utwórz bazę i użytkownika. Obie nazwy dostaną prefiks loginu, np.
   `LOGIN_gastro`, i właśnie takie wpisujesz do `.env`.
4. **Kod – zawsze poza `public_html`** (inaczej plik `.env` byłby dostępny z internetu):

   ```bash
   cd ~/domains/domena.pl
   git clone -b claude/gmail-crm-extension-laravel-gsketl https://github.com/karolmacieja/crm-mail.git gastroflowx
   cd gastroflowx/backend
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   # uzupełnij .env jak w kroku 3 powyżej (DB_DATABASE / DB_USERNAME z prefiksem)
   php artisan migrate --force
   php artisan crm:license twoj@email.pl --admin
   php artisan config:cache && php artisan route:cache
   ```

   Komendy `sudo` i `chown` pomijasz – pliki należą do Twojego konta.
5. **Subdomena API → `backend/public`** (zamiast konfiguracji Nginx):

   ```bash
   cd ~/domains/domena.pl/public_html
   rm -rf api
   ln -s ../gastroflowx/backend/public api
   ```

   Test: `https://api.domena.pl/up` zwraca 200.
6. **Panel admina:** zbuduj go na swoim komputerze (`VITE_API_BASE_URL=https://api.domena.pl/api` w
   `frontend/.env`, potem `npm run build:admin`). Całą zawartość `frontend/dist-admin/` – razem z ukrytym
   plikiem `.htaccess` – wgraj do `public_html/app` przez FTP lub menedżer plików. `.htaccess` kieruje
   wszystkie podstrony panelu do `index.html`, więc odświeżanie strony działa.
7. **SSL:** w DirectAdmin otwórz certyfikaty SSL, wystaw Let's Encrypt dla `api.domena.pl` i `app.domena.pl`,
   a potem włącz wymuszanie HTTPS.
8. **Cron:** w DirectAdmin → Zadania Cron dodaj zadanie co minutę (ścieżkę do PHP sprawdzisz komendą `which php`):

   ```
   * * * * * cd /home/LOGIN/domains/domena.pl/gastroflowx/backend && php artisan schedule:run >/dev/null 2>&1
   ```

9. **Rozszerzenie i Google:** bez zmian – kroki 8 i 9 powyżej.

**Panel pokazuje „Sesja wygasła” przy logowaniu** (błąd 419 – ochrona CSRF): przeglądarka nie może odczytać
ciasteczka `XSRF-TOKEN` ustawionego przez API. Sprawdź w `backend/.env`:

```ini
SESSION_DOMAIN=.domena.pl            # z kropką na początku; nie może zostać SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=app.domena.pl
WEB_PANEL_URL=https://app.domena.pl
```

a potem **zawsze** `php artisan config:clear && php artisan config:cache` (Laravel czyta zapamiętaną konfigurację,
nie plik `.env`). Wyczyść ciasteczka obu subdomen w przeglądarce i zaloguj się ponownie. Podgląd aktualnych
wartości: `php artisan config:show session` i `php artisan config:show sanctum`.

Aktualizacje: w SSH `git pull`, potem w `backend/` `composer install --no-dev --optimize-autoloader`,
`php artisan migrate --force` i `php artisan config:cache && php artisan route:cache`. Panel admina zbuduj
u siebie i wgraj ponownie.

## Język interfejsu

Rozszerzenie jest dostępne po polsku i angielsku. Domyślny język wynika z ustawień przeglądarki, a przełącznik
**PL / EN** jest w popupie, w nagłówku Panelu CRM i na dole panelu bocznego. Wybór zapisuje się w
`chrome.storage.local` i od razu obowiązuje we wszystkich kartach. Wyjątek to etykiety rysowane przez samego
Gmaila (pozycja w lewym menu), które zmieniają się po odświeżeniu karty.

Rozszerzenie wysyła wybrany język w nagłówku `Accept-Language`, a API (middleware `SetLocaleFromHeader`)
zwraca w nim komunikaty walidacji i błędów (`lang/pl`, `lang/en`). Tłumaczenia interfejsu są w
`frontend/src/shared/locales/pl.js` i `en.js`.

## Ustawienia, notatki, kalendarz i historia maili

### Panel ustawień (zakładka **Ustawienia** w Panelu CRM)

| Zakładka | Co można zrobić | Zakres |
|---|---|---|
| Kategorie klientów | Dodawanie, zmiana nazwy, koloru i ikony, kolejność (strzałki), usuwanie (klienci tracą kategorię) | Cała restauracja |
| Statusy | Jak wyżej + status **domyślny** dla nowych kontaktów. Usunięcie używanego statusu wymaga wskazania, dokąd przenieść kontakty; ostatniego nie da się usunąć | Cała restauracja |
| Kategorie zadań | Kolumny tablicy **Zadania** i typy w formularzu zadania; usuwanie z przeniesieniem zadań | Cała restauracja |
| Pola dodatkowe | Szablony pól (np. Alergie, NIP) podpowiadane jednym kliknięciem w karcie klienta | Cała restauracja |
| Preferencje | Domyślna godzina przypomnienia, domyślny termin zadania, język | Tylko moje konto |
| Kalendarz | Prywatny link ICS, powiadomienie przed terminem, rezerwacje w kalendarzu, połączenie z Google | Tylko moje konto |

Słowniki może edytować każdy członek restauracji (kelner i kierownik). Nowa restauracja dostaje zestaw
domyślnych statusów, kategorii zadań i pól.

### Notatki pod „Dane kontaktowe”

Notatka zapisana przez „Zapisz do osi czasu” trafia na oś czasu **i** do sekcji **Notatki** tuż pod danymi
kontaktowymi (3 ostatnie, licznik, „Pokaż wszystkie” filtruje oś czasu do notatek). Własne notatki można
edytować i usuwać.

### Kalendarz

* **Link ICS (działa bez konfiguracji):** Ustawienia → Kalendarz → „Utwórz link kalendarza”. Link zawiera
  moje otwarte zadania (przypisane do mnie albo moje nieprzypisane), moje przypomnienia i opcjonalnie
  rezerwacje restauracji, z powiadomieniem przed terminem. Dodaj go w Google Calendar („Z adresu URL”),
  Outlooku lub kalendarzu Apple. Link jest prywatny (losowy token, w bazie tylko hash); „Wygeneruj nowy link”
  unieważnia stary. Google odświeża subskrypcje z opóźnieniem do kilku godzin.
* **Synchronizacja z Kalendarzem Google (natychmiastowa):** po połączeniu konta Google zadania
  i przypomnienia są tworzone, aktualizowane i usuwane w głównym kalendarzu osoby, której dotyczą.
* Bez połączenia przy każdym zadaniu/przypomnieniu jest ikona „Dodaj do Kalendarza Google” (gotowy szablon
  wydarzenia).

### Pełna historia korespondencji

Po dodaniu kontaktu lub otwarciu klienta rozszerzenie pobiera przez Gmail API **wszystkie** wcześniejsze
wiadomości od i do tego adresu (do 500 najnowszych za jednym razem) i zapisuje je na osi czasu. Kolejne
importy są przyrostowe, a wiadomości już zapisane (także te dodane po kliknięciu w Gmailu) nie dublują się.
Na osi czasu jest też przycisk „Wczytaj historię z Gmaila” z postępem i datą ostatniej synchronizacji.

### Konfiguracja Google (wymagana dla historii maili i synchronizacji kalendarza)

1. W [Google Cloud Console](https://console.cloud.google.com/) utwórz projekt i włącz **Gmail API**
   oraz **Google Calendar API**.
2. Skonfiguruj ekran zgody OAuth (typ „Zewnętrzny”, dodaj siebie i współpracowników jako użytkowników
   testowych) ze scope'ami `gmail.readonly` i `calendar.events`.
3. Utwórz identyfikator klienta OAuth typu **Rozszerzenie Chrome** i wpisz ID rozszerzenia
   (z `chrome://extensions`). Aby ID się nie zmieniało (np. na innym komputerze), ustaw
   `VITE_EXTENSION_KEY` (klucz publiczny z panelu Chrome Web Store) – trafi do pola `key` manifestu.
4. W `frontend/.env` dopisz `VITE_GOOGLE_OAUTH_CLIENT_ID=…apps.googleusercontent.com`, zbuduj ponownie
   rozszerzenie i przeładuj je.
5. W Ustawienia → Kalendarz kliknij „Połącz z Google”.

Bez tej zmiennej rozszerzenie działa normalnie (bez uprawnienia `identity`), a w ustawieniach widać
informację, że integracja nie jest skonfigurowana. **Uwaga:** `gmail.readonly` to zakres „restricted” –
do publicznej dystrybucji (Chrome Web Store, użytkownicy spoza listy testowej) Google wymaga weryfikacji
aplikacji i audytu bezpieczeństwa.

## Model danych (multi-tenancy)

Każda restauracja to **grupa** (`groups`). Wszystkie dane operacyjne mają kolumnę `group_id` i trait
`App\Models\Concerns\BelongsToGroup`, który:

* dodaje Global Scope `GroupScope`, więc zalogowany użytkownik widzi wyłącznie dane swojej grupy
  (cudze rekordy dają 404, użytkownik bez grupy nie widzi nic);
* sam uzupełnia `group_id` przy tworzeniu i rzuca `MissingGroupContext`, gdy nie wiadomo, do jakiej grupy
  zapisać rekord. `group_id` nie jest „fillable”, więc nie da się go podmienić z requestu.

Kontekst grupy trzyma `App\Support\Tenancy\Tenancy`: `runAs($grupa, fn)` (np. w kolejkach),
`withoutScope(fn)` (raporty Master Admina), a w zapytaniach scope `Model::forGroup($grupa)`.
Master Admin (`role = master_admin`, bez grupy) widzi wszystkie grupy.

| Tabela | Zawartość |
|---|---|
| `groups` | Restauracje (tenanci), strefa czasowa |
| `users` | `group_id`, `role`: `master_admin` / `manager` / `staff` |
| `licenses`, `license_user` | Licencja grupy z liczbą miejsc i przydziały miejsc użytkownikom |
| `contacts` | Kontakty i klienci (`is_client`), `category_id`, firma, ostatnia aktywność |
| `contact_categories` | Kategorie grupy (domyślnie B2B, VIP, Indywidualni) |
| `contact_custom_fields` | Własne pola kontaktu, np. „Alergie” (typy: tekst, liczba, data, tak/nie) |
| `tasks` | Zadania: typ (follow-up / oferta / wewnętrzne), priorytet, osoba przypisana, mail źródłowy |
| `reservations` | Data, godzina, liczba gości, status, stolik, okazja, `source_email_id` |
| `reminders` | Przypomnienia (z maila / rezerwacji / ogólne) |
| `activities` | Oś czasu (polimorficzna): maile, notatki, zdarzenia systemowe |

Statusy czasowe zadań i przypomnień (zaległe / dziś / 7 dni) nie są zapisywane w bazie. Liczą je scope'y
`overdue()`, `dueToday($tz)` i `upcoming($tz)` w strefie czasowej restauracji, więc nigdy się nie
dezaktualizują.

## Backend

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite        # albo ustaw DB_* dla MySQL
php artisan migrate

# utworzenie użytkownika i licencji (komenda zapyta o hasło)
php artisan crm:license ty@firma.pl --days=365 --name="Ty" --group="Moja Restauracja"
php artisan crm:license wlasciciel@firma.pl --admin   # Master Admin: loguje się tylko w panelu webowym
php artisan db:seed                                   # dane demo (tylko poza produkcją)

php artisan serve                     # http://localhost:8000
php artisan test                      # 70 testów
```

Na produkcji uruchom scheduler (`php artisan schedule:work` albo cron), żeby codziennie usuwać wygasłe tokeny.

### Dwa typy logowania

| | Rozszerzenie Gmail (pracownicy) | Panel webowy (Master Admin) |
|---|---|---|
| Logowanie | `POST /api/auth/login` → token Bearer | `GET /sanctum/csrf-cookie`, potem `POST /api/web/login` → ciasteczko sesji |
| Mechanizm | Sanctum Personal Access Token z uprawnieniem `crm` | Sanctum SPA (sesja + CSRF) |
| Kto może | `staff` / `manager` z grupą (restauracją) i miejscem w licencji | tylko `master_admin` |
| Middleware | `auth:sanctum` → `client:extension` → `tenant` → `license` | `auth:sanctum` → `client:web` → `admin` |
| Dostęp | dane CRM własnej restauracji | grupy, użytkownicy, licencje; **bez** danych CRM |

`client:web` przyjmuje wyłącznie sesję, więc token z rozszerzenia nigdy nie dotrze do zarządzania licencjami,
nawet jeśli należy do Master Admina. Master Admin nie dostaje też tokena rozszerzenia (`403 use_web_panel`).
Błędy mają pole `code` (`wrong_client`, `no_group`, `group_inactive`, `license_expired`, `license_missing`, `admin_only`).

### API rozszerzenia (`/api`, token Bearer)

| Metoda | Ścieżka | Uwagi |
|---|---|---|
| POST | `/auth/login` | `{email, password, device_name?}` → `{token, expires_at, user}` (limit 5/min) |
| GET / POST | `/auth/me`, `/auth/logout` | Działa także bez ważnej licencji |
| GET | `/dashboard/summary` | Osobne liczniki i listy dla **zadań** i **przypomnień**: `overdue`, `due_today`, `upcoming_week`; do tego kontakty, rezerwacje, maile |
| GET | `/contacts` | `search, status, category (id/slug), is_client, sort, direction, page, per_page` |
| GET | `/contacts/lookup?email=` | Pełna karta albo `{"data": null}` |
| CRUD | `/contacts/{id}` | Karta: kategoria, własne pola, zadania, przypomnienia, nadchodzące rezerwacje |
| POST / PATCH / DELETE | `/contacts/{id}/custom-fields[/{field}]` | „Dodaj pole”, np. Alergie (typy: text, number, date, boolean) |
| GET | `/contacts/{id}/activities` | Oś czasu, `type=email\|note\|system`, stronicowana |
| POST | `/contacts/{id}/notes` | „Zapisz do osi czasu” |
| POST | `/contacts/{id}/emails` | Zapis maila z Gmaila na osi czasu (idempotentny po `message_id`) |
| CRUD | `/tasks` | `window=overdue\|today\|upcoming\|open\|done\|all`, `type`, `priority`, `urgent=1`, `assigned_to=me\|id`, `search` |
| CRUD | `/reminders` | `window`, `type=email\|reservation\|general`, `search`, `contact_id`, `reservation_id` |
| CRUD | `/reservations` | `from, to, status, contact_id, upcoming=1` |
| GET | `/categories`, `/team` | Kategorie grupy; współpracownicy (do przypisywania zadań) |
| GET | `/settings` | Wszystkie słowniki grupy (kategorie, statusy, kategorie zadań, szablony pól) z liczbą użyć |
| POST / PATCH / DELETE | `/settings/{categories\|statuses\|task-categories\|field-templates}[/{id}]` | Usuwanie używanego statusu/kategorii zadań wymaga `?move_to=<key>` |
| POST | `/settings/{słownik}/reorder` | `{ids: [...]}` |
| GET / PATCH | `/me/preferences` | Preferencje użytkownika (działa także bez licencji) |
| POST / DELETE | `/me/calendar-feed` | Utworzenie nowego / wyłączenie prywatnego linku ICS |
| GET | `/calendar/{token}.ics` | Publiczny (token w adresie) kanał iCalendar, limit 60/min |
| PUT / DELETE | `/calendar-events/{task\|reminder}/{id}` | Powiązanie z wydarzeniem w Kalendarzu Google |
| PATCH / DELETE | `/activities/{id}` | Edycja / usunięcie własnej notatki |
| POST | `/contacts/{id}/emails/import` | Import historii (do 500 maili; `complete=true` zapisuje datę synchronizacji) |

### API panelu webowego (`/api`, sesja, tylko Master Admin)

| Metoda | Ścieżka | Uwagi |
|---|---|---|
| POST / GET / POST | `/web/login`, `/web/me`, `/web/logout` | Logowanie sesyjne |
| GET | `/admin/stats` | Grupy, użytkownicy bez miejsca, licencje wygasające w ciągu 30 dni |
| CRUD | `/admin/groups` | Usunięcie wymaga `?confirm=<slug>` i kasuje dane restauracji oraz jej konta |
| CRUD | `/admin/users` | `role`, `group_id`, opcjonalnie `license_id` (od razu przydziela miejsce) |
| POST | `/admin/users/{id}/revoke-tokens` | Wylogowuje użytkownika ze wszystkich rozszerzeń |
| CRUD | `/admin/licenses` | `group_id, seats, days \| expires_at, plan, status`; liczba miejsc nie może spaść poniżej zajętych |
| POST | `/admin/licenses/{id}/extend` | `{days}` |
| POST / DELETE | `/admin/licenses/{id}/assignments[/{user}]` | Przydział / zwolnienie miejsca |

### CORS i domeny

`config/cors.php` dopuszcza panel webowy (`WEB_PANEL_URL`), rozszerzenie (`CHROME_EXTENSION_IDS`) i
`https://mail.google.com`, z `supports_credentials=true` (wymagane przez sesję panelu). Lokalnie, przy pustym
`CHROME_EXTENSION_IDS`, dopuszczane jest dowolne ID rozszerzenia, ale nigdy na produkcji.

Na produkcji panel i API muszą mieć wspólną domenę nadrzędną, np.:

```
API:   https://api.domena.pl      WEB_PANEL_URL=https://app.domena.pl
Panel: https://app.domena.pl      SANCTUM_STATEFUL_DOMAINS=app.domena.pl
                                  SESSION_DOMAIN=.domena.pl
                                  SESSION_SECURE_COOKIE=true
                                  CHROME_EXTENSION_IDS=<id z chrome://extensions>
```

Ciasteczko sesji ma `SameSite=Lax`, a Sanctum uruchamia sesję tylko dla domen ze `SANCTUM_STATEFUL_DOMAINS`.
Żądanie z Gmaila nie może więc użyć sesji Master Admina (zwraca 401).

## Frontend (`frontend/`)

Jeden projekt Vue 3 budowany na dwa sposoby:

| | Rozszerzenie Gmail (pracownicy) | Panel webowy (Master Admin) |
|---|---|---|
| Uruchomienie | `npm run dev:extension` / `build:extension` → `dist/` | `npm run dev:admin` / `build:admin` → `dist-admin/` |
| Konfiguracja Vite | `vite.config.js` (CRXJS, Manifest V3) | `vite.admin.config.js` (zwykłe SPA) |
| Router | `createMemoryHistory()`, bo adresem zarządza Gmail | `createWebHistory()` |
| Trasy | `/dashboard`, `/clients`, `/clients/:id`, `/contacts`, `/tasks`, `/reminders` | `/`, `/groups`, `/groups/:id`, `/users`, `/licenses` |
| Meta tras | `requiresAuth`, `requiresLicense`, `roles: ['manager','staff']` | `requiresAuth`, `roles: ['master_admin']` |
| Logowanie | token w `chrome.storage.local`, zapytania przez service worker | sesja Sanctum + CSRF (`withCredentials`, `withXSRFToken`) |

```
frontend/src/
├── shared/      i18n (PL/EN), formatowanie, ApiError, guard routera, wspólne komponenty, Tailwind
├── extension/   service worker, content script (InboxSDK), popup, klient API przez service worker
├── crm/         CRM w Gmailu: router, store'y Pinia, layout, widoki, karta klienta (ContextSidebar / FullClientProfile)
└── admin/       panel Master Admina: router, store'y Pinia, widoki, klient API sesyjny
```

**Guard routera** (`shared/router/guards.js`) czyta meta tras w obu aplikacjach. Reaguje też na zmiany sesji
poza nawigacją: wylogowanie w popupie, 401 albo 402 z API od razu przenosi na logowanie lub ekran licencji,
a po zalogowaniu wraca do żądanej strony (`?redirect=`).

**Widoki (makieta GastroFlowx):**

| CRM w Gmailu | Panel Master Admina |
|---|---|
| `DashboardView`: KPI, przypomnienia i zadania (Zaległe / Dziś / 7 dni), ostatnie maile ze skrzynki | `DashboardView`: restauracje, użytkownicy, zajęte miejsca, licencje wygasające w 30 dni |
| `ClientsView`: klienci pogrupowani po kategoriach (B2B, VIP…), wyszukiwarka i filtr | `GroupsView` / `GroupDetailView`: restauracje, ich pracownicy i licencje, usuwanie z potwierdzeniem |
| `FullClientProfile`: pełna karta klienta zamiast listy | `UsersView`: konta, role, grupa, przydział miejsca, wylogowanie z rozszerzeń |
| `ContactsView`: książka adresowa, zamiana kontaktu w klienta | `LicensesView`: miejsca, przedłużanie (+30 dni / +1 rok), przydziały, zawieszanie |
| `TasksView`: tablica Pilne / Follow-up / Oferty / Wewnętrzne | |
| `RemindersView`: z maili / rezerwacje / pozostałe | |
| `ContextSidebar`: prawy panel (450 px) wysuwany po kliknięciu maila | |
| `ThreadPanelApp`: ta sama karta klienta w pasku bocznym otwartego maila w Gmailu | |

Sekcje karty klienta (`crm/components/client/`): `ContactDetails` (z własnymi polami), `QuickNote`,
`ReservationsPanel`, `ClientWork` (zadania i przypomnienia) i `Timeline`. Składają je `ContextSidebar`,
`FullClientProfile` i `ThreadPanelApp`, a dane pochodzą z jednego `useClientStore`. Ikony to Font Awesome
w wersji SVG, wbudowane w paczkę, bo w Manifest V3 nie wolno ładować skryptów z CDN.

**Ostatnie maile:** Gmail nie udostępnia rozszerzeniom listy skrzynki bez OAuth, więc content script zbiera
wiersze, które Gmail wyświetla w skrzynce odbiorczej (InboxSDK `ThreadRowView`). Plakietki B2B/VIP pochodzą
z jednego zapytania `POST /contacts/lookup-many`. Wiersz zna tylko ID wątku, więc oś czasu zapisuje go
tymczasowo jako `thread:<id>`. Po otwarciu maila w Gmailu wpis jest podmieniany na prawdziwą wiadomość i nie
powstaje duplikat.

**Store'y Pinia:**

| Store | Rola |
|---|---|
| `useClientStore` | Klient/mail w fokusie. Zasila `ContextSidebar` (klik maila) i `FullClientProfile` (klik w „Klienci”): profil, oś czasu, notatki, rezerwacje, własne pola, zadania i przypomnienia klienta. Odrzuca odpowiedzi dla maila, który nie jest już w fokusie |
| `useDashboardStore` | Liczniki zadań i przypomnień (zaległe / dziś / 7 dni), odświeżane po każdej zmianie |
| `useContactsStore` | „Klienci” (grupowani po kategoriach) i „Kontakty” (książka adresowa), kategorie |
| `useTasksStore` | Moduł zadań i kolumny z makiety: Pilne, Follow-up, Oferty, Wewnętrzne |
| `useRemindersStore` | Przypomnienia pogrupowane: z maili, rezerwacje, ogólne |
| `useAuthStore` (CRM), `useAdminAuthStore` | Sesja rozszerzenia (token) i panelu (cookie) |
| `useGroupsStore`, `useUsersStore`, `useLicensesStore`, `useAdminStatsStore` | Panel Master Admina |

Zmiana w zadaniu albo przypomnieniu aktualizuje otwarty profil klienta i liczniki dashboardu. Zmiana
zalogowanego pracownika czyści dane wszystkich store'ów CRM.

```bash
npm test          # Vitest: guard routera, store'y, klient API panelu (CSRF/419/401), router CRM
```

## Uwagi

* Laravel 11 nie dostaje już poprawek bezpieczeństwa, a `composer audit` zgłasza podatności naprawione
  dopiero w wersjach 12.x/13.x. Przed wdrożeniem zalecana jest aktualizacja
  (`composer require laravel/framework:^12`, a potem uruchomienie testów).
* ID aplikacji InboxSDK to prawdziwy identyfikator powiązany z Twoim rozszerzeniem. Bez niego build kończy się błędem.
