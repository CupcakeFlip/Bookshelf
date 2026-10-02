<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';

// Read local Docker database settings from the environment.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'bookshelf';
$username = getenv('DB_USERNAME') ?: 'bookshelf';
$password = getenv('DB_PASSWORD') ?: '';

// Create one connection that can be reused by the including PHP file.
$db = Database::connect($host, $port, $database, $username, $password);

return $db;
