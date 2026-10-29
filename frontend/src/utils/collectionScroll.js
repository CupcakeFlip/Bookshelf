/**
 * Scrolls a successfully updated collection into view after layout has settled.
 * Lets paginated views share the timing checks and ignore outdated requests
 * or page elements that are no longer connected.
 */
import {nextTick} from "vue";

/**
 * Waits for Vue and browser layout before scrolling a committed page into view.
 * @param {HTMLElement|null} element Collection container.
 * @param {function(): boolean} isCurrent Whether this request still owns the view.
 * @returns {Promise<void>} Resolves without scrolling if the target is stale.
 */
export async function scrollToCollection(element, isCurrent) {
    await nextTick();
    await new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
    if (!isCurrent() || !element?.isConnected) {
        return;
    }
    // Avoid a smooth animation racing page replacement or Safari momentum scrolling.
    element.scrollIntoView({behavior: "instant", block: "start"});
}
