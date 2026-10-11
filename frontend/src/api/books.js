const statusValues = ["want_to_read", "reading", "finished", "dnf"];

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

export async function removeBookStatus(book) {
    const response = await fetch(`/api/books.php?hardcover_id=${encodeURIComponent(book.hardcover_id)}`, { method: "DELETE" });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || "Could not remove the book.");
    return payload;
}
