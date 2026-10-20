<script setup>
import { computed, ref } from "vue";
import { formatAuthors } from "@/utils/bookFormatting";
import { bookText } from "@/utils/bookText";
import BookStatusDropdown from "@/components/BookStatusDropdown.vue";
import { optimizedCoverUrl, optimizedCoverSrcset } from "@/utils/coverImages";

const isFlipped = ref(false);
const isMenuOpen = ref(false);

/**
 * Book data supplied by a view or by the PHP API.
 *
 * @typedef {Object} BookCardData
 * @property {string|number} hardcover_id Stable Hardcover identifier.
 * @property {string} title Display title.
 * @property {string} [cover_url] Optional cover image URL.
 * @property {Array<string|{name?: string}>} [authors] Author names or API objects.
 * @property {{name?: string, position?: number|string}|string} [series] Series metadata.
 * @property {string|null} [status] Current locally persisted reading status.
 */
/** @type {{book: BookCardData}} */
const props = defineProps({
    book: { type: Object, required: true },
    coverLoading: { type: String, default: "lazy" },
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
                        :src="optimizedCoverUrl(cover_url)"
                        :srcset="optimizedCoverSrcset(cover_url)"
                        sizes="(max-width: 767px) 163px, 298px"
                        :loading="coverLoading"
                        decoding="async"
                        class="book-cover"
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

                    <hr />

                    <div
                        v-if="text.headline || text.description"
                        class="mb-2 book-meta book-description"
                    >
                        <p v-if="text.headline" class="mb-2 book-headline">
                            {{ text.headline }}
                        </p>
                        <p v-if="text.description" class="mb-0">
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
        <hr class="m-0 mb-2" />
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

.book-card__inner {
    display: grid;
    transition: transform 0.6s;
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
    grid-area: 1 / 1;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.book-card__front {
    transform: rotateY(0deg);
}

.book-card__back {
    transform: rotateY(180deg);
}

.book-card__cover-wrapper {
    overflow: hidden;
    aspect-ratio: 2 / 3;
}

.book-cover {
    width: 100%;
    height: 100%;
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
    padding: 1rem;
}

.book-card__actions {
    padding: 0 1rem 1rem;
}

.book-card__main:focus-visible,
.book-card__actions button:focus-visible {
    outline: 2px solid #b985c6;
    outline-offset: -2px;
}

.book-description {
    display: -webkit-box;
    overflow: hidden;
    white-space: pre-line;
    color: var(--bs-light);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 12;
    line-clamp: 12;
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
