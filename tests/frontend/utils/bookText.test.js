import { describe, expect, it } from "vitest";
import { bookText } from "@/utils/bookText";

describe("bookText", () => {
    it("keeps headline and description separate", () =>
        expect(bookText({ headline: "Intro", description: "Body" })).toEqual({
            headline: "Intro",
            description: "Body",
        }));
    it("removes a duplicated headline prefix", () =>
        expect(
            bookText({ headline: "Intro", description: "Intro\nBody" }),
        ).toEqual({ headline: "Intro", description: "Body" }));
    it("supports missing fields", () => {
        expect(bookText({ headline: "Only" })).toEqual({
            headline: "Only",
            description: "",
        });
        expect(bookText({ description: "Only" })).toEqual({
            headline: "",
            description: "Only",
        });
        expect(bookText({})).toEqual({ headline: "", description: "" });
    });
});
