<script setup>
import {nextTick, ref} from "vue";
import {Toast} from "bootstrap";

const syncing = ref(false);
const toastElement = ref(null);
const toastMessage = ref("");
const toastVariant = ref("success");
/** Navigation entries rendered in the primary menu. */
const items = [
    {label: "Home", to: "/"},
    {label: "Lists", to: "/lists"},
    {label: "Stats", to: "/stats", soon: true},
    {label: "Library", to: "/library"}
];

/**
 * Prevents placeholder links from navigating before their feature exists.
 *
 * @param {MouseEvent} event Router-link click event.
 * @returns {void}
 */
const stop = event => event.preventDefault();

/**
 * Synchronizes metadata for locally saved books with Hardcover.
 *
 * HTTP 207 indicates partial success and is handled as a valid result.
 *
 * @returns {Promise<void>}
 */
async function syncBooks() {
    syncing.value = true;

    try {
        const response = await fetch("/api/sync-books.php", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok && response.status !== 207) {
            await showToast(data.error || "Synchronization failed.", "danger");
            return;
        }

        const updated = data.updated ?? 0;
        const unchanged = data.unchanged ?? 0;
        const failed = data.failed ?? 0;
        const variant = failed > 0 && failed === (data.total ?? 0)
            ? "danger"
            : failed > 0
                ? "warning"
                : updated === 0
                    ? "info"
                    : "success";
        await showToast(`${updated} updated, ${unchanged} unchanged, ${failed} failed.`, variant);

        if (updated > 0) window.dispatchEvent(new CustomEvent("bookshelf:books-synced"));

    } catch (error) {
        await showToast(error instanceof Error
            ? error.message
            : "An unexpected error occurred.", "danger");

    } finally {
        syncing.value = false;
    }
}

/** Shows one auto-dismissing Bootstrap toast without affecting navbar layout. */
async function showToast(message, variant) {
    toastMessage.value = message;
    toastVariant.value = variant;
    await nextTick();
    Toast.getOrCreateInstance(toastElement.value, {autohide: true, delay: 5000}).show();
}
</script>

<template>
    <!-- Router links switch views without a full browser reload. -->
    <nav aria-label="Primary navigation" class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <RouterLink class="navbar-brand fw-semibold" to="/">Bookshelf</RouterLink>
            <button aria-controls="primary-navigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
                class="navbar-toggler"
                data-bs-target="#primary-navigation"
                data-bs-toggle="collapse"
                type="button"><span class="navbar-toggler-icon"/></button>
            <div id="primary-navigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li v-for="item in items" :key="item.to" class="nav-item">
                        <RouterLink :aria-disabled="item.soon ? 'true' : undefined"
                            :tabindex="item.soon ? -1 : undefined"
                            :to="item.to"
                            class="nav-link"
                            @click="item.soon ? stop($event) : undefined">{{ item.label }}<span v-if="item.soon"
                            class="visually-hidden"> (coming soon)</span></RouterLink>
                    </li>
                    <li class="nav-item">
                        <button :aria-label="'Sync with Hardcover'"
                            :disabled="syncing"
                            class="btn btn-sm sync-button nav-link"
                            title="Sync with Hardcover"
                            type="button"
                            @click="syncBooks">
                            <i :class="{ 'sync-icon--spinning': syncing }"
                                aria-hidden="true"
                                class="bi bi-arrow-clockwise sync-icon"/>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div aria-atomic="true"
        aria-live="polite"
        class="toast-container position-fixed top-0 end-0 p-3 sync-toast-container">
        <div ref="toastElement" :class="`text-bg-${toastVariant}`" class="toast border-0" role="status">
            <div class="d-flex">
                <div class="toast-body">{{ toastMessage }}</div>
                <button aria-label="Close"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    type="button"/>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sync-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sync-button:hover {
    animation: sync-hover 0.9s ease-in-out infinite alternate;
}

@keyframes sync-hover {
    to {
        transform: translateY(-4px);
    }
}

.sync-toast-container {
    z-index: 1090;
    margin-top: 3.5rem;
}

.sync-icon {
    line-height: 1;
    display: inline-grid;
    width: 1em;
    transform-origin: center;
    place-items: center;
    aspect-ratio: 1;
}

.sync-icon--spinning {
    animation: sync-rotate .9s linear infinite;
}

@keyframes sync-rotate {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sync-icon--spinning {
        animation: none;
    }
}
</style>
