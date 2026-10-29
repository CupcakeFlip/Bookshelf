/* Made with AI */

/**
 * Builds cached cover URLs and responsive image choices for book components.
 * Uses the PHP cover endpoint for supported Hardcover images and retries
 * an original image if an optimized version fails to load.
 */
const widths = [320, 640, 960];

/**
 * Uses the PHP cover cache only for supported Hardcover URLs and widths.
 * @param {string} original Original cover URL.
 * @param {number} [width=320] Requested cache width.
 * @returns {string} Proxy URL, original URL, or an empty string.
 */
export function optimizedCoverUrl(original, width = 320) {
    if (!original) return "";
    try {
        const url = new URL(original);
        if (
            url.protocol !== "https:" ||
            url.hostname !== "assets.hardcover.app" ||
            url.username ||
            url.password ||
            url.port ||
            url.hash ||
            original.length > 2048 ||
            !widths.includes(width)
        ) return original;
    } catch {
        return original;
    }
    return `/api/cover.php?${new URLSearchParams({ url: original, w: String(width) })}`;
}

/**
 * Builds responsive image candidates for covers supported by the cache.
 * @param {string} original Original cover URL.
 * @returns {string} Width candidates, or an empty string for unsupported URLs.
 */
export function optimizedCoverSrcset(original) {
    if (!original || optimizedCoverUrl(original) === original) return "";
    return widths.map((width) => `${optimizedCoverUrl(original, width)} ${width}w`).join(", ");
}

/**
 * Retries the original image once after a proxy or srcset failure.
 * @param {Event} event Image error event.
 * @param {string} original Original cover URL.
 * @returns {void}
 */
export function fallbackCoverImage(event, original) {
    const image = event.currentTarget;
    if (!image || !original || optimizedCoverUrl(original) === original) return;
    if (image.getAttribute("src") === original && !image.hasAttribute("srcset")) return;
    // Clear candidates first so the browser cannot select another failed variant.
    image.removeAttribute("srcset");
    image.removeAttribute("sizes");
    image.setAttribute("src", original);
}
