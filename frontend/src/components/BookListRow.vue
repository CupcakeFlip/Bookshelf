<template>
    <button class="book-list-row card w-100 text-start" type="button" @click="emit('open',list)"><span
        class="book-list-row__title">{{ list.title }}</span><span class="book-list-row__previews"
        :aria-label="`${preview.length} recent books in ${list.title}`"><span
        v-for="book in preview" :key="book.id" class="book-list-row__cover"><img v-if="book.cover??book.coverUrl"
        :src="book.cover??book.coverUrl"
        :alt="`Cover for ${book.title}`"><span
        v-else aria-hidden="true">No cover</span></span></span></button>
</template>
<script setup>
import {computed} from "vue";

const props = defineProps({list: {type: Object, required: true}, previewLimit: {type: Number, default: 8}});

const emit = defineEmits(["open"]);

const preview = computed(() => (props.list.recentBooks ?? props.list.previewBooks ?? []).slice(0, props.previewLimit));

</script>
<style scoped lang="scss">
.book-list-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    color: inherit;
    border: 1px solid var(--bs-border-color);
    transition: background-color .18s ease
}

.book-list-row:hover, .book-list-row:focus-visible {
    background: var(--bs-tertiary-bg)
}

.book-list-row__title {
    flex: 0 0 min(32%, 14rem);
    font-size: 1.1rem;
    font-weight: 600
}

.book-list-row__previews {
    display: flex;
    min-width: 0;
    gap: .5rem;
    overflow: hidden
}

.book-list-row__cover {
    display: flex;
    flex: 0 0 3.5rem;
    align-items: center;
    justify-content: center;
    aspect-ratio: 2/3;
    overflow: hidden;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
    font-size: .65rem;
    text-align: center
}

.book-list-row__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover
}

@media(max-width: 575.98px) {
    .book-list-row {
        align-items: flex-start;
        flex-direction: column
    }
    .book-list-row__title {
        flex-basis: auto
    }
    .book-list-row__previews {
        width: 100%
    }
}
</style>
