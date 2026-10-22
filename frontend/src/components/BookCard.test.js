import { describe, expect, it } from "vitest";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import BookCard from "./BookCard.vue";

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
