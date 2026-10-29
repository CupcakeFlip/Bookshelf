<?php

declare(strict_types=1);

/**
 * Opens and returns the shared MariaDB connection used by PHP endpoints.
 * Reads database settings from the environment and passes them to Database,
 * keeping connection setup in one place.
 */

require_once __DIR__ . '/../classes/Database.php';

/** Read database settings from the Docker environment, with local defaults. */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'bookshelf';
$username = getenv('DB_USERNAME') ?: 'bookshelf';
$password = getenv('DB_PASSWORD') ?: '';

// Create one connection that can be reused by the including PHP file.
$db = Database::connect($host, $port, $database, $username, $password);

return $db;
