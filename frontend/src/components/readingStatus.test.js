// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, h, nextTick, reactive } from "vue";
import BookStatusDropdown from "./BookStatusDropdown.vue";
import ReadingSessionForm from "./ReadingSessionForm.vue";
import BookCard from "./BookCard.vue";
import dropdownSource from "./BookStatusDropdown.vue?raw";
import formSource from "./ReadingSessionForm.vue?raw";
import dialogSource from "./ReadingStatusDialog.vue?raw";
import { getReadingSessions, removeBookStatus, saveBookStatus } from "@/api/books";

vi.mock("@/api/books", () => ({
    getReadingSessions: vi.fn(),
    removeBookStatus: vi.fn(),
    saveBookStatus: vi.fn(),
}));

const apps = [];
const book = { id: 1, hardcover_id: "123", title: "Test book", status: "reading" };
async function flush() {
    await Promise.resolve();
    await nextTick();
    await Promise.resolve();
    await nextTick();
}
async function mount(component, props = {}) {
    const host = document.createElement("div");
    document.body.append(host);
    const state = reactive(props);
    const app = createApp({ render: () => h(component, state) });
    app.component("RouterLink", { render: () => h("a") });
    app.mount(host);
    apps.push(app);
    await flush();
    return { host, state };
}
function button(label, root = document) {
    const result = [...root.querySelectorAll("button")].find((el) => el.textContent.trim() === label);
    expect(result, `Button: ${label}`).toBeTruthy();
    return result;
}
async function click(element) {
    element.click();
    await flush();
}
async function openStatus(label = "Finished", component = BookStatusDropdown) {
    const mounted = await mount(component, { book: { ...book } });
    const trigger = mounted.host.querySelector(".btn-status");
    trigger.focus();
    await click(trigger);
    await click(button(label, mounted.host.querySelector(".dropdown-menu")));
    return { ...mounted, trigger };
}
function key(key, shiftKey = false) {
    document.activeElement.dispatchEvent(new KeyboardEvent("keydown", {
        key, shiftKey, bubbles: true, cancelable: true,
    }));
}
async function setField(label, value) {
    const field = document.querySelector(`[aria-label="${label}"]`);
    field.value = value;
    field.dispatchEvent(new Event(field.tagName === "SELECT" ? "change" : "input", { bubbles: true }));
    await flush();
}
async function submit() {
    document.querySelector("form").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await flush();
}

beforeEach(() => {
    vi.resetAllMocks();
    getReadingSessions.mockResolvedValue([]);
    saveBookStatus.mockResolvedValue({ id: 1 });
    removeBookStatus.mockResolvedValue({});
});
afterEach(async () => {
    for (const app of apps.splice(0)) app.unmount();
    await flush();
    document.body.replaceChildren();
    document.body.style.overflow = "";
});

