<!--
    Displays a reusable book card with a cover and a description on the reverse.
    Links to book details and shares the reading-status control across collections.
    Emits status changes and list requests for the parent view to handle.
-->

<script setup>
import BookStatusDropdown from "@/components/BookStatusDropdown.vue";
import {formatAuthors} from "@/utils/bookFormatting";
import {bookText} from "@/utils/bookText";
import {fallbackCoverImage, optimizedCoverSrcset, optimizedCoverUrl} from "@/utils/coverImages";
import {computed, nextTick, onBeforeUnmount, ref, watch} from "vue";

const isFlipped = ref(false);
const isMenuOpen = ref(false);


const props = defineProps({
    // The book data displayed by this card.
    book: {type: Object, required: true},
    coverLoading: {type: String, default: "lazy"},
});
/** User actions that the parent view can handle. */
const emit = defineEmits(["status-change", "add-to-list"]);

// Normalize possible API field names before displaying the book.
const cover_url = computed(() => props.book.cover_url ?? "");
const authors = computed(() =>
    formatAuthors(props.book.authors ?? props.book.author),
);
const name = computed(
    () =>
        props.book.series?.name ??
        props.book.seriesName ??
        (typeof props.book.series === "string" ? props.book.series : ""),
);
const position = computed(
    () => props.book.series?.position ?? props.book.seriesPosition,
);
const seriesText = computed(() =>
    name.value
        ? name.value + (position.value ? " #" + parseFloat(position.value) : "")
        : "",
);

const text = computed(() => bookText(props.book));
const descriptionBox = ref(null);
const descriptionOverflow = ref(false);
let descriptionObserver;

/**
 * Measures actual remaining card space instead of truncating by character count.
 * @returns {void}
 */
function measureDescription() {
    const box = descriptionBox.value;
    if (box) {
        descriptionOverflow.value = box.scrollHeight > box.clientHeight + 1;
    }
}

watch(descriptionBox, (box) => {
    descriptionObserver?.disconnect();
    if (box && typeof ResizeObserver !== "undefined") {
        descriptionObserver = new ResizeObserver(measureDescription);
        descriptionObserver.observe(box);
    }
    nextTick(measureDescription);
});
watch(text, () => nextTick(measureDescription));
onBeforeUnmount(() => descriptionObserver?.disconnect());
</script>

<template>
    <!-- The card exposes book details, status controls, and a separate list action. -->
    <article :class="{ 'dropdown-open': isMenuOpen }" class="h-100 book-card">
        <div
            :class="{ 'is-flipped': isFlipped }"
            class="book-card__inner book-card__main"
            @click="isFlipped = !isFlipped"
        >
            <div class="book-card__front">
                <div class="book-card__cover-wrapper">
                    <img
                        v-if="cover_url"
                        :alt="'Cover for ' + book.title"
                        :loading="coverLoading"
                        :src="optimizedCoverUrl(cover_url)"
                        :srcset="optimizedCoverSrcset(cover_url)"
                        class="book-cover"
                        decoding="async"
                        height="480"
                        sizes="(min-width: 1400px) 306px, (min-width: 1200px) 261px, (min-width: 992px) calc(25vw - 24px), (min-width: 768px) calc(33.333vw - 19px), calc(50vw - 20px)"
                        width="320"
                        @error="fallbackCoverImage($event, cover_url)"
                    />
                    <div
                        v-else
                        aria-hidden="true"
                        class="book-cover book-cover--placeholder"
                    >
                        No cover
                    </div>
                </div>
                <div class="book-card__content">
                    <h2 class="book-title">
                        {{ book.title }}
                    </h2>
                    <p
                        v-if="authors"
                        class="mb-1 book-meta book-authors book-authors__front"
                    >
                        {{ authors }}
                    </p>
                    <p
                        v-if="seriesText"
                        class="mb-2 book-meta book-seriesText book-seriesText__front"
                    >
                        {{ seriesText }}
                    </p>
                </div>
            </div>
            <div class="book-card__back">
                <div class="book-card__content">
                    <h3 class="book-title">
                        {{ book.title }}
                    </h3>
                    <p
                        v-if="authors"
                        class="mb-1 book-meta book-authors book-authors__back"
                    >
                        {{ authors }}
                    </p>
                    <p
                        v-if="seriesText"
                        class="mb-2 book-meta book-seriesText book-seriesText__back"
                    >
                        {{ seriesText }}
                    </p>

                    <hr/>

                    <p v-if="text.headline" class="mb-2 book-meta book-headline">
                        {{ text.headline }}
                    </p>
                    <div
                        v-if="text.description"
                        ref="descriptionBox"
                        :class="{ 'book-description--overflow': descriptionOverflow }"
                        class="mb-2 book-meta book-description"
                    >
                        <p class="mb-0">
                            {{ text.description }}
                        </p>
                    </div>
                    <RouterLink
                        :to="{
                            name: 'book-details',
                            params: { hardcoverId: book.hardcover_id },
                        }"
                        class="btn btn-primary btn-sm"
                        @click.stop
                    >
                        View Details
                    </RouterLink>
                </div>
            </div>
        </div>
        <hr class="m-0 mb-2"/>
        <div
            class="d-flex flex-wrap mt-auto m-2 gap-2 align-items-stretch justify-content-evenly"
        >
            <BookStatusDropdown
                :book="book"
                @status-change="emit('status-change', $event)"
                @menu-toggle="isMenuOpen = $event"
            />

            <div class="btn-add-to-list">
                <button
                    class="btn btn-sm btn-outline-light w-100"
                    type="button"
                    @click="emit('add-to-list', book)"
                >
                    <i class="bi bi-plus-lg"></i> List
                </button>
            </div>
        </div>
    </article>
