-- This schema defines the core data model for Bookshelf.
-- The application stores books, the series they belong to, and each user's
-- personal reading status for a given title.

-- A series is a collection such as a trilogy or a long-running book line.
-- Some books can be linked to a series and keep track of their position inside it.
CREATE TABLE IF NOT EXISTS series
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_api_id VARCHAR(255) NULL UNIQUE,
    name            VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- The books table stores canonical book information fetched from an external API.
CREATE TABLE IF NOT EXISTS books
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_api_id VARCHAR(255)  NULL UNIQUE,
    title           VARCHAR(255)  NOT NULL,
    description     TEXT          NULL,
    cover_url       VARCHAR(2048) NULL,
    release_date    DATE          NULL,
    series_id       INT UNSIGNED  NULL,
    series_position DECIMAL(6, 2) NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_books_series
        FOREIGN KEY (series_id) REFERENCES series (id)
            ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS authors
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_api_id VARCHAR(255) NULL UNIQUE,
    name            VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_authors
(
    book_id   INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, author_id),
    CONSTRAINT fk_book_authors_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_book_authors_author
        FOREIGN KEY (author_id) REFERENCES authors (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Genres are normalized separately so the same Hardcover tag can be reused by many books.
CREATE TABLE IF NOT EXISTS genres
(
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE,
    slug       VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_genres
(
    book_id  INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, genre_id),
    CONSTRAINT fk_book_genres_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_book_genres_genre
        FOREIGN KEY (genre_id) REFERENCES genres (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- user_books tracks how a specific user is interacting with a book.
-- A book can only appear once per user, so each row is unique to one book.
CREATE TABLE IF NOT EXISTS user_books
(
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- A single-user installation needs only one status row per book.
    book_id       INT UNSIGNED                                                  NOT NULL UNIQUE,
    status        ENUM ('want_to_read', 'reading', 'finished', 'paused', 'dnf') NOT NULL DEFAULT 'want_to_read',
    rating        TINYINT UNSIGNED                                              NULL,
    date_started  DATE                                                          NULL,
    date_finished DATE                                                          NULL,
    created_at    TIMESTAMP                                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP                                                     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_user_books_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5),
    CONSTRAINT fk_user_books_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Owned formats are independent of reading status and may exist without user_books.
CREATE TABLE IF NOT EXISTS user_book_formats
(
    book_id INT UNSIGNED                            NOT NULL,
    format  ENUM ('physical', 'ebook', 'audiobook') NOT NULL,
    PRIMARY KEY (book_id, format),
    CONSTRAINT fk_user_book_formats_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


-- The lists table stores user-defined lists of books. Each list can have a name and an optional description.
CREATE TABLE IF NOT EXISTS lists
(
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- The book_lists table is a many-to-many relationship between books and lists. Each row represents a book being part of a list.
CREATE TABLE IF NOT EXISTS book_lists
(
    book_id  INT UNSIGNED NOT NULL,
    lists_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, lists_id),
    CONSTRAINT fk_book_lists_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_book_lists_list
        FOREIGN KEY (lists_id) REFERENCES lists (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- The tags table stores user-defined tags that can be applied to books. Each tag has a name and a slug for URL-friendly representation.
CREATE TABLE IF NOT EXISTS tags
(
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) NOT NULL UNIQUE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- The book_tags table is a many-to-many relationship between books and tags. Each row represents a book being associated with a tag.
CREATE TABLE IF NOT EXISTS book_tags
(
    book_id INT UNSIGNED NOT NULL,
    tags_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, tags_id),
    CONSTRAINT fk_book_tags_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_book_tags_tag
        FOREIGN KEY (tags_id) REFERENCES tags (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Reading attempts are historical records independent from the current
-- user_books status. Multiple attempts can therefore coexist with one
-- current library status.
CREATE TABLE IF NOT EXISTS reading_sessions
(
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id             INT UNSIGNED NOT NULL,
    status              ENUM ('reading', 'paused', 'finished', 'dnf', 'abandoned') NOT NULL,
    start_date          DATE NULL,
    start_date_precision ENUM ('day', 'month', 'year', 'unknown') NOT NULL DEFAULT 'unknown',
    finish_date         DATE NULL,
    finish_date_precision ENUM ('day', 'month', 'year', 'unknown') NOT NULL DEFAULT 'unknown',
    dnf_progress_type   ENUM ('percentage', 'page', 'time') NULL,
    dnf_progress_value  DECIMAL(10, 2) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reading_sessions_book_status (book_id, status),
    CONSTRAINT fk_reading_sessions_book FOREIGN KEY (book_id) REFERENCES books (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reading_session_formats
(
    reading_session_id INT UNSIGNED NOT NULL,
    format ENUM ('physical', 'ebook', 'audiobook') NOT NULL,
    PRIMARY KEY (reading_session_id, format),
    CONSTRAINT fk_reading_session_formats_session FOREIGN KEY (reading_session_id)
        REFERENCES reading_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
