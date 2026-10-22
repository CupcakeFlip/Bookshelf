import { nextTick } from "vue";

/** Scroll only after a successful page commit, Vue's patch, and browser layout. */
export async function scrollToCollection(element, isCurrent) {
    await nextTick();
    await new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
    if (!isCurrent() || !element?.isConnected) return;
    // Avoid a smooth animation racing page replacement or Safari momentum scrolling.
    element.scrollIntoView({ behavior: "instant", block: "start" });
}
