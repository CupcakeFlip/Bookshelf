/** Formats author values for display without changing the source data. */
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
const GENRE_CATEGORIES = {
  Paranormal: ["Paranormal", "Paranormal Romance", "Paranormal Fantasy"],
  Dark: ["Dark", "Dark Romance", "Dark Fantasy"],
  Urban: ["Urban", "Urban Fantasy"],
  Historical: ["Historical", "Historical Romance", "Historical Fiction"],
  Fantasy: [
    "Fantasy",
    "Fantasy Romance",
    "Romantasy",
    "Epic Fantasy",
    "Urban Fantasy",
    "Dark Fantasy",
    "High Fantasy",
    "Paranormal Fantasy",
    "Science Fiction & Fantasy",
    "Romance|science Fiction & Fantasy",
  ],
  Romance: [
    "Romance",
    "Fantasy Romance",
    "Romantasy",
    "Dark Romance",
    "Contemporary Romance",
    "Paranormal Romance",
    "Romance|science Fiction & Fantasy",
    "Sports Romance",
  ],
  "Science Fiction": [
    "Science Fiction & Fantasy",
    "Romance|science Fiction & Fantasy",
  ],
};

function genreKey(value) {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");
}

/** Normalizes a genre alias or applies readable capitalization to an unknown genre. */
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

/** Returns normalized genre names without mutating the supplied book data. */
export function normalizeGenres(genres) {
  if (!Array.isArray(genres)) return [];
  return [
    ...new Set(
      genres
        .map((genre) =>
          normalizeGenre(typeof genre === "string" ? genre : genre?.name),
        )
        .filter(Boolean),
    ),
  ];
}

/** Builds the standard and book-specific genre groups for a select element. */
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
  return { standard, additional };
}

/** Filters books by normalized genre while preserving the original array and objects. */
export function filterBooksByGenre(books, selectedGenre) {
  if (!selectedGenre || selectedGenre === "All genres")
    return Array.isArray(books) ? [...books] : [];
  const normalizedSelection = normalizeGenre(selectedGenre);
  return (Array.isArray(books) ? books : []).filter((book) =>
    normalizeGenres(book?.genres).includes(normalizedSelection),
  );
}

export function expandGenreIds(selectedIds, availableGenres) {
  const selected = new Set(
    (Array.isArray(selectedIds) ? selectedIds : []).map(Number),
  );
  const available = Array.isArray(availableGenres) ? availableGenres : [];
  for (const id of [...selected]) {
    const genre = available.find((item) => Number(item?.id) === id);
    const category = Object.entries(GENRE_CATEGORIES).find(
      ([name]) => normalizeGenre(name) === normalizeGenre(genre?.name),
    );
    if (!category) continue;
    const names = new Set(category[1].map(normalizeGenre));
    for (const item of available)
      if (names.has(normalizeGenre(item?.name))) selected.add(Number(item.id));
  }
  return [...selected];
}

export function getGenreFilterCategories(availableGenres) {
  const genres = Array.isArray(availableGenres) ? availableGenres : [];
  return Object.entries(GENRE_CATEGORIES)
    .map(([name, members]) => ({
      name,
      ids: genres
        .filter((item) =>
          members.some(
            (member) => normalizeGenre(member) === normalizeGenre(item?.name),
          ),
        )
        .map((item) => Number(item.id)),
    }))
    .filter((category) => category.ids.length);
}

export { GENRE_CATEGORIES, STANDARD_GENRES };
