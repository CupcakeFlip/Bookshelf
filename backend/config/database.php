<?php

declare(strict_types=1);

// This file centralizes the database connection logic.
// By reading DB settings from environment variables, we can switch between
// local development and production without changing the app code itself.

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'bookshelf';
$username = getenv('DB_USERNAME') ?: 'bookshelf';
$password = getenv('DB_PASSWORD') ?: '';

// Build the PDO DSN used to connect to MySQL.
// The utf8mb4 charset ensures that special characters in titles and authors
// are stored and returned correctly.
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $database
);

// Return a configured PDO instance so all API files can reuse the same database
// connection and behavior across the application.
return new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
