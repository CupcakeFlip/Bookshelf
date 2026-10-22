const widths = [320, 640, 960];

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

export function optimizedCoverSrcset(original) {
    if (!original || optimizedCoverUrl(original) === original) return "";
    return widths.map((width) => `${optimizedCoverUrl(original, width)} ${width}w`).join(", ");
}

/** Retry an optimized cover once at its original URL, including srcset failures. */
export function fallbackCoverImage(event, original) {
    const image = event.currentTarget;
    if (!image || !original || optimizedCoverUrl(original) === original) return;
    if (image.getAttribute("src") === original && !image.hasAttribute("srcset")) return;
    // Clear candidates first so the browser cannot select another failed variant.
    image.removeAttribute("srcset");
    image.removeAttribute("sizes");
    image.setAttribute("src", original);
}
