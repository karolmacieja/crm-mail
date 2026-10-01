# Gmail CRM

CRM w modelu SaaS działający wewnątrz Gmaila: **rozszerzenie Chrome (Manifest V3, Vue 3, InboxSDK)** połączone z **API w Laravel 11**, z tokenami Sanctum i licencjami przypisanymi do użytkowników.

```
crm-mail/
├── backend/     API w Laravel 11 (Sanctum, licencje, kontakty, zadania, dashboard)
└── extension/   Vite + CRXJS + Vue 3 + Pinia + Tailwind + InboxSDK
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
cd extension
npm install
cp .env.example .env              # Windows: copy .env.example .env
```

W pliku `extension/.env` uzupełnij:

```
VITE_API_BASE_URL=http://localhost:8000/api
VITE_INBOXSDK_APP_ID=sdk_twoj_identyfikator
```

Następnie:

```bash
npm run build                     # wynik trafia do extension/dist
```

### 5. Załaduj rozszerzenie do Chrome

1. Otwórz `chrome://extensions`.
2. W prawym górnym rogu włącz **Tryb programisty**.
3. Kliknij **Załaduj rozpakowane** i wskaż folder `extension/dist`.
4. Przypnij ikonę „Gmail CRM” na pasku (ikona puzzla → pinezka).

### 6. Zaloguj się i korzystaj

1. Kliknij ikonę rozszerzenia, wybierz język (**PL** / **EN**) i zaloguj się danymi z kroku 2.
2. Otwórz (lub odśwież) https://mail.google.com.
3. Otwórz dowolnego maila. Po prawej stronie, w pasku bocznym Gmaila, pojawi się ikona CRM.
   Kliknij ją, żeby zobaczyć kartę nadawcy, dodać go do CRM i zaplanować zadania.
4. Pełny **Panel CRM** otworzysz z pozycji w lewym menu Gmaila albo przyciskiem „CRM” w prawym górnym rogu.

### Rozwiązywanie problemów

| Objaw | Co zrobić |
|---|---|
| „Brak połączenia z serwerem CRM” | Sprawdź, czy w terminalu nr 1 działa `php artisan serve` |
| Brak ikony CRM w Gmailu | Odśwież kartę Gmaila (F5). Sprawdź konsolę (F12) pod kątem błędów `[Gmail CRM]`, np. złego `VITE_INBOXSDK_APP_ID` |
| „Gmail CRM został zaktualizowany” | Po przebudowaniu rozszerzenia kliknij ⟳ przy nim w `chrome://extensions` i odśwież Gmaila |
| „Twoja licencja wygasła” | `php artisan crm:license twoj@email.pl --days=365`, potem „Licencja odnowiona – sprawdź ponownie” |
| „Zbyt wiele żądań” przy logowaniu | Limit to 5 prób na minutę. Odczekaj minutę |
| Zmieniłeś `VITE_API_BASE_URL` | Uruchom ponownie `npm run build` i przeładuj rozszerzenie (adres jest wbudowywany w manifest) |

Podczas pracy nad kodem zamiast `npm run build` możesz użyć `npm run dev`. Zmiany w Vue przeładują się automatycznie.

## Język interfejsu

Rozszerzenie jest dostępne po polsku i angielsku. Domyślny język wynika z ustawień przeglądarki, a przełącznik
**PL / EN** jest w popupie, w nagłówku Panelu CRM i na dole panelu bocznego. Wybór zapisuje się w
`chrome.storage.local` i od razu obowiązuje we wszystkich kartach. Wyjątek to etykiety rysowane przez samego
Gmaila (pozycja w lewym menu), które zmieniają się po odświeżeniu karty.

Rozszerzenie wysyła wybrany język w nagłówku `Accept-Language`, a API (middleware `SetLocaleFromHeader`)
zwraca w nim komunikaty walidacji i błędów (`lang/pl`, `lang/en`). Tłumaczenia interfejsu są w
`extension/src/locales/pl.js` i `en.js`.

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
php artisan test                      # 68 testów
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

## Rozszerzenie

```bash
cd extension
npm install
cp .env.example .env
#   VITE_API_BASE_URL=http://localhost:8000/api
#   VITE_INBOXSDK_APP_ID=sdk_xxx   ← darmowe, z https://www.inboxsdk.com/register
npm run dev      # build z HMR w dist/
npm run build    # build produkcyjny w dist/ (API spoza localhost musi używać https)
```

Żeby załadować rozszerzenie, otwórz `chrome://extensions`, włącz tryb programisty, kliknij **Załaduj rozpakowane** i wskaż `extension/dist`.

| Plik | Do czego służy |
|---|---|
| `manifest.config.js` | Manifest MV3 (kompilowany do `dist/manifest.json`). Origin API trafia do `host_permissions` z `.env` |
| `vite.config.js` | Vue, CRXJS i kopiowanie `pageWorld.js` z InboxSDK do katalogu głównego rozszerzenia |
| `src/background/index.js` | Wstrzykiwanie pageWorld InboxSDK (MV3) i proxy API z autoryzacją |
| `src/content/content.js` | InboxSDK: panel boczny wątku, własna trasa, pozycja w menu, przycisk na pasku |
| `src/content/mount.js` | Montowanie Vue w Shadow DOM |
| `src/lib/i18n.js`, `src/locales/` | Tłumaczenia PL/EN i przełączanie języka |
| `src/lib/api.js` | Instancja axios, adapter do service workera, ujednolicanie błędów (`ApiError`) |
| `src/stores/auth.js`, `src/stores/crm.js` | Store'y Pinia (sesja i licencja; cache kontaktów, zadań i dashboardu) |
| `src/sidebar/SidebarApp.vue` | Karta kontaktu nadawcy: dodawanie, edycja, status, zadania |
| `src/dashboard/DashboardApp.vue` | Dashboard pełnoekranowy: liczniki, przypomnienia, tabela klientów |
| `src/popup/` | Popup na pasku przeglądarki: logowanie, status licencji, wylogowanie |

## Uwagi

* Laravel 11 nie dostaje już poprawek bezpieczeństwa, a `composer audit` zgłasza podatności naprawione
  dopiero w wersjach 12.x/13.x. Przed wdrożeniem zalecana jest aktualizacja
  (`composer require laravel/framework:^12`, a potem uruchomienie testów).
* ID aplikacji InboxSDK to prawdziwy identyfikator powiązany z Twoim rozszerzeniem. Bez niego build kończy się błędem.
