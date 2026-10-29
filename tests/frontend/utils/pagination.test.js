/* Made with AI */
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";
import {afterEach, describe, expect, it, vi} from "vitest";

afterEach(() => vi.unstubAllGlobals());

describe("page-size preference", () => {
    it("reads allowed values and rejects invalid stored values", () => {
        const getItem = vi.fn().mockReturnValue("48");
        vi.stubGlobal("localStorage", {getItem});
        expect(getSavedPageLimit()).toBe(48);
        getItem.mockReturnValue("13");
        expect(getSavedPageLimit()).toBe(24);
    });

    it("keeps navigation usable when storage access throws", () => {
        const blocked = () => {
            throw new Error("Storage blocked");
        };
        vi.stubGlobal("localStorage", {getItem: blocked, setItem: blocked});
        expect(getSavedPageLimit()).toBe(24);
        expect(() => savePageLimit(48)).not.toThrow();
    });

    it("writes only supported page sizes", () => {
        const setItem = vi.fn();
        vi.stubGlobal("localStorage", {setItem});
        savePageLimit(13);
        expect(setItem).not.toHaveBeenCalled();
        savePageLimit(48);
        expect(setItem).toHaveBeenCalledExactlyOnceWith("booksPerPage", "48");
    });
});
