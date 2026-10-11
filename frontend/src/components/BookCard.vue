<template>
    <!-- Kortets hovedhandling åbner bogen, mens den separate knap tilføjer den til en liste. -->
    <article class="book-card h-100">
        <div
            class="book-card__inner book-card__main"
            :class="{ 'is-flipped': isFlipped }"
            @click="isFlipped = !isFlipped"
        >
            <div class="book-card__front">
                <div class="book-card__cover-wrapper">
                    <img
                        v-if="cover_url"
                        :alt="'Cover for ' + book.title"
                        :src="cover_url"
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
                    <p v-if="authors" class="book-meta mb-1">
                        {{ authors }}
                    </p>
                    <p v-if="seriesText" class="book-meta mb-2">
                        {{ seriesText }}
                    </p>
                </div>
            </div>
            <div class="book-card__back">
                <div class="book-card__content">
                    <h2 class="book-title">
                        {{ book.title }}
                    </h2>
                    <p v-if="authors" class="book-meta mb-1">
                        {{ authors }}
                    </p>
                    <p v-if="seriesText" class="book-meta mb-2">
                        {{ seriesText }}
                    </p>

                    <p
                        v-if="description"
                        class="book-meta mb-2 book-description"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
        </div>
        <hr class="m-0 mb-2" />
        <div class="mt-auto d-flex flex-wrap justify-content-evenly m-2 gap-2">
            <div class="">
                <button
                    v-if="book.status"
                    :class="statusClass"
                    class="badge status-badge h-100"
                >
                    {{ statusLabel }}
                </button>
            </div>

            <div class="">
                <button
                    class="btn btn-sm btn-outline-light w-100"
                    type="button"
                    @click="emit('add-to-list', book)"
                >
                    Add to list
                </button>
            </div>
        </div>
    </article>
</template>
<script setup>
import { computed, ref } from "vue";
const isFlipped = ref(false);

/** Book data used to populate the card. */
const props = defineProps({ book: { type: Object, required: true } });
/** User actions that the parent view can handle. */
const emit = defineEmits(["open", "status-change", "add-to-list"]);

// Normalize possible API field names before displaying the book.
const cover_url = computed(() => props.book.cover_url ?? "");
const authors = computed(() =>
    Array.isArray(props.book.authors)
        ? props.book.authors
              .map((a) => (typeof a === "string" ? a : a?.name))
              .filter(Boolean)
              .join(", ")
        : (props.book.authors ?? props.book.author ?? ""),
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
        ? name.value + (position.value ? " #" + Number(position.value, 1) : "")
        : "",
);

const description = computed(() => props.book.description ?? "");

// Normalize status spelling so the same status always gets the same label and style.
const normalized = computed(() =>
    String(props.book.status ?? "")
        .toLowerCase()
        .trim()
        .replaceAll("_", "-")
        .replaceAll(" ", "-"),
);
const statusClass = computed(
    () =>
        ({
            "want-to-read": "status-want-read",
            "want-read": "status-want-read",
            reading: "status-reading",
            finished: "status-finished",
            read: "status-finished",
            paused: "status-paused",
            dnf: "status-dnf",
        })[normalized.value] ?? "status-want-read",
);
const statusLabel = computed(
    () =>
        ({
            "want-to-read": "Want to read",
            "want-read": "Want to read",
            reading: "Reading",
            finished: "Finished",
            read: "Finished",
            paused: "Paused",
            dnf: "DNF",
        })[normalized.value] ?? props.book.status,
);
</script>
<style lang="scss" scoped>
.book-card {
    display: flex;
    flex-direction: column;
    perspective: 1000px;
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
    grid-area: 1 / 1;
    display: flex;
    flex-direction: column;
    width: 100%;
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
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 12;
    line-clamp: 12;
}
</style>
