-- Repeatable migration for existing MariaDB volumes. Existing user_books rows survive.
ALTER TABLE user_books
    MODIFY COLUMN status ENUM ('want_to_read', 'reading', 'finished', 'paused', 'dnf')
        NOT NULL DEFAULT 'want_to_read';

CREATE TABLE IF NOT EXISTS user_book_formats
(
    book_id INT UNSIGNED NOT NULL,
    format  ENUM ('physical', 'ebook', 'audiobook') NOT NULL,
    PRIMARY KEY (book_id, format),
    CONSTRAINT fk_user_book_formats_book
        FOREIGN KEY (book_id) REFERENCES books (id)
            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
