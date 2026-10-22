import { describe, expect, it } from "vitest";
import { createSSRApp } from "vue";
import { renderToString } from "vue/server-renderer";
import Pagination from "./Pagination.vue";

const render = (props) => renderToString(createSSRApp(Pagination, props));

describe("Pagination", () => {
    it("renders a bounded page window and accessible current page", async () => {
        const html = await render({ page: 50, totalPages: 100, limit: 24, showPageSize: true });
        expect(html).toContain('aria-label="Book pages"');
        expect(html).toContain('aria-current="page"');
        expect(html).toContain('aria-label="Page 50"');
        expect(html).not.toContain('aria-label="Page 1"');
        for (const size of [12, 24, 48, 96]) expect(html).toContain(`value="${size}"`);
    });

    it("disables invalid navigation with a known total", async () => {
        const first = await render({ page: 1, totalPages: 2 });
        const last = await render({ page: 2, totalPages: 2 });
        expect(first).toMatch(/<button[^>]*disabled[^>]*>\s*Previous\s*</);
        expect(last).toMatch(/<button[^>]*disabled[^>]*>\s*Next\s*</);
    });

    it("uses only Previous and Next for unknown totals", async () => {
        const html = await render({ page: 3, hasNext: true });
        expect(html).toContain("Page 3");
        expect(html).not.toContain('aria-label="Page 3"');
        expect(html).not.toMatch(/<button[^>]*disabled[^>]*>\s*Next\s*</);
    });

    it("disables navigation and page size during a page request", async () => {
        const html = await render({ page: 2, totalPages: 4, showPageSize: true, disabled: true });
        expect(html).toMatch(/<select[^>]*disabled/);
        expect(html.match(/<button[^>]*disabled/g)).toHaveLength(6);
    });
});
