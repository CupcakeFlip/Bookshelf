/* Made with AI */
import {scrollToCollection} from "@/utils/collectionScroll";
import Frontpage from "@/views/Frontpage.vue";
import LibraryView from "@/views/LibraryView.vue";
import ListDetailView from "@/views/ListDetailView.vue";
// @vitest-environment jsdom
import {afterEach, beforeEach, describe, expect, it, vi} from "vitest";
import {createApp, h, nextTick} from "vue";

vi.mock("vue-router", () => ({useRoute: () => ({params: {status: "reading"}})}));
vi.mock("@/components/Navbar.vue", () => ({default: {render: () => null}}));
vi.mock("@/components/BookCollectionFilters.vue", () => ({
    default: {
        emits: ["change"],
        render() {
            return h("button",
                {class: "test-filter", onClick: () => this.$emit("change", {q: "new", sort: "recently_updated_desc"})},
                "Filter");
        },
    }
}));
vi.mock("@/components/BookGrid.vue", () => ({
    default: {
        props: ["books"], render() {
            return h("div", {class: "test-books"}, this.books.map((book) => book.title).join(","));
        },
    }
}));

let app;
let frames;
let scroll;

async function flush() {
    for (let i = 0; i < 8; i++) {
        await Promise.resolve();
        await nextTick();
    }
}

async function paint() {
    for (let i = 0; i < 2; i++) {
        const callbacks = frames.splice(0);
        for (const callback of callbacks) {
            callback();
        }
        await flush();
    }
}

function response(page = 1, ok = true) {
    return {
        ok,
        json: async () => ({
            books: [{hardcover_id: page, title: `Page ${page} book`}],
            page,
            totalPages: 3,
            hasNext: true
        })
    };
}

async function mount(component) {
    const host = document.createElement("div");
    document.body.append(host);
    app = createApp(component);
    app.mount(host);
    await flush();
    return host;
}

function button(text, host) {
    return [...host.querySelectorAll("button")].find((button) => button.textContent.trim() === text);
}

function deferred() {
    let resolve;
    const promise = new Promise((done) => {
        resolve = done;
    });
    return {promise, resolve};
}

beforeEach(() => {
    frames = [];
    scroll = vi.fn();
    vi.stubGlobal("requestAnimationFrame", (callback) => {
        frames.push(callback);
        return frames.length;
    });
    vi.stubGlobal("fetch", vi.fn(async () => response()));
    Element.prototype.scrollIntoView = scroll;
    localStorage.clear();
});
afterEach(async () => {
    app?.unmount();
    app = null;
    await paint();
    document.body.replaceChildren();
    delete Element.prototype.scrollIntoView;
    vi.unstubAllGlobals();
});

describe.each([
    ["Library", LibraryView], ["reading list", ListDetailView], ["Catalogue", Frontpage],
])("%s pagination", (_name, component) => {
    it("waits for success, DOM replacement and layout, then scrolls to the collection without animation", async () => {
        const host = await mount(component);
        await paint();
        expect(scroll).not.toHaveBeenCalled();
        const pending = deferred();
        fetch.mockReturnValueOnce(pending.promise);
        button("Next", host).click();
        await flush();
        expect(host.querySelector(".test-books").textContent).toContain("Page 1 book");
        expect(scroll).not.toHaveBeenCalled();
        pending.resolve(response(2));
        await flush();
        expect(host.querySelector(".test-books").textContent).toContain("Page 2 book");
        expect(scroll).not.toHaveBeenCalled();
        await paint();
        expect(scroll).toHaveBeenCalledExactlyOnceWith({behavior: "instant", block: "start"});
        expect(scroll.mock.contexts[0]).toBe(host.querySelector(".book-collection"));
    });

    it("does not scroll on failures or unrelated refreshes", async () => {
        const host = await mount(component);
        fetch.mockResolvedValueOnce(response(2, false));
        button("Next", host).click();
        await flush();
        await paint();
        expect(host.querySelector(".test-books").textContent).toContain("Page 1 book");
        expect(scroll).not.toHaveBeenCalled();
        window.dispatchEvent(new Event("bookshelf:books-synced"));
        await flush();
        await paint();
        expect(scroll).not.toHaveBeenCalled();
    });

    it("does not scroll for an aborted/stale page response", async () => {
        const host = await mount(component);
        const old = deferred();
        fetch.mockReturnValueOnce(old.promise);
        button("Next", host).click();
        await flush();
        window.dispatchEvent(new Event("bookshelf:books-synced"));
        await flush();
        old.resolve(response(2));
        await flush();
        await paint();
        expect(host.querySelector(".test-books").textContent).toContain("Page 1 book");
        expect(scroll).not.toHaveBeenCalled();
    });

    it("cancels a scheduled scroll when a newer request starts or the view unmounts", async () => {
        const host = await mount(component);
        fetch.mockResolvedValueOnce(response(2));
        button("Next", host).click();
        await flush();
        expect(frames.length).toBeGreaterThan(0);
        window.dispatchEvent(new Event("bookshelf:books-synced"));
        await flush();
        await paint();
        expect(scroll).not.toHaveBeenCalled();
        fetch.mockResolvedValueOnce(response(2));
        button("Next", host).click();
        await flush();
        app.unmount();
        app = null;
        await paint();
        expect(scroll).not.toHaveBeenCalled();
    });
});

describe("collection scroll boundaries", () => {
    it.each([LibraryView, ListDetailView])("does not scroll for filter changes but does for page-size changes",
        async (component) => {
            const host = await mount(component);
            host.querySelector(".test-filter").click();
            await flush();
            await paint();
            expect(scroll).not.toHaveBeenCalled();
            const size = host.querySelector("#books-page-size");
            size.value = "12";
            size.dispatchEvent(new Event("change", {bubbles: true}));
            await flush();
            await paint();
            expect(scroll).toHaveBeenCalledTimes(1);
            expect(fetch.mock.calls.at(-1)[0]).toContain("limit=12");
        });

    it("ignores a missing or disconnected target", async () => {
        const missing = scrollToCollection(null, () => true);
        const disconnected = scrollToCollection(document.createElement("div"), () => true);
        await flush();
        await paint();
        await Promise.all([missing, disconnected]);
        expect(scroll).not.toHaveBeenCalled();
    });
});
