/* Made with AI */

/**
 * Provides shared frontend requests for book search, status, ownership, and history.
 * Translates user actions into calls to the PHP endpoints and reports request
 * errors so components and views can handle results consistently.
 */
const statusValues = ["want_to_read", "reading", "finished", "paused", "dnf"];
const formatValues = ["physical", "ebook", "audiobook"];

/**
 * Collects every local result page for the combined Catalogue search.
 * @param {string} [query=""] Title or author search text.
 * @returns {Promise<Object[]>} Saved books in server sort order.
 * @throws {Error} If a page cannot be fetched.
 */
export async function searchLocalBooks(query = "") {
    const books = [];
    let page = 1;
    let totalPages;
    do {
        const params = new URLSearchParams({q: query.trim(), page: String(page), limit: "96"});
        const response = await fetch(`/api/books.php?${params}`);
        const payload = await response.json().catch(() => ({}));
        if (!response.ok)
            throw new Error(payload.error || "Could not search the local library.");
        books.push(...payload.books);
        totalPages = payload.totalPages;
        page++;
    } while (page <= totalPages);
    return books;
}

/**
 * Searches Hardcover through PHP so the token stays on the server.
 * @param {string} query Search text.
 * @returns {Promise<Object[]>} Normalized external results.
 * @throws {Error} If the request fails.
 */
export async function searchHardcoverBooks(query) {
    const response = await fetch(
        `/api/search-books.php?q=${encodeURIComponent(query.trim())}`,
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not search Hardcover.");
    return Array.isArray(payload.results) ? payload.results : [];
}

/**
 * Saves a book in the local library or updates its reading status.
 *
 * The PHP endpoint uses the Hardcover ID as the stable identifier, so the
 * frontend does not need to know the local MariaDB primary key.
 *
 * @param {{hardcover_id: string|number}} book Book selected by the user.
 * @param {"want_to_read"|"reading"|"finished"|"paused"|"dnf"} status New reading status.
 * @param {Object} [session={}] Confirmed reading-session fields.
 * @returns {Promise<Object>} Normalized book returned by the API.
 * @throws {Error} When the status is invalid or the API rejects the request.
 */
export async function saveBookStatus(book, status, session = {}) {
    if (!statusValues.includes(status))
        throw new Error("Invalid reading status.");
    const response = await fetch("/api/books.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            hardcover_id: book.hardcover_id,
            status,
            session,
        }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not save the book.");
    return payload;
}

/**
 * Removes a book's user-library status while leaving shared book metadata intact.
 *
 * @param {{hardcover_id: string|number}} book Book selected by the user.
 * @returns {Promise<Object>} API response confirming the removal.
 * @throws {Error} When the API rejects the request.
 */
export async function removeBookStatus(book) {
    const response = await fetch(
        `/api/books.php?hardcover_id=${encodeURIComponent(book.hardcover_id)}`,
        {method: "DELETE"},
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not remove the book.");
    return payload;
}

/**
 * Loads ownership independently of reading status and session formats.
 * @param {number} bookId Local database ID, not a Hardcover ID.
 * @returns {Promise<string[]>} Saved format names.
 * @throws {Error} If the request fails.
 */
export async function getOwnedFormats(bookId) {
    const response = await fetch(
        `/api/formats.php?book_id=${encodeURIComponent(bookId)}`,
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not load owned formats.");
    return payload.formats ?? [];
}

/**
 * Replaces the complete owned-format set after validating its values.
 * @param {number} bookId Local database ID.
 * @param {string[]} formats Selected physical, ebook, or audiobook values.
 * @returns {Promise<string[]>} Server-confirmed format set.
 * @throws {Error} If validation or the request fails.
 */
export async function updateOwnedFormats(bookId, formats) {
    if (
        !Array.isArray(formats) ||
        formats.some((format) => !formatValues.includes(format))
    )
        throw new Error("Invalid owned format.");
    const response = await fetch("/api/formats.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            book_id: bookId,
            formats: [...new Set(formats)],
        }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not save owned formats.");
    return payload.formats ?? [];
}

/**
 * Removes one owned format, or all when no format is supplied.
 * @param {number} bookId Local database ID.
 * @param {string|null} [format=null] Format to remove.
 * @returns {Promise<string[]>} Remaining formats.
 * @throws {Error} If validation or the request fails.
 */
export async function removeOwnedFormat(bookId, format = null) {
    if (format !== null && !formatValues.includes(format))
        throw new Error("Invalid owned format.");
    const query = new URLSearchParams({book_id: String(bookId)});
    if (format !== null) query.set("format", format);
    const response = await fetch(`/api/formats.php?${query}`, {
        method: "DELETE",
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not remove owned format.");
    return payload.formats ?? [];
}

/**
 * Loads reading attempts for a saved book.
 * @param {Object|string|number} book Book object or Hardcover ID.
 * @returns {Promise<Object[]>} Sessions ordered newest first by the server.
 * @throws {Error} If the request fails.
 */
export async function getReadingSessions(book) {
    const response = await fetch(
        `/api/reading-sessions.php?hardcover_id=${encodeURIComponent(book.hardcover_id ?? book)}`,
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not load reading history.");
    return payload.sessions ?? [];
}

/**
 * Creates a reading attempt using API fields from ReadingSessionForm.
 * @param {Object|string|number} book Book object or Hardcover ID.
 * @param {Object} session Dates, precision, status, formats, and optional DNF progress.
 * @returns {Promise<Object>} Persisted session, including its local ID.
 * @throws {Error} If validation or the request fails.
 */
export async function createReadingSession(book, session) {
    const response = await fetch("/api/reading-sessions.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            hardcover_id: book.hardcover_id ?? book,
            ...session,
        }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not create reading session.");
    return payload;
}

/**
 * Updates supplied fields of an existing reading attempt.
 * @param {number} sessionId Local reading-session ID.
 * @param {Object} session Changed API fields.
 * @returns {Promise<Object>} Persisted session.
 * @throws {Error} If validation or the request fails.
 */
export async function updateReadingSession(sessionId, session) {
    const response = await fetch(
        `/api/reading-sessions.php?id=${encodeURIComponent(sessionId)}`,
        {
            method: "PATCH",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(session),
        },
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not update reading session.");
    return payload;
}

/**
 * Deletes a reading attempt; PHP reconciles active reading status.
 * @param {number} sessionId Local reading-session ID.
 * @returns {Promise<Object>} Deletion confirmation.
 * @throws {Error} If the request fails.
 */
export async function deleteReadingSession(sessionId) {
    const response = await fetch(
        `/api/reading-sessions.php?id=${encodeURIComponent(sessionId)}`,
        {method: "DELETE"},
    );
    const payload = await response.json().catch(() => ({}));
    if (!response.ok)
        throw new Error(payload.error || "Could not delete reading session.");
    return payload;
}
