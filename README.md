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

Endpointet returnerer normaliserede bogdata fra Hardcover. Søgeresultater gemmes ikke automatisk i MariaDB; lagring implementeres separat senere.
