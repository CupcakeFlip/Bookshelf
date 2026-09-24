<template>
    <article class="book-card h-100">
        <button class="book-card__main" type="button" :aria-label="'Open '+book.title" @click="emit('open',book)">
            <div class="book-card__cover-wrapper"><img v-if="coverUrl" :src="coverUrl" :alt="'Cover for '+book.title"
                class="book-cover">
                <div v-else class="book-cover book-cover--placeholder" aria-hidden="true">No cover</div>
            </div>
            <div class="book-card__content"><h2 class="book-title">{{ book.title }}</h2>
                <p v-if="authors" class="book-meta mb-1">{{ authors }}</p>
                <p v-if="seriesText" class="book-meta mb-2">{{ seriesText }}</p>
                <div class="mt-auto d-flex flex-wrap gap-2"><span v-if="book.format"
                    class="badge text-bg-surface-2">{{ book.format }}</span><span
                    v-if="book.status" class="badge status-badge" :class="statusClass">{{ statusLabel }}</span></div>
            </div>
        </button>
        <div class="book-card__actions">
            <button class="btn btn-sm btn-outline-light w-100" type="button" @click="emit('add-to-list',book)">Add to
                list
            </button>
        </div>
    </article>
</template>
<script setup>
import {computed} from "vue";

const props = defineProps({book: {type: Object, required: true}});
const emit = defineEmits(["open", "status-change", "add-to-list"]);
const coverUrl = computed(() => props.book.cover ?? props.book.coverUrl);
const authors = computed(
    () => Array.isArray(props.book.authors) ? props.book.authors.map(a => typeof a === "string" ? a : a?.name)
        .filter(Boolean).join(", ") : props.book.authors ?? props.book.author ?? "");
const name = computed(() => props.book.series?.name ?? props.book.seriesName ??
    (typeof props.book.series === "string" ? props.book.series : ""));
const position = computed(() => props.book.series?.position ?? props.book.seriesPosition);
const seriesText = computed(() => name.value ? name.value + (position.value ? " #" + position.value : "") : "");
const normalized = computed(
    () => String(props.book.status ?? "").toLowerCase().trim().replaceAll("_", "-").replaceAll(" ", "-"));
const statusClass = computed(() => ({
    "want-to-read": "status-want-read",
    "want-read": "status-want-read",
    reading: "status-reading",
    finished: "status-finished",
    read: "status-finished",
    paused: "status-paused",
    dnf: "status-dnf"
}[normalized.value] ?? "status-want-read"));
const statusLabel = computed(() => ({
    "want-to-read": "Want to read",
    "want-read": "Want to read",
    reading: "Reading",
    finished: "Finished",
    read: "Finished",
    paused: "Paused",
    dnf: "DNF"
}[normalized.value] ?? props.book.status));
</script>
<style scoped lang="scss">.book-card {
    display: flex;
    flex-direction: column
}

.book-card__main {
    display: flex;
    flex: 1;
    flex-direction: column;
    width: 100%;
    padding: 0;
    color: inherit;
    text-align: left;
    background: transparent;
    border: 0;
    cursor: pointer
}

.book-card__cover-wrapper {
    aspect-ratio: 2/3;
    overflow: hidden
}

.book-cover {
    width: 100%;
    height: 100%;
    object-fit: cover
}

.book-cover--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg)
}

.book-card__content {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 1rem
}

.book-card__actions {
    padding: 0 1rem 1rem
}

.book-card__main:focus-visible, .book-card__actions button:focus-visible {
    outline: 2px solid #b985c6;
    outline-offset: -2px
}</style>
