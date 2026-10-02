<template>
    <!-- Hele rækken er klikbar, så den fungerer som én tydelig handling for brugeren. -->
    <button class="book-list-row card w-100 text-start" type="button" @click="emit('open',list)"><span
        class="book-list-row__title">{{
            list.title
        }}</span><span :aria-label="`${preview.length} recent books in ${list.title}`"
        class="book-list-row__previews"><span
        v-for="book in preview" :key="book.id" class="book-list-row__cover"><img v-if="book.cover??book.cover_url"
        :alt="`Cover for ${book.title}`"
        :src="book.cover??book.cover_url"><span
        v-else aria-hidden="true">No cover</span></span></span></button>
</template>
<script setup>
import {computed} from "vue";

const props = defineProps({list: {type: Object, required: true}, previewLimit: {type: Number, default: 8}});

const emit = defineEmits(["open"]);

// Understøtter begge navne på preview-feltet og begrænser antallet af viste bøger.
const preview = computed(() => (props.list.recentBooks ?? props.list.previewBooks ?? []).slice(0, props.previewLimit));

</script>
<style lang="scss" scoped>
.book-list-row {
    display: flex;
    align-items: center;
    padding: 1rem;
    transition: background-color .18s ease;
    color: inherit;
    border: 1px solid var(--bs-border-color);
    gap: 1rem;
}

.book-list-row:hover, .book-list-row:focus-visible {
    background: var(--bs-tertiary-bg)
}

.book-list-row__title {
    font-size: 1.1rem;
    font-weight: 600;
    flex: 0 0 min(32%, 14rem);
}

.book-list-row__previews {
    display: flex;
    overflow: hidden;
    min-width: 0;
    gap: .5rem;
}

.book-list-row__cover {
    font-size: .65rem;
    display: flex;
    overflow: hidden;
    align-items: center;
    flex: 0 0 3.5rem;
    justify-content: center;
    text-align: center;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
    aspect-ratio: 2/3;
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
