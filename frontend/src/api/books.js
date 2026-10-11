const statusValues = ["want_to_read", "reading", "finished", "dnf"];

/**
 * Saves a book in the local library or updates its reading status.
 *
 * The PHP endpoint uses the Hardcover ID as the stable identifier, so the
 * frontend does not need to know the local MariaDB primary key.
 *
 * @param {{hardcover_id: string|number}} book Book selected by the user.
 * @param {"want_to_read"|"reading"|"finished"|"dnf"} status New reading status.
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
