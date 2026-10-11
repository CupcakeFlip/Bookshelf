<script setup>
import { ref } from "vue";
const syncing = ref(false);
const syncMessage = ref("");
const syncError = ref("");
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
 * The endpoint returns 207 when some books fail; that is still useful because
 * successful updates are reported and the library is refreshed afterwards.
 *
 * @returns {Promise<void>}
 */
async function syncBooks() {
    syncing.value = true; syncMessage.value = ""; syncError.value = "";
    try {
        const response = await fetch("/api/sync-books.php", { method: "POST", headers: { "X-Requested-With": "XMLHttpRequest" } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok && response.status !== 207) throw new Error(data.error || "Synchronization failed.");
        syncMessage.value = `${data.updated ?? 0} updated${data.failed ? `, ${data.failed} failed` : ""}.`;
        window.dispatchEvent(new CustomEvent("bookshelf:books-synced"));
    } catch (error) { syncError.value = error.message; }
    finally { syncing.value = false; }
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
                        <button :disabled="syncing" class="btn btn-sm btn-outline-light" type="button" @click="syncBooks">
                            <span v-if="syncing" aria-hidden="true" class="spinner-border spinner-border-sm me-1" role="status" />
                            {{ syncing ? "Syncing…" : "Sync with Hardcover" }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div v-if="syncMessage || syncError" class="container pb-2">
            <div v-if="syncMessage" class="alert alert-success py-2 mb-0" role="status">{{ syncMessage }}</div>
            <div v-if="syncError" class="alert alert-danger py-2 mb-0" role="alert">{{ syncError }}</div>
        </div>
    </nav>
</template>

<style scoped></style>