</template>

<style lang="scss" scoped>
h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

h3 {
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.book-card {
    position: relative;
    z-index: 0;
    display: flex;
    overflow: visible;
    // Let the status menu escape the card while the cover keeps its own clipping.
    flex-direction: column;
    width: 100%;
    min-width: 0;
    perspective: 1000px;

    .dropdown,
    .btn-group {
        display: flex;
    }

    .btn {
        align-self: stretch;
    }
}

.book-card.dropdown-open {
    z-index: 1050;
}

.book-card__cover-wrapper {
    border-radius: 0.8rem 0.8rem 0 0;
}

.book-card__main {
    flex: 1;
    width: 100%;
    padding: 0;
    cursor: pointer;
    text-align: left;
    color: inherit;
    border: 0;
    background: transparent;
}

// Both faces share one grid cell; the front determines the available back-face height.
.book-card__inner {
    position: relative;
    display: grid;
    min-width: 0;
    transition: transform 0.6s;
    grid-template-columns: minmax(0, 1fr);
    transform-style: preserve-3d;
}

.book-card__inner.is-flipped {
    transform: rotateY(180deg);
}

.book-card__front,
.book-card__back {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 0;
    grid-area: 1 / 1;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.book-card__front {
    transform: rotateY(0deg);
}

.book-card__back {
    position: absolute;
    min-height: 0;
    transform: rotateY(180deg);
    inset: 0;
}

.book-card__cover-wrapper {
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
    width: 100%;
    min-width: 0;
    aspect-ratio: 2 / 3;
}

.book-cover {
    position: absolute;
    display: block;
    width: 100%;
    max-width: 100%;
    height: 100%;
    inset: 0;
    object-fit: cover;
}

.book-cover--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
}

.book-card__content {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    padding: 1rem;
    overflow-wrap: anywhere;
}

.book-card__back .book-card__content {
    overflow: hidden;
    min-height: 0;
}

.book-card__back .book-title,
.book-card__back .book-authors,
.book-card__back .book-seriesText,
.book-card__back .book-headline,
.book-card__back hr {
    flex-shrink: 0;
}

.book-card__back .btn {
    flex: none;
    margin-top: auto;
}

.book-card__main:focus-visible {
    outline: 2px solid #b985c6;
    outline-offset: -2px;
}

.book-description {
    overflow: hidden;
    flex: 1 1 auto;
    min-height: 0;
    white-space: pre-line;
    color: var(--bs-light);
}

.book-description--overflow {
    mask-image: linear-gradient(to bottom, black calc(100% - 1.5em), transparent);
}

.book-headline {
    font-weight: 600;
}

.book-authors {
    font-weight: 600;
}

.book-seriesText {
    font-style: italic;
}

.book-seriesText__back,
.book-authors__back {
    font-size: 0.9rem;
}
</style>
