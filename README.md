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

Dates are stored as normalized SQL dates with a separate `day`, `month`,
`year`, or `unknown` precision. DNF progress supports `percentage`, positive
`page` values, or non-negative audiobook `time` values in minutes. Changing a
book through `books.php` creates, pauses, resumes, completes, or abandons the
active session transactionally; cancelling a future completion form makes no
request and therefore changes no status.
