<!-- Written by AI -->

# Bookshelf

[Architecture and review notes](docs/architecture.md) explain the data flow, validation commands, and known limitations.

**Dansk** · [Read in English](#bookshelf---english)

Personligt bog- og lydbogsbibliotek til at finde, gemme, organisere og filtrere bøger. Bygget med Vue, PHP og MariaDB.

En demo af Bookshelf med eksempeldata kan afprøves
her: [bookshelf.flipdesign.dk](https://bookshelf.flipdesign.dk/library).

Du er velkommen til at udforske applikationen og afprøve de forskellige funktioner. Du må frit tilføje, redigere og
slette bøger og andre data, da demoen udelukkende indeholder testdata.

## Lokal udvikling

**Forudsætninger:** Docker Desktop med Docker Compose, Node.js og npm.

Første gang skal du kopiere `.env.example` til `.env`, installere afhængighederne og starte udviklingsmiljøet:

```powershell
Copy-Item .env.example .env
npm install
npm --prefix frontend install
npm run dev
```

`npm run dev` starter Docker-miljøet og Vue/Vite samtidig. Tjenesterne findes på:

- **Frontend:** http://localhost:5173
- **PHP/Apache:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8080

Bogforsider konverteres til WebP ved første visning med PHP GD (JPEG, PNG og WebP understøttes). Efter ændringer i PHP
Dockerfile skal PHP-imaget bygges igen med `docker compose up -d --build php`.

De originale forside-URL'er bevares i databasen. Kildebilleder og varianter på 320, 640 og 960 px gemmes i den separate
Docker-volume `cover_cache`, som overlever genopbygning af containere. Cachen kan ryddes ved at slette filerne under
`/var/cache/bookshelf/covers` i PHP-containeren. De oprettes igen ved næste billedforespørgsel. **Slet ikke
MariaDB-volumen for at rydde billedcachen.**

Docker-kommandoer:

```powershell
npm run docker:start
npm run docker:stop
npm run docker:rebuild
npm run docker:status
npm run docker:logs
```

MariaDB is initialized from `database/schema.sql` only when its volume is first created.
Existing databases have targeted scripts in `database/migrations/`. Back up data and
review the applicable scripts before running them manually; code updates do not require a reset.

PHP-forbindelsen bruger miljøvariablerne fra `.env` og PDO med `utf8mb4`. Databaseværten inde i Docker er `mariadb`.

## Hardcover-søgning

Hardcover bruges som ekstern datakilde til bogsøgning. Opret et API-token under kontoens API-indstillinger i Hardcover,
og indsæt det lokalt i `.env`:

```dotenv
HARDCOVER_API_TOKEN=din-lokale-token
```

Tokenet skal have tilladelsen `read:catalog`. Det bruges kun på serveren og må aldrig committes.

Søg efter bøger med:

```text
GET http://localhost:8000/api/search-books.php?q=Ironside
```

Endpointet returnerer normaliserede bogdata fra Hardcover. Søgeresultater gemmes ikke automatisk i MariaDB.

En valgt bog kan gemmes med:

```http
POST http://localhost:8000/api/books.php
Content-Type: application/json

{"hardcover_id":1286648}
```

PHP bruger kun Hardcover-ID'et fra requesten og henter de autoritative bogdetaljer på serveren. Nye bøger får status
`want_to_read`. Gentagne POST-kald opretter ikke dubletter. Hvis bogen allerede findes i databasen, genbruges den
eksisterende bog.

The requested status is applied (`want_to_read` when omitted).

## Tests

Alle testfiler ligger i projektets `tests/`-mappe:

- `tests/frontend/`: Vitest-tests grupperet efter kildekodemappe.
- `tests/backend/`: selvstændige PHP-tests af bogforsider.
- `tests/integration/`: skrivebeskyttede HTTP-tests mod de kørende PHP- og MariaDB-tjenester.

Kør fra projektets rodmappe:

```sh
npm --prefix frontend run test
npm --prefix frontend run test:watch
php tests/backend/cover_images_test.php
php tests/integration/pagination_test.php
npm --prefix frontend run build
```

Integrationstesten bruger som standard `http://localhost:8000`. Adressen kan ændres med miljøvariablen
`BOOKSHELF_TEST_URL`. Start tjenesterne med `docker compose up -d`.

For en fuld test af bogforsider med Dockers GD/WebP- og cURL-udvidelser:

```sh
docker compose run --rm --no-deps --volume .:/workspace:ro php php /workspace/tests/backend/cover_images_test.php
```

Forsidetesten kontrollerer også fallback, når PHP-funktioner er deaktiveret via `-d disable_functions=imagewebp` eller
`-d disable_functions=curl_init`.

Frontend-afhængighederne og konfigurationen til Vite/Vitest findes fortsat i `frontend/`. De eksisterende scripts til
tests og watch kan også køres derfra.

# Bookshelf - English

[Dansk version](#bookshelf)

A personal book and audiobook library for discovering, saving, organizing, and filtering books. Built with Vue, PHP, and
MariaDB.

A demo of Bookshelf with sample data is available
here: [bookshelf.flipdesign.dk](https://bookshelf.flipdesign.dk/library).

Feel free to explore the application and try out its features. You are welcome to add, edit, and delete books and other
data, as the demo only contains test data.

## Local development

**Requirements:** Docker Desktop with Docker Compose, Node.js, and npm.

On first setup, copy `.env.example` to `.env`, install dependencies, and start the development environment:

```powershell
Copy-Item .env.example .env
npm install
npm --prefix frontend install
npm run dev
```

`npm run dev` starts both the Docker environment and Vue/Vite. Services are available at:

- **Frontend:** http://localhost:5173
- **PHP/Apache:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8080

Book covers are converted to WebP on first request using PHP GD (JPEG, PNG, and WebP are supported). After changing the
PHP Dockerfile, rebuild the PHP image with `docker compose up -d --build php`.

Original cover URLs remain in the database. Source images and 320, 640, and 960 px variants are stored in a separate
Docker volume, `cover_cache`, which survives container rebuilds. To clear the cache, delete files under
`/var/cache/bookshelf/covers` inside the PHP container. They will be recreated on the next image request. **Do not
delete the MariaDB volume to clear the image cache.**

Docker commands:

```powershell
npm run docker:start
npm run docker:stop
npm run docker:rebuild
npm run docker:status
npm run docker:logs
```

MariaDB is initialized from `database/schema.sql` only when its volume is first created.
Existing databases have targeted scripts in `database/migrations/`. Back up data and
review the applicable scripts before running them manually; code updates do not require a reset.

The PHP connection uses environment variables from `.env` and PDO with `utf8mb4`. The database hostname inside Docker is
`mariadb`.

## Hardcover search

Hardcover is the external data source for book searches. Create an API token in your Hardcover account's API settings
and add it to your local `.env`:

```dotenv
HARDCOVER_API_TOKEN=your-local-token
```

The token requires the `read:catalog` permission. It is server-side configuration only and must never be committed.

Search for books using:

```text
GET http://localhost:8000/api/search-books.php?q=Ironside
```

The endpoint returns normalized book data from Hardcover. Search results are not automatically saved to MariaDB.

Save a selected book using:

```http
POST http://localhost:8000/api/books.php
Content-Type: application/json

{"hardcover_id":1286648}
```

PHP uses only the Hardcover ID from the request and fetches authoritative book details on the server. New books receive
the `want_to_read` status. Repeated POST requests do not create duplicates. If the book already exists in the database,
the existing book is reused and the requested status is applied (`want_to_read` when omitted).

## Tests

All test files are in the root `tests/` directory:

- `tests/frontend/`: Vitest tests grouped by source directory.
- `tests/backend/`: standalone PHP cover image tests.
- `tests/integration/`: read-only HTTP tests against the running PHP and MariaDB services.

Run from the project root:

```sh
npm --prefix frontend run test
npm --prefix frontend run test:watch
php tests/backend/cover_images_test.php
php tests/integration/pagination_test.php
npm --prefix frontend run build
```

The integration test uses `http://localhost:8000` by default. Override it with `BOOKSHELF_TEST_URL` if needed. Start the
services with `docker compose up -d`.

For full cover image checks with Docker's GD/WebP and cURL extensions:

```sh
docker compose run --rm --no-deps --volume .:/workspace:ro php php /workspace/tests/backend/cover_images_test.php
```

The cover test also checks missing-extension fallbacks when PHP functions are disabled with
`-d disable_functions=imagewebp` or `-d disable_functions=curl_init`.

Frontend dependencies and Vite/Vitest configuration remain in `frontend/`; the existing test and watch scripts also work
from that directory.
