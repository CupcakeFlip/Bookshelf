import { afterEach, describe, expect, it, vi } from "vitest";
import {
    createReadingSession,
    saveBookStatus,
    updateReadingSession,
} from "./books";

afterEach(() => vi.restoreAllMocks());

describe("reading session payloads", () => {
    it("does not send date form UI fields when dates are empty", async () => {
        const response = { ok: true, json: async () => ({ id: 1 }) };
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => response),
        );
        await createReadingSession(
            { hardcover_id: 42 },
            {
                status: "finished",
                formats: [],
                start_date: null,
                start_date_precision: "unknown",
                finish_date: null,
                finish_date_precision: "unknown",
            },
        );
        const body = JSON.parse(fetch.mock.calls[0][1].body);
        expect(body).not.toHaveProperty("startYear");
        expect(body).not.toHaveProperty("finishDay");
        expect(body.start_date).toBeNull();
    });

    it("includes confirmed session details in a status update", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => ({
                ok: true,
                json: async () => ({ status: "finished" }),
            })),
        );
        await saveBookStatus({ hardcover_id: 42 }, "finished", {
            finish_date: "2026-10-19",
            finish_date_precision: "day",
            formats: ["audiobook"],
        });
        const body = JSON.parse(fetch.mock.calls[0][1].body);
        expect(body.session.finish_date).toBe("2026-10-19");
        expect(body.session.formats).toEqual(["audiobook"]);
    });

    it("updates an existing session and can clear its format", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => ({
                ok: true,
                json: async () => ({ id: 9, formats: [] }),
            })),
        );
        await updateReadingSession(9, {
            status: "finished",
            formats: [],
            start_date: null,
            start_date_precision: "unknown",
            finish_date: null,
            finish_date_precision: "unknown",
        });
        expect(fetch.mock.calls[0][0]).toContain("id=9");
        expect(JSON.parse(fetch.mock.calls[0][1].body).formats).toEqual([]);
    });

    it("supports optional DNF progress and time in the status payload", async () => {
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => ({
                ok: true,
                json: async () => ({ status: "dnf" }),
            })),
        );
        await saveBookStatus({ hardcover_id: 42 }, "dnf", {
            finish_date: null,
            finish_date_precision: "unknown",
            formats: [],
            dnf_progress_type: "time",
            dnf_progress_value: 95,
        });
        const body = JSON.parse(fetch.mock.calls[0][1].body);
        expect(body.session.dnf_progress_type).toBe("time");
        expect(body.session.dnf_progress_value).toBe(95);
    });
});
