const statusValues = ["want_to_read", "reading", "finished", "paused", "dnf"];
const formatValues = ["physical", "ebook", "audiobook"];

/** Searches locally saved books by title or author. */
export async function searchLocalBooks(query = "") {
    const params = new URLSearchParams();
    if (query.trim()) params.set("q", query.trim());
    const response = await fetch(`/api/books.php${params.size ? `?${params}` : ""}`);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not search the local library.");
    return Array.isArray(payload) ? payload : [];
}

/** Searches Hardcover through the server-side normalized search endpoint. */
export async function searchHardcoverBooks(query) {
    const response = await fetch(`/api/search-books.php?q=${encodeURIComponent(query.trim())}`);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not search Hardcover.");
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
 * @returns {Promise<Object>} Normalized book returned by the API.
 * @throws {Error} When the status is invalid or the API rejects the request.
 */
export async function saveBookStatus(book, status) {
    if (!statusValues.includes(status)) throw new Error("Invalid reading status.");
    const response = await fetch("/api/books.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ hardcover_id: book.hardcover_id, status }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not save the book.");
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
    const response = await fetch(`/api/books.php?hardcover_id=${encodeURIComponent(book.hardcover_id)}`, { method: "DELETE" });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not remove the book.");
    return payload;
}

/** Retrieves independently owned formats for a canonical local book. */
export async function getOwnedFormats(bookId) {
    const response = await fetch(`/api/formats.php?book_id=${encodeURIComponent(bookId)}`);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not load owned formats.");
    return payload.formats ?? [];
}

/** Replaces the owned-format set without creating or requiring a reading-status row. */
export async function updateOwnedFormats(bookId, formats) {
    if (!Array.isArray(formats) || formats.some(format => !formatValues.includes(format))) throw new Error("Invalid owned format.");
    const response = await fetch("/api/formats.php", {method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify({book_id: bookId, formats: [...new Set(formats)]})});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not save owned formats.");
    return payload.formats ?? [];
}

/** Removes one owned format, or all formats when format is omitted. */
export async function removeOwnedFormat(bookId, format = null) {
    if (format !== null && !formatValues.includes(format)) throw new Error("Invalid owned format.");
    const query = new URLSearchParams({book_id: String(bookId)});
    if (format !== null) query.set("format", format);
    const response = await fetch(`/api/formats.php?${query}`, {method: "DELETE"});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not remove owned format.");
    return payload.formats ?? [];
}
