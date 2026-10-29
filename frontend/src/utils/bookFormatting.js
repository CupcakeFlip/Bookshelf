/* Made with AI */

/**
 * Provides shared author labels, genre names, and genre-filter categories.
 * Shares display rules between book cards, catalogue search, and collection
 * filters, with explicit genre categories for the available local genres.
 */
/**
 * Formats author names without changing the source data.
 * @param {string|Array<string|{name?: string}>} authors Author names or API objects.
 * @returns {string} Up to two names, followed by et al. when needed.
 */
export function formatAuthors(authors) {
    // Hardcover responses use both strings and objects, so normalize the
    // input locally instead of changing the API response shape.
    const values = Array.isArray(authors)
        ? authors
        : typeof authors === "string"
            ? [authors]
            : [];
    const names = values
        .map((author) => (typeof author === "string" ? author : author?.name))
        .filter((name) => typeof name === "string" && name.trim())
        .map((name) => name.trim());
    if (names.length <= 2) return names.join(", ");
    return `${names.slice(0, 2).join(", ")} et al.`;
}

const STANDARD_GENRES = [
    "Comedy",
    "Contemporary",
    "Fantasy",
    "Historical Fiction",
    "Horror",
    "Mystery",
    "Non-Fiction",
    "Romance",
    "Science Fiction",
    "Thriller",
];

const GENRE_ALIASES = new Map(
    [
        ["scifi", "Science Fiction"],
        ["sciencefiction", "Science Fiction"],
        ["nonfiction", "Non-Fiction"],
        ["ya", "Young Adult"],
        ["young adult fiction", "Young Adult"],
        ["youngadult", "Young Adult"],
        ["romcom", "Romantic Comedy"],
        ["romanticcomedy", "Romantic Comedy"],
        ["historical", "Historical Fiction"],
        ["dystopia", "Dystopian"],
        ["litrpg (literary Role-playing Game)", "Litrpg"],
        ["literary fiction", "Literary"],
        ["literary & fiction", "Literary"],
        ["literary and fiction", "Literary"],
        ["lit rpg", "Litrpg"],
        ["litRpg", "Litrpg"],
        ["humor", "Comedy"],
        ["humorous", "Comedy"],
        ["comedy & humor", "Comedy"],
        ["comedy and humor", "Comedy"],
        ["genre fiction", "Fiction"],
        ["childrens", "Children"],
        ["thrillers", "thriller"],
        ["children's audiobooks", "Children"],
        ["middle grade", "Children"],
    ].map(([alias, genre]) => [genreKey(alias), genre]),
);

// Explicit category membership avoids guessing from words in genre names.
// Keep this explicit list aligned with backend/api/books.php.
const GENRE_CATEGORIES = {
    Paranormal: ["Paranormal", "Paranormal Romance", "Paranormal Fantasy"],
    Dark: ["Dark", "Dark Romance", "Dark Fantasy"],
    Urban: ["Urban", "Urban Fantasy"],
    Historical: [
        "Historical",
        "Historical Romance",
        "Historical Fiction",
        "History",
    ],
    Fantasy: [
        "Fantasy",
        "Fantasy Romance",
        "Fantasy romance",
        "Romantasy",
        "Epic Fantasy",
        "Urban Fantasy",
        "Dark Fantasy",
        "High Fantasy",
        "Paranormal Fantasy",
        "Science Fiction & Fantasy",
        "Science Fiction and Fantasy",
        "Fantasy & Science Fiction",
        "Fantasy and Science Fiction ",
        "Romance|science Fiction & Fantasy",
        "Magic",
        "Romance fantasy",
        "dragons",
    ],
    Romance: [
        "Romance",
        "Fantasy Romance",
        "Fantasy romance",
        "Romantasy",
        "Dark Romance",
        "Contemporary Romance",
        "Paranormal Romance",
        "Romance|science Fiction & Fantasy",
        "Sports Romance",
        "Romance fantasy",
    ],
    "Science Fiction": [
        "Science Fiction & Fantasy",
        "Science Fiction and Fantasy",
        "Fantasy & Science Fiction",
        "Fantasy and Science Fiction ",
        "Romance|science Fiction & Fantasy",
    ],
    Action: ["Action", "Action & Adventure"],
    Adventure: ["Adventure", "Action & Adventure"],
};

/**
 * Builds a case- and punctuation-independent lookup key.
 * @param {unknown} value Genre name or alias.
 * @returns {string} Normalized lookup key.
 */
function genreKey(value) {
    return String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "");
}

/**
 * Resolves a known alias or capitalizes an unfamiliar genre.
 * @param {unknown} value Genre name.
 * @returns {string} Display name, or an empty string.
 */
export function normalizeGenre(value) {
    const original = String(value ?? "").trim();
    if (!original) return "";
    const key = genreKey(original);
    if (GENRE_ALIASES.has(key)) return GENRE_ALIASES.get(key);
    return original
        .toLowerCase()
        .split(/\s+/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

/**
 * Normalizes and deduplicates a book's genre names.
 * @param {Array<string|{name?: string}>} genres Raw genre values.
 * @returns {string[]} Unique display names.
 */
export function normalizeGenres(genres) {
    if (!Array.isArray(genres)) return [];
    return [
        ...new Set(
            genres
                .map((genre) =>
                    normalizeGenre(
                        typeof genre === "string" ? genre : genre?.name,
                    ),
                )
                .filter(Boolean),
        ),
    ];
}

/**
 * Separates standard choices from additional genres in the current results.
 * @param {Object[]} books Current book results.
 * @returns {{standard: string[], additional: string[]}} Sorted select groups.
 */
export function getGenreOptions(books) {
    const standard = [...STANDARD_GENRES].sort();
    const standardKeys = new Set(standard.map(genreKey));
    const additional = [
        ...new Set(
            (Array.isArray(books) ? books : []).flatMap((book) =>
                normalizeGenres(book?.genres),
            ),
        ),
    ]
        .filter((genre) => !standardKeys.has(genreKey(genre)))
        .sort();
    return {standard, additional};
}

/**
 * Filters Catalogue results without mutating the source array.
 * @param {Object[]} books Current results.
 * @param {string} selectedGenre Selected display name, or empty for all.
 * @returns {Object[]} Matching books.
 */
export function filterBooksByGenre(books, selectedGenre) {
    if (!selectedGenre || selectedGenre === "All genres")
        return Array.isArray(books) ? [...books] : [];
    const normalizedSelection = normalizeGenre(selectedGenre);
    return (Array.isArray(books) ? books : []).filter((book) =>
        normalizeGenres(book?.genres).includes(normalizedSelection),
    );
}

/**
 * Matches explicit filter categories to available database genre IDs.
 * Keep category membership aligned with BOOK_GENRE_CATEGORIES in books.php.
 * @param {Object[]} availableGenres Local genres with IDs and names.
 * @returns {Array<{name: string, ids: number[]}>} Categories with at least one match.
 */
export function getGenreFilterCategories(availableGenres) {
    const genres = Array.isArray(availableGenres) ? availableGenres : [];
    return Object.entries(GENRE_CATEGORIES)
        .map(([name, members]) => ({
            name,
            ids: genres
                .filter((item) =>
                    members.some(
                        (member) =>
                            normalizeGenre(member) ===
                            normalizeGenre(item?.name),
                    ),
                )
                .map((item) => Number(item.id)),
        }))
        .filter((category) => category.ids.length);
}

export {GENRE_CATEGORIES, STANDARD_GENRES};
