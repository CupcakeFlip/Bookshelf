<?php

declare(strict_types=1);

/**
 * Loads the shared environment-backed MariaDB connection.
 *
 * @return PDO Configured database connection.
 */
return require __DIR__ . '/../settings/init.php';
