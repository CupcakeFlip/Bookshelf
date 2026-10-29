/* Made with AI */
import BookCard from "@/components/BookCard.vue";
import {fallbackCoverImage, optimizedCoverSrcset, optimizedCoverUrl} from "@/utils/coverImages";
// @vitest-environment jsdom
import {afterEach, describe, expect, it, vi} from "vitest";
import {createApp, h, nextTick, reactive} from "vue";

const original = "https://assets.hardcover.app/covers/book.jpg?token=a&size=large";
let app;
afterEach(() => {
    app?.unmount();
    app = null;
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

describe("cover fallback", () => {
    it.each([false, true])("removes responsive candidates before retrying the original (srcset: %s), once only",
        (responsive) => {
            const image = document.createElement("img");
            image.src = optimizedCoverUrl(original);
            if (responsive) {
                image.srcset = optimizedCoverSrcset(original);
                image.sizes = "163px";
            }
            const set = vi.spyOn(image, "setAttribute");
            const remove = vi.spyOn(image, "removeAttribute");
            fallbackCoverImage({currentTarget: image}, original);
            expect(image.getAttribute("src")).toBe(original);
            expect(image.hasAttribute("srcset")).toBe(false);
            expect(image.hasAttribute("sizes")).toBe(false);
            expect(remove.mock.invocationCallOrder[0]).toBeLessThan(set.mock.invocationCallOrder[0]);
            fallbackCoverImage({currentTarget: image}, original);
            expect(set).toHaveBeenCalledExactlyOnceWith("src", original);
        });

    it("leaves already-original, unsupported, and missing images alone", () => {
        const image = document.createElement("img");
        image.src = "https://example.com/cover.jpg";
        const set = vi.spyOn(image, "setAttribute");
        fallbackCoverImage({currentTarget: image}, image.src);
        fallbackCoverImage({currentTarget: image}, "");
        fallbackCoverImage({currentTarget: null}, original);
        expect(set).not.toHaveBeenCalled();
    });

    it("handles actual card error events, preserves fallback on updates, and resets for a different cover",
        async () => {
            const book = reactive({hardcover_id: 1, title: "Cover test", cover_url: original});
            const host = document.createElement("div");
            document.body.append(host);
            app = createApp({render: () => h(BookCard, {book})});
            app.component("RouterLink", {render: () => h("a")});
            app.mount(host);
            const image = host.querySelector("img");
            expect(image.getAttribute("srcset")).toBe(optimizedCoverSrcset(original));
            expect(image.getAttribute("loading")).toBe("lazy");
            expect(image.getAttribute("sizes")).toContain("calc(50vw - 20px)");
            expect(image.width / image.height).toBe(2 / 3);
            image.dispatchEvent(new Event("error"));
            expect(image.getAttribute("src")).toBe(original);
            const set = vi.spyOn(image, "setAttribute");
            image.dispatchEvent(new Event("error"));
            expect(set).not.toHaveBeenCalled();
            book.title = "Updated title";
            await nextTick();
            expect(image.hasAttribute("srcset")).toBe(false);
            expect(image.getAttribute("src")).toBe(original);
            book.cover_url = "https://assets.hardcover.app/other.jpg";
            await nextTick();
            expect(image.getAttribute("srcset")).toBe(optimizedCoverSrcset(book.cover_url));
            image.dispatchEvent(new Event("error"));
            expect(image.getAttribute("src")).toBe(book.cover_url);
            expect(image.hasAttribute("srcset")).toBe(false);
        });
});
