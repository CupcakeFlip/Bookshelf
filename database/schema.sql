-- This schema defines the core data model for Bookshelf.
-- The application stores books, the series they belong to, and each user's
-- personal reading status for a given title.

-- A series is a collection such as a trilogy or a long-running book line.
-- Some books can be linked to a series and keep track of their position inside it.
CREATE TABLE IF NOT EXISTS series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_api_id VARCHAR(255) NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The books table stores the canonical book information we fetch from the API.
-- Each book has an author, optional cover URL, and an optional series relation.
CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_api_id VARCHAR(255) NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    cover_url VARCHAR(2048) NULL,
    series_id INT UNSIGNED NULL,
    series_position DECIMAL(6,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_books_series
        FOREIGN KEY (series_id) REFERENCES series (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_books tracks how a specific user is interacting with a book.
-- A book can only appear once per user, so each row is unique to one book.
CREATE TABLE IF NOT EXISTS user_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL UNIQUE,
    status ENUM('want_to_read', 'reading', 'finished', 'dnf') NOT NULL DEFAULT 'want_to_read',
    rating TINYINT UNSIGNED NULL,
    date_started DATE NULL,
    date_finished DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_user_books_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5),
    CONSTRAINT fk_user_books_book
        FOREIGN KEY (book_id) REFERENCES books (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
