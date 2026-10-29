/**
 * Prepares separate headline and description text for cards and book details.
 * Removes a repeated headline from the displayed description while leaving
 * the stored Hardcover metadata unchanged.
 */
/**
 * Separates display text without changing the stored Hardcover metadata.
 * @param {{headline?: string, description?: string}} [book={}] Book text fields.
 * @returns {{headline: string, description: string}} Trimmed text with a duplicate headline prefix removed.
 */
export function bookText(book = {}) {
    const headline =
        typeof book.headline === "string" ? book.headline.trim() : "";
    const description =
        typeof book.description === "string" ? book.description.trim() : "";
    if (!headline) {
        return {headline: "", description};
    }
    if (!description) {
        return {headline, description: ""};
    }
    if (description === headline) {
        return {headline, description: ""};
    }
    if (description.startsWith(headline)) {
        return {
            headline,
            description: description.slice(headline.length).replace(/^\s+/, ""),
        };
    }
    return {headline, description};
}
