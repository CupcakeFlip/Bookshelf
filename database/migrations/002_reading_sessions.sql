-- Safe, repeatable migration. Existing books, statuses, dates, and owned
-- formats are preserved while reading history is added as a separate relation.
CREATE TABLE IF NOT EXISTS reading_sessions
(
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id               INT UNSIGNED                                               NOT NULL,
    status                ENUM ('reading', 'paused', 'finished', 'dnf', 'abandoned') NOT NULL,
    start_date            DATE                                                       NULL,
    start_date_precision  ENUM ('day', 'month', 'year', 'unknown')                   NOT NULL DEFAULT 'unknown',
    finish_date           DATE                                                       NULL,
    finish_date_precision ENUM ('day', 'month', 'year', 'unknown')                   NOT NULL DEFAULT 'unknown',
    dnf_progress_type     ENUM ('percentage', 'page', 'time')                        NULL,
    dnf_progress_value    DECIMAL(10, 2)                                             NULL,
    created_at            TIMESTAMP                                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP                                                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reading_sessions_book_status (book_id, status),
    CONSTRAINT fk_reading_sessions_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reading_session_formats
(
    reading_session_id INT UNSIGNED                            NOT NULL,
    format             ENUM ('physical', 'ebook', 'audiobook') NOT NULL,
    PRIMARY KEY (reading_session_id, format),
    CONSTRAINT fk_reading_session_formats_session FOREIGN KEY (reading_session_id) REFERENCES reading_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
