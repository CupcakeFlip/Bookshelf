/* Made with AI */
import {optimizedCoverSrcset, optimizedCoverUrl} from "@/utils/coverImages";
import {describe, expect, it} from "vitest";

describe("optimized cover URLs", () => {
    const original = "https://assets.hardcover.app/covers/book.jpg?token=a&size=large";

    it("provides the three responsive variants", () => {
        const srcset = optimizedCoverSrcset(original);
        expect(srcset.split(", ")).toHaveLength(3);
        for (const width of [320, 640, 960]) {
            expect(srcset).toContain(`${optimizedCoverUrl(original, width)} ${width}w`);
            const url = new URL(optimizedCoverUrl(original, width), "https://bookshelf.test");
            expect(url.searchParams.get("url")).toBe(original);
            expect(url.searchParams.get("w")).toBe(String(width));
        }
    });

    it("leaves unsupported and absent sources alone", () => {
        expect(optimizedCoverUrl("")).toBe("");
        expect(optimizedCoverSrcset("")).toBe("");
        expect(optimizedCoverUrl("https://example.com/a.jpg")).toBe("https://example.com/a.jpg");
        expect(optimizedCoverSrcset("https://example.com/a.jpg")).toBe("");
        expect(optimizedCoverUrl("https://assets.hardcover.app:444/a.jpg"))
            .toBe("https://assets.hardcover.app:444/a.jpg");
    });
});