describe("shared reading status controls", () => {
    it.each(["want_to_read", "reading", "finished", "paused", "dnf"])(
        "toggles from the entire %s label or the accessible arrow without saving", async (status) => {
            const { host } = await mount(BookStatusDropdown, { book: { ...book, status } });
            const main = host.querySelector(".btn-status");
            const arrow = host.querySelector(".dropdown-toggle");
            await click(main);
            expect(host.querySelector(".dropdown-menu")).toBeTruthy();
            expect(main.getAttribute("aria-expanded")).toBe("true");
            expect(arrow.getAttribute("aria-expanded")).toBe("true");
            await click(main);
            expect(host.querySelector(".dropdown-menu")).toBeNull();
            await click(arrow);
            expect(host.querySelector(".dropdown-menu")).toBeTruthy();
            expect(arrow.textContent).toContain("Toggle reading status");
            expect(saveBookStatus).not.toHaveBeenCalled();
        },
    );

    it("preserves quick add and the separate dropdown for Not in library", async () => {
        const onStatusChange = vi.fn();
        const { host } = await mount(BookStatusDropdown, { book: { ...book, status: null }, onStatusChange });
        await click(host.querySelector(".dropdown-toggle"));
        expect(host.querySelector(".dropdown-menu")).toBeTruthy();
        expect(saveBookStatus).not.toHaveBeenCalled();
        await click(host.querySelector(".btn-status"));
        expect(saveBookStatus).toHaveBeenCalledWith(expect.objectContaining({ status: null }), "want_to_read", {});
        expect(onStatusChange).toHaveBeenCalledWith(expect.objectContaining({ status: "want_to_read", previous: null }));
    });

    it("keeps 44px touch targets on both pill buttons, menu options, and mobile form controls", async () => {
        const { host } = await mount(BookStatusDropdown, { book });
        expect(host.querySelectorAll(".btn-status")).toHaveLength(2);
        expect(dropdownSource).toMatch(/\.btn-status,\s*\.dropdown-item\s*\{[^}]*min-width: 44px;[^}]*min-height: 44px;/);
        expect(formSource).toContain("(max-width: 767.98px), (pointer: coarse)");
        expect(formSource).toMatch(/\.reading-session-form \.btn,[\s\S]*\.form-select\s*\{[^}]*min-width: 44px;[^}]*min-height: 44px;/);
        expect(dialogSource).toMatch(/\.btn-close\s*\{[^}]*min-width: 44px;[^}]*min-height: 44px;/);
    });

    it.each(["Finished", "Did not finish"])("teleports the %s modal outside BookCard with dialog semantics", async (label) => {
        const { host } = await openStatus(label, BookCard);
        const modal = document.querySelector('[role="dialog"]');
        expect(modal.parentElement).toBe(document.body);
        expect(host.querySelector(".modal")).toBeNull();
        expect(document.querySelector(".modal-backdrop").parentElement).toBe(document.body);
        expect(modal.getAttribute("aria-modal")).toBe("true");
        expect(document.getElementById(modal.getAttribute("aria-labelledby")).textContent).toContain(label === "Finished" ? "Finish reading" : label);
        expect(modal.querySelector(".modal-dialog-scrollable")).toBeTruthy();
        expect(document.body.style.overflow).toBe("hidden");
        expect(document.activeElement).toBe(modal.querySelector(".btn-close"));
        expect(saveBookStatus).not.toHaveBeenCalled();
    });

    it.each(["Finished", "Did not finish"])("cancels %s without writes by backdrop, dialog margin, close, Cancel or Escape", async (label) => {
        const onStatusChange = vi.fn();
        const { host } = await mount(BookStatusDropdown, { book: { ...book }, onStatusChange });
        const trigger = host.querySelector(".btn-status");
        for (const dismiss of [".modal", ".modal-backdrop", ".modal-dialog", ".btn-close", "Cancel", "Escape"]) {
            trigger.focus();
            await click(trigger);
            await click(button(label, host.querySelector(".dropdown-menu")));
            // Interacting inside the content must leave the pending change open.
            await click(document.querySelector(".modal-title"));
            expect(document.querySelector(".modal")).toBeTruthy();
            if (dismiss === "Escape") {
                key("Escape");
                await flush();
            } else {
                await click(dismiss === "Cancel" ? button("Cancel") : document.querySelector(dismiss));
            }
            expect(document.querySelector(".modal")).toBeNull();
            expect(document.querySelector(".modal-backdrop")).toBeNull();
            expect(document.body.style.overflow).toBe("");
            expect(document.activeElement).toBe(trigger);
            expect(trigger.disabled).toBe(false);
            expect(trigger.textContent.trim()).toBe("Reading");
        }
        expect(saveBookStatus).not.toHaveBeenCalled();
        expect(removeBookStatus).not.toHaveBeenCalled();
        expect(onStatusChange).not.toHaveBeenCalled();
    });

    it("contains keyboard focus, cycles Tab, and restores existing body overflow on unmount", async () => {
        document.body.style.overflow = "scroll";
        const { host } = await openStatus();
        const close = document.querySelector(".btn-close");
        key("Tab", true);
        expect(document.activeElement).toBe(button("Save"));
        key("Tab");
        expect(document.activeElement).toBe(close);
        host.querySelector(".btn-status").focus();
        expect(document.activeElement.closest(".modal")).toBeTruthy();
        apps.pop().unmount();
        await flush();
        expect(document.querySelector(".modal")).toBeNull();
        expect(document.body.style.overflow).toBe("scroll");
        expect(saveBookStatus).not.toHaveBeenCalled();
    });

    it("cancels during loading and ignores a late session response", async () => {
        let resolve;
        getReadingSessions.mockReturnValue(new Promise((done) => { resolve = done; }));
        await openStatus();
        expect(document.querySelector('[role="status"]')).toBeTruthy();
        await click(document.querySelector(".modal"));
        resolve([{ status: "reading", formats: ["ebook"] }]);
        await flush();
        expect(document.querySelector(".modal")).toBeNull();
        expect(saveBookStatus).not.toHaveBeenCalled();
    });

    it("preserves loading errors and retries before confirming an active session", async () => {
        getReadingSessions.mockRejectedValueOnce(new Error("Session load failed"))
            .mockResolvedValueOnce([{ status: "reading", start_date: "2020-01-01", start_date_precision: "year", formats: ["ebook"] }]);
        const { host } = await openStatus();
        expect(document.querySelector('[role="alert"]').textContent).toContain("Session load failed");
        await click(button("Retry"));
        let finish;
        saveBookStatus.mockReturnValue(new Promise((resolve) => { finish = resolve; }));
        await submit();
        expect(saveBookStatus).toHaveBeenCalledWith(book, "finished", expect.objectContaining({
            start_date: "2020-01-01", start_date_precision: "year", formats: ["ebook"],
        }));
        expect(host.querySelector(".btn-status").disabled).toBe(true);
        finish({ id: 1 });
        await flush();
        expect(host.querySelector(".btn-status").disabled).toBe(false);
    });

    it.each(["Finished", "Did not finish"])("confirms %s with an entirely unknown finish date and the existing API payload", async (label) => {
        const { host, state } = await openStatus(label);
        const onStatusChange = vi.fn();
        state.onStatusChange = onStatusChange;
        await click(button("I don't know the finish date"));
        for (const part of ["year", "month", "day"]) {
            expect(document.querySelector(`[aria-label="Finished ${part}"]`).value).toBe("");
        }
        expect(saveBookStatus).not.toHaveBeenCalled();
        await submit();
        const status = label === "Finished" ? "finished" : "dnf";
        expect(saveBookStatus).toHaveBeenCalledExactlyOnceWith(book, status, {
            status, formats: [], start_date: null, start_date_precision: "unknown",
            finish_date: null, finish_date_precision: "unknown",
            dnf_progress_type: null, dnf_progress_value: null,
        });
        expect(onStatusChange).toHaveBeenCalledWith(expect.objectContaining({ status, previous: "reading" }));
        expect(document.querySelector(".modal")).toBeNull();
        expect(host.querySelector(".btn-status").disabled).toBe(false);
        expect(document.activeElement).toBe(host.querySelector(".btn-status"));
    });

    it("shows save failures without changing status and allows retry", async () => {
        saveBookStatus.mockRejectedValueOnce(new Error("Could not save"));
        const { host } = await openStatus();
        await submit();
        expect(host.querySelector('[role="alert"]').textContent).toBe("Could not save");
        expect(host.querySelector(".btn-status").textContent.trim()).toBe("Reading");
        expect(host.querySelector(".btn-status").disabled).toBe(false);
        await click(host.querySelector(".btn-status"));
        expect(host.querySelector(".dropdown-menu")).toBeTruthy();
    });
});

