/* Made with AI */
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import BookDetailsSkeleton from "@/components/BookDetailsSkeleton.vue";
import detailsSource from "@/components/BookDetailsSkeleton.vue?raw";
import BookGridSkeleton from "@/components/BookGridSkeleton.vue";
import gridSource from "@/components/BookGridSkeleton.vue?raw";
import ButtonSpinner from "@/components/ButtonSpinner.vue";
import ListRowsSkeleton from "@/components/ListRowsSkeleton.vue";
import rowsSource from "@/components/ListRowsSkeleton.vue?raw";
import LoadingIndicator from "@/components/LoadingIndicator.vue";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
import {describe, expect, it} from "vitest";
import {createSSRApp, h} from "vue";
import {renderToString} from "vue/server-renderer";

const render = (component, props = {}) => {
    const app = createSSRApp({render: () => h(component, props)});
    app.component("RouterLink", {render: () => h("a")});
    return renderToString(app);
};

describe("loading components", () => {
    it("renders card skeletons in the BookGrid columns without announcing each card", async () => {
        const html = await render(BookGridSkeleton, {count: 12});
        expect(html.match(/col-6 col-md-4 col-lg-3 col-xl-3/g)).toHaveLength(12);
        expect(html).toContain('aria-hidden="true"');
        expect(html).toContain("book-skeleton__cover");
    });

    it("replaces initial skeletons with books and keeps books during refresh or errors", async () => {
        const book = {id: 1, hardcover_id: "1", title: "Visible book", authors: [], status: null};
        const initial = await render(BookCollectionContent, {books: [], loading: true, label: "Library books"});
        const refreshing = await render(BookCollectionContent, {books: [book], loading: true, label: "Library books"});
        const failed = await render(BookCollectionContent,
            {books: [book], error: "Request failed", label: "Library books"});
        const loaded = await render(BookCollectionContent, {books: [book], label: "Library books"});
        expect(initial).toContain("book-skeleton__cover");
        expect(initial).toContain('role="status"');
        expect(refreshing).toContain("Visible book");
        expect(refreshing).toContain("Updating library books…");
        expect(refreshing).not.toContain("book-skeleton__cover");
        expect(failed).toContain("Visible book");
        expect(failed).toContain("Request failed");
        expect(loaded).not.toContain("spinner-border");
    });

    it("renders details and list placeholders", async () => {
        expect(await render(BookDetailsSkeleton)).toContain("details-skeleton__cover");
        expect((await render(ListRowsSkeleton)).match(/list-skeleton__title/g)).toHaveLength(5);
    });

    it("announces one meaningful loading status and hides the spinner itself", async () => {
        const html = await render(LoadingIndicator, {label: "Updating library…"});
        expect(html).toContain('role="status"');
        expect(html).toContain('aria-live="polite"');
        expect(html).toContain("Updating library…");
        expect(html).toContain('aria-hidden="true"');
        expect(await render(ButtonSpinner)).toContain("spinner-border-sm");
    });

    it("disables the session save action and shows a spinner while saving", async () => {
        const saving = await render(ReadingSessionForm, {saving: true});
        const idle = await render(ReadingSessionForm);
        expect(saving).toMatch(/aria-busy="true"[^>]*>.*spinner-border/s);
        expect(saving).toContain("Saving…");
        expect(idle).not.toContain("Saving…");
        expect(idle).not.toContain("spinner-border");
    });

    it("turns off skeleton animation for reduced motion", () => {
        for (const source of [gridSource, detailsSource, rowsSource]) {
            expect(source).toContain("prefers-reduced-motion: reduce");
            expect(source).toMatch(/animation:\s*none/);
        }
    });
});
