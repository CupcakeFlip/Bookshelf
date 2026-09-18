<?php

// This endpoint returns all books from the local database as JSON.
// It is a simple read-only API used while the application is still in early stages.

// Reuse the shared database connection setup from the config file.
$pdo = require __DIR__ . '/../config/database.php';

// Tell the browser we are returning JSON instead of HTML.
header('Content-Type: application/json; charset=utf-8');

// Fetch every row from the books table and convert it into a JSON response.
$stmt = $pdo->query('SELECT * FROM books');
$books = $stmt->fetchAll();

echo json_encode($books);