<script setup>
import {computed} from "vue";
import {useRouter} from "vue-router";

const props = defineProps({list: {type: Object, required: true}});
const router = useRouter();
function openList() { router.push({name: "list-detail", params: {status: props.list.id}}); }
const preview = computed(() => props.list.recentBooks ?? props.list.previewBooks ?? []);
</script>

<template>
    <button class="book-list-row card w-100 text-start" type="button" @click="openList">
        <span class="book-list-row__title">{{ list.title }}</span>
        <span :aria-label="`${preview.length} recent books in ${list.title}`" class="book-list-row__previews">
            <span v-for="book in preview" :key="book.id" class="book-list-row__cover">
                <img v-if="book.cover_url" :alt="`Cover for ${book.title}`" :src="book.cover_url">
                <span v-else aria-hidden="true">No cover</span>
            </span>
            <span v-if="!preview.length" class="book-list-row__empty">No books yet</span>
        </span>
    </button>
</template>

<style lang="scss" scoped>
.book-list-row {
    display: grid;
    grid-template-columns: minmax(10rem, 14rem) minmax(0, 1fr);
    align-items: center;
    padding: .75rem 1rem;
    transition: background-color .18s ease;
    color: inherit;
    border: 1px solid var(--bs-border-color);
    gap: 1rem;
}
.book-list-row:hover, .book-list-row:focus-visible { background: var(--bs-tertiary-bg); }
.book-list-row__title { min-width: 0; font-size: 1.1rem; font-weight: 600; }
.book-list-row__previews {
    display: flex;
    min-width: 0;
    overflow: hidden;
    gap: .5rem;
}
.book-list-row__cover {
    display: flex;
    overflow: hidden;
    align-items: center;
    flex: 0 0 3.5rem;
    justify-content: center;
    aspect-ratio: 2 / 3;
    font-size: .65rem;
    text-align: center;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
}
.book-list-row__cover img { width: 100%; height: 100%; object-fit: cover; }
.book-list-row__empty { color: var(--bs-secondary-color); }

@media (min-width: 576px) {
    .book-list-row__cover { flex-basis: clamp(3.5rem, 8vw, 5rem); }
}

@media (max-width: 575.98px) {
    .book-list-row { grid-template-columns: minmax(0, 1fr) 3.5rem; min-height: 5.25rem; gap: .75rem; }
    .book-list-row__previews { justify-content: flex-end; }
    .book-list-row__cover { display: none; }
    .book-list-row__cover:first-child { display: flex; }
    .book-list-row__empty { text-align: right; }
}
</style>
