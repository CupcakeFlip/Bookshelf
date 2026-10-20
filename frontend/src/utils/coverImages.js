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
