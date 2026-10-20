const STORAGE_KEY = "booksPerPage";
const ALLOWED_LIMITS = [12, 24, 48, 96];
const DEFAULT_LIMIT = 24;

export function getSavedPageLimit() {
    const saved = Number(localStorage.getItem(STORAGE_KEY));

    return ALLOWED_LIMITS.includes(saved)
        ? saved
        : DEFAULT_LIMIT;
}

export function savePageLimit(limit) {
    if (!ALLOWED_LIMITS.includes(limit)) return;

    localStorage.setItem(STORAGE_KEY, String(limit));
}