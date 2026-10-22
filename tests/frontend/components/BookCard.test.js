import { describe, expect, it } from "vitest";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import BookCard from "@/components/BookCard.vue";
import source from "@/components/BookCard.vue?raw";

async function renderBook(book) {
    const app = createSSRApp({ render: () => h(BookCard, { book }) });
    app.component("RouterLink", {
        props: ["to"],
        render() { return h("a", { href: "/book" }, this.$slots.default?.()); },
    });
    app.component("BookStatusDropdown", { render: () => h("div") });
    return renderToString(app);
}

describe("BookCard description", () => {
    const base = { hardcover_id: 1, title: "Book", authors: ["Author"] };

    it("shows short text, headline, and the details link", async () => {
        const html = await renderBook({ ...base, headline: "Intro", description: "Intro\nShort body" });
        expect(html).toContain("book-headline");
        expect(html).toContain("Short body");
        expect(html).toContain("View Details");
        expect(html).not.toContain("Intro\nShort body");
    });

    it("keeps long text available for height based clipping", async () => {
        const description = "Long description. ".repeat(100);
        const html = await renderBook({ ...base, description });
        expect(html).toContain(description.trim());
        expect(html).toContain("book-description");
    });

    it("handles missing text and a headline on its own", async () => {
        const empty = await renderBook(base);
        expect(empty).not.toContain("book-description");
        const headline = await renderBook({ ...base, headline: "Only headline" });
        expect(headline).toContain("Only headline");
        expect(headline).not.toContain("book-description");
    });
});

describe("BookCard sizing", () => {
    it("constrains intrinsic grid widths while preserving the flip and reserved 2:3 cover space", () => {
        expect(source).toMatch(/\.book-card__inner\s*\{[^}]*grid-template-columns: minmax\(0, 1fr\)/);
        expect(source).toMatch(/\.book-card__front,\s*\.book-card__back\s*\{[^}]*min-width: 0/);
        expect(source).toMatch(/\.book-card__cover-wrapper\s*\{[^}]*width: 100%;[^}]*aspect-ratio: 2 \/ 3/);
        expect(source).toMatch(/\.book-cover\s*\{[^}]*position: absolute;[^}]*max-width: 100%/);
        expect(source).toContain("transform-style: preserve-3d");
        expect(source).toContain("transform: rotateY(180deg)");
    });
});
