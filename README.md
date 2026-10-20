# Bookshelf
Personligt bog- og lydbogsbibliotek til at finde, gemme, organisere og filtrere bøger. Bygget med Vue, PHP og MySQL.

## Lokal udvikling

Forudsætninger: Docker Desktop med Docker Compose, Node.js og npm.

Første gang skal du kopiere `.env.example` til `.env`, installere afhængighederne og starte miljøet:

```powershell
Copy-Item .env.example .env
npm install
npm --prefix frontend install
npm run dev
```

`npm run dev` starter Docker-miljøet og Vue/Vite samtidig. Frontend findes på http://localhost:5173, PHP/Apache på http://localhost:8000, og phpMyAdmin på http://localhost:8080.

Bogforsider konverteres ved første visning til WebP med PHP GD (JPEG, PNG og WebP understøttes). Efter ændringer i PHP Dockerfile skal PHP-imaget bygges igen med `docker compose up -d --build php`. Originale forside-URL'er bevares i databasen. Kildebilleder og 320/640/960 px-varianter gemmes i den separate Docker-volume `cover_cache`, som overlever container-rebuilds. For at rydde cachen kan filerne under `/var/cache/bookshelf/covers` slettes i PHP-containeren; de oprettes igen ved næste billedrequest. Slet ikke MariaDB-volumen for at rydde billedcachen.

Docker-kommandoer:

```powershell
npm run docker:start
npm run docker:stop
npm run docker:rebuild
npm run docker:status
npm run docker:logs
```

MariaDB initialiseres første gang den navngivne volume oprettes. `database/schema.sql` opretter tabellerne for bøger, serier, forfattere, genrer og brugerstatus. Ved ændringer i det lokale schema skal volume nulstilles, da der ikke bruges migrationer:

```powershell
docker compose down -v
npm run docker:start
```

PHP-forbindelsen bruger miljøvariablerne i `.env` og PDO med `utf8mb4`. Databaseværten inde i Docker er `mariadb`.

## Hardcover-søgning

Hardcover bruges som ekstern datakilde til bogsøgning. Opret et API-token i Hardcover under kontoens API-indstillinger, og indsæt det lokalt i `.env`:

```dotenv
HARDCOVER_API_TOKEN=din-lokale-token
```

Tokenet skal have tilladelsen `read:catalog`. Tokenet er kun serverkonfiguration og skal aldrig committes.

Søg efter bøger med:

```text
http://localhost:8000/api/search-books.php?q=Ironside
```

Endpointet returnerer normaliserede bogdata fra Hardcover. Søgeresultater gemmes ikke automatisk i MariaDB.

En valgt bog kan gemmes med:

```http
POST http://localhost:8000/api/books.php
Content-Type: application/json

{"hardcover_id": 1286648}
```

PHP bruger kun Hardcover-ID'et fra requesten og henter de autoritative bogdetaljer server-side. Nye bøger får status `want_to_read`. Gentagne POST-kald er idempotente: den eksisterende bog og brugerstatus genbruges uden dubletter.

## Lister

The Lists page automatically groups library books into Want to Read,
Currently Reading, Finished, Paused, and Did Not Finish using
`user_books.status`. These lists are virtual and are not stored in the manual
`lists` tables. Selecting one opens `/lists/:status`, where its books are
loaded from `books.php` in newest `user_books.updated_at` order.

Library and automatic list detail pages support debounced title/author search,
multiple genre filters, and selectable sorting. The books API accepts optional
`q`, `status`, `genres` (comma-separated genre IDs), and `sort` parameters.
Supported sort values are `recently_updated_desc`, `recently_updated_asc`,
`title_asc`, `title_desc`, `release_date_desc`, `release_date_asc`,
`author_asc`, and `author_desc`.

Lister og deres bøger håndteres via:

```text
GET    http://localhost:8000/api/lists.php
GET    http://localhost:8000/api/lists.php?id=1
POST   http://localhost:8000/api/lists.php
PUT    http://localhost:8000/api/lists.php?id=1
DELETE http://localhost:8000/api/lists.php?id=1
```

Opret eller opdater en liste med `name`, en valgfri `description` og eventuelt
`book_ids`, for eksempel `{"name":"Fantasy","book_ids":[1,2]}`. `PUT` erstatter
bogtilknytningerne, når `book_ids` er med; udelades feltet, bevares de
eksisterende tilknytninger. Sletning af en liste sletter også dens
`book_lists`-rækker, men ikke bøgerne.

## Reading history

  Book metadata keeps Hardcover's optional `headline` and `description` as
  separate fields. Both are refreshed by manual metadata synchronization and
  are displayed in that order on Book Details and the reusable BookCard. When a
  description repeats the headline at its beginning, the repeated prefix is
  removed only for display; the stored fields remain unchanged.

  Metadata synchronization selects every existing book with a valid Hardcover
  ID and reports `selected`, `processed`, `updated`, `unchanged`, and `failed`.
  An updated count means at least one shared metadata or relationship changed;
  unchanged means the book was successfully checked and required no change;
  failed means the book could not be checked or persisted. API and database
  errors are included per book without exposing the Hardcover token.

Reading history is stored in `reading_sessions`, independently of the current
`user_books.status` and of owned formats in `user_book_formats`. Apply
`database/migrations/002_reading_sessions.sql` to an existing database; it is
safe to run repeatedly and leaves legacy `date_started` and `date_finished`
values unchanged. Fresh installations receive the same tables from
`database/schema.sql`.

The session API uses a Hardcover ID to identify a book:

```text
GET    /api/reading-sessions.php?hardcover_id=1286648
POST   /api/reading-sessions.php
PATCH  /api/reading-sessions.php?id=1
DELETE /api/reading-sessions.php?id=1
```

Example session payload:

```json
{"hardcover_id":1286648,"status":"dnf","start_date":"2026-10-01","start_date_precision":"day","finish_date":"2026-10-12","finish_date_precision":"day","formats":["ebook"],"dnf_progress_type":"percentage","dnf_progress_value":40}
```

Dates are entered as separate year, month, and day fields. The API infers and
stores `day`, `month`, `year`, or `unknown` precision without inventing missing
values. DNF progress supports `percentage`, positive
`page` values, or non-negative audiobook `time` values in minutes. Changing a
book through `books.php` creates, pauses, resumes, completes, or abandons the
active session transactionally; cancelling a future completion form makes no
request and therefore changes no status.

Only one `reading` or `paused` session can exist for a book. This is protected
by row locks on the canonical `books` row in every API transaction that can
create or change a session.
The Finished status dialog is shared by every `BookStatusDropdown` instance,
including cards in the Library and Frontpage. It supplies optional local-date
defaults and sends only the API session fields; cancelling it leaves the book
status unchanged.
Selecting DNF uses the same shared dialog. It records an optional stopped date,
optional format, and at most one optional progress value: percentage, page, or
time stored as total minutes for audiobook progress. An active reading session
is updated in place; otherwise a new DNF session is created.
Editing or deleting an active session reconciles `user_books.status`; editing
or deleting a historical session does not. Removing a book from the library
abandons an active session before deleting only its `user_books` association.

For existing databases, apply migration `002_reading_sessions.sql`. Legacy `date_started` and
`date_finished` values are intentionally preserved and are not converted into
sessions because their original precision and reading-attempt boundaries are
unknown. A `month` date is stored on day 1, a `year` date on January 1, and an
`unknown` date is stored as `NULL`.
