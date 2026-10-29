/**
 * Shares the saved books-per-page preference between collection views.
 * Checks supported page sizes and tolerates unavailable browser storage,
 * allowing each view to keep ownership of its current page.
 */
const STORAGE_KEY = "booksPerPage";
const ALLOWED_LIMITS = [12, 24, 48, 96];
const DEFAULT_LIMIT = 24;

/** Reads the page-size preference, falling back when browser storage is blocked.
 * @returns {number} One of the page sizes supported by the API.
 */
export function getSavedPageLimit() {
    try {
        const saved = Number(localStorage.getItem(STORAGE_KEY));
        return ALLOWED_LIMITS.includes(saved) ? saved : DEFAULT_LIMIT;
    } catch {
        return DEFAULT_LIMIT;
    }
}

/** Saves an allowed page size; storage failure must not prevent navigation.
 * @param {number} limit Requested number of books per page.
 * @returns {void}
 */
export function savePageLimit(limit) {
    if (!ALLOWED_LIMITS.includes(limit)) {
        return;
    }

    try {
        localStorage.setItem(STORAGE_KEY, String(limit));
    } catch {
        // The current view still keeps its selection in reactive state.
    }
}