describe("finish date form", () => {
    it.each([
        ["2021", "", "", "2021-01-01", "year"],
        ["2021", "2", "", "2021-02-01", "month"],
        ["2021", "2", "7", "2021-02-07", "day"],
    ])("allows a known date after clearing it, preserving %s/%s/%s precision", async (year, month, day, date, precision) => {
        const onSave = vi.fn();
        await mount(ReadingSessionForm, { modelValue: { status: "finished", finish_date: "2025-10-21", finish_date_precision: "day" }, onSave });
        await click(button("I don't know the finish date"));
        await setField("Finished year", year);
        if (month) await setField("Finished month", month);
        if (day) await setField("Finished day", day);
        await submit();
        expect(onSave).toHaveBeenCalledWith(expect.objectContaining({ finish_date: date, finish_date_precision: precision }));
    });

    it("keeps DNF progress, optional formats and partial start dates when clearing the finish date", async () => {
        const onSave = vi.fn();
        await mount(ReadingSessionForm, { modelValue: {
            status: "dnf", formats: ["audiobook"], start_date: "2025-03-01", start_date_precision: "month",
            finish_date: "2025-04-12", finish_date_precision: "day", dnf_progress_type: "time", dnf_progress_value: 95,
        }, onSave });
        await click(button("I don't know the finish date"));
        await submit();
        expect(onSave).toHaveBeenCalledWith({
            status: "dnf", formats: ["audiobook"], start_date: "2025-03-01", start_date_precision: "month",
            finish_date: null, finish_date_precision: "unknown", dnf_progress_type: "time", dnf_progress_value: 95,
        });
    });
});
