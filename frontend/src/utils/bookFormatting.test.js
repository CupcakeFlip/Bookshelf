import { describe, expect, it } from "vitest";
import {
    expandGenreIds,
    filterBooksByGenre,
    formatAuthors,
    getGenreOptions,
    normalizeGenre,
    normalizeGenres,
} from "./bookFormatting";

describe("formatAuthors", () => {
    it.each([
        [[{ name: "Jane Smith" }], "Jane Smith"],
        [[{ name: "Jane Smith" }, "John Doe"], "Jane Smith, John Doe"],
        [
            [
                { name: "Jane Smith" },
                { name: "John Doe" },
                { name: "A. Writer" },
            ],
            "Jane Smith, John Doe et al.",
        ],
        [["Jane Smith", null, { name: "John Doe" }], "Jane Smith, John Doe"],
        [[], ""],
        [null, ""],
        [{ name: "Jane Smith" }, ""],
    ])("formats %o", (authors, expected) =>
        expect(formatAuthors(authors)).toBe(expected),
    );
});

describe("normalizeGenre", () => {
    it.each([
        ["sci-fi", "Science Fiction"],
        ["scifi", "Science Fiction"],
        ["sciencefiction", "Science Fiction"],
        [" science fiction ", "Science Fiction"],
        ["nonfiction", "Non-Fiction"],
        ["non fiction", "Non-Fiction"],
        ["non-fiction", "Non-Fiction"],
        ["YA", "Young Adult"],
        ["young adult", "Young Adult"],
        ["romcom", "Romantic Comedy"],
        ["rom-com", "Romantic Comedy"],
        ["romantic comedy", "Romantic Comedy"],
        ["children's audiobooks", "Children"],
        ["DARK ROMANCE", "Dark Romance"],
        ["Urban Fantasy", "Urban Fantasy"],
        ["  cozy mystery  ", "Cozy Mystery"],
    ])("normalizes %s", (value, expected) =>
        expect(normalizeGenre(value)).toBe(expected),
    );

    it("does not mutate original genre data", () => {
        const source = [{ name: "sci-fi", slug: "sci-fi" }];
        expect(normalizeGenres(source)).toEqual(["Science Fiction"]);
        expect(source).toEqual([{ name: "sci-fi", slug: "sci-fi" }]);
    });

    it("normalizes multi-word aliases consistently", () => {
        expect(normalizeGenres(["children's audiobooks", "Children"])).toEqual([
            "Children",
        ]);
    });
});

describe("genre options", () => {
    it("groups sorted standard and additional genres without duplicates", () => {
        const options = getGenreOptions([
            {
                genres: [
                    { name: "sci-fi" },
                    { name: "Custom Genre" },
                    { name: "custom genre" },
                    { name: "Fantasy" },
                ],
            },
        ]);
        expect(options.standard).toEqual([
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
        ]);
        expect(options.additional).toEqual(["Custom Genre"]);
    });

    it("handles books without genres and has no empty additional group", () => {
        expect(getGenreOptions([{}, { genres: [] }]).additional).toEqual([]);
    });
});

describe("filterBooksByGenre", () => {
    const books = [
        { title: "A", genres: [{ name: "sci-fi" }] },
        { title: "B", genres: [{ name: "Fantasy" }] },
        { title: "C" },
    ];

    it("matches aliases, all genres, and no matches safely", () => {
        expect(filterBooksByGenre(books, "Science Fiction")).toEqual([
            books[0],
        ]);
        expect(filterBooksByGenre(books, "All genres")).toEqual(books);
        expect(filterBooksByGenre(books, "Mystery")).toEqual([]);
        expect(filterBooksByGenre(books, "")).toEqual(books);
    });

    it("does not mutate the source array", () => {
        const result = filterBooksByGenre(books, "Fantasy");
        expect(result).not.toBe(books);
        expect(books).toHaveLength(3);
    });
});

describe("genre categories", () => {
    const genres = [
        { id: 1, name: "Fantasy" },
        { id: 2, name: "Fantasy Romance" },
        { id: 3, name: "Romantasy" },
        { id: 4, name: "Romance" },
        { id: 5, name: "sci-fi" },
    ];

    it("expands Fantasy and Romance independently with overlapping subgenres", () => {
        expect(expandGenreIds([1], genres)).toEqual([1, 2, 3]);
        expect(expandGenreIds([4], genres)).toEqual([4, 2, 3]);
        expect(expandGenreIds([1, 4], genres)).toEqual([1, 4, 2, 3]);
    });

    it("keeps a specific subgenre independent and normalizes aliases", () => {
        expect(expandGenreIds([2], genres)).toEqual([2]);
        expect(expandGenreIds([5], genres)).toEqual([5]);
    });
});
