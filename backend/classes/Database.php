<?php

declare(strict_types=1);

/**
 * Creates configured PDO connections for the application.
 */
final class Database
{
    /**
     * Opens a PDO connection to the configured MariaDB database.
     *
     * @param string $host Database server hostname.
     * @param string $port Database server port.
     * @param string $database Database name.
     * @param string $username Database username.
     * @param string $password Database password.
     * @return PDO Configured database connection.
     */
    public static function connect(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password
    ): PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
