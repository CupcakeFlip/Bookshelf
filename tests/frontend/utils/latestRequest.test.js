import { describe, expect, it } from "vitest";
import { createLatestRequest } from "@/utils/latestRequest";

describe("latest request guard", () => {
    it("aborts the previous request and rejects its late result", () => {
        const requests = createLatestRequest();
        const first = requests.start();
        const second = requests.start();
        expect(first.signal.aborted).toBe(true);
        expect(requests.isCurrent(first)).toBe(false);
        expect(requests.isCurrent(second)).toBe(true);
    });

    it("rejects completion after unmount cancellation", () => {
        const requests = createLatestRequest();
        const pending = requests.start();
        requests.abort();
        expect(pending.signal.aborted).toBe(true);
        expect(requests.isCurrent(pending)).toBe(false);
    });
});
