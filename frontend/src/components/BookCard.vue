<script setup>
import {computed, onMounted, onUnmounted, ref} from "vue";
import {removeBookStatus, saveBookStatus} from "@/api/books";
import {formatAuthors} from "@/utils/bookFormatting";

const isFlipped = ref(false);
const isMenuOpen = ref(false);
const dropdownElement = ref(null);
const pending = ref(false);
const errorMessage = ref("");

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
const props = defineProps({book: {type: Object, required: true}});
/** User actions that the parent view can handle. */
const emit = defineEmits(["open", "status-change", "add-to-list"]);
const statusOptions = [
    {value: null, label: "Not in library"},
    {value: "want_to_read", label: "Want to read"},
    {value: "reading", label: "Reading"},
    {value: "finished", label: "Finished"},
    {value: "paused", label: "Paused"},
    {value: "dnf", label: "Did not finish"},
];
const currentStatus = computed(() => props.book.status ?? null);

// Normalize possible API field names before displaying the book.
const cover_url = computed(() => props.book.cover_url ?? "");
const authors = computed(() => formatAuthors(props.book.authors ?? props.book.author));
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
        })[normalized.value] ?? "Not in library",
);
const statusButtonClass = computed(() => currentStatus.value
    ? `btn-status btn-${statusClass.value.replace("status-", "")}`
    : "btn-status btn-not-in-library");

/**
 * Persists a status change and reports the updated book to the parent grid.
 *
 * @param {"want_to_read"|"reading"|"finished"|"dnf"|null} status
 *        New status, or null to remove the book from the library.
 * @returns {Promise<void>}
 */
async function selectStatus(status) {
    if (pending.value || status === currentStatus.value) {
        isMenuOpen.value = false;
        return;
    }
    const previous = currentStatus.value;
    pending.value = true;
    errorMessage.value = "";
    isMenuOpen.value = false;
    try {
        const saved = status === null ? await removeBookStatus(props.book) : await saveBookStatus(props.book, status);
        emit("status-change", {book: saved.status === undefined ? {...props.book, status} : saved, status, previous});
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        pending.value = false;
    }
}

/**
 * Uses the primary status button as a shortcut for adding an untracked book.
 *
 * @returns {void}
 */
function mainStatusAction() {
    if (currentStatus.value === null) selectStatus("want_to_read");
}

/**
 * Closes the status dropdown when clicking outside it.
 */
function handleOutsideClick(event) {
    if (
        isMenuOpen.value &&
        dropdownElement.value &&
        !dropdownElement.value.contains(event.target)
    ) {
        isMenuOpen.value = false;
    }
}

/**
 * Closes the status dropdown when pressing Escape.
 */
function handleEscape(event) {
    if (event.key === "Escape" && isMenuOpen.value) {
        isMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener("pointerdown", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
    document.removeEventListener("pointerdown", handleOutsideClick);
    document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
    <!-- The card exposes book details, status controls, and a separate list action. -->
    <article :class="{ 'dropdown-open': isMenuOpen }" class="book-card h-100">
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
                    <p v-if="authors" class="book-meta mb-1 book-authors book-authors__front">
                        {{ authors }}
                    </p>
                    <p v-if="seriesText" class="book-meta mb-2 book-seriesText book-seriesText__front">
                        {{ seriesText }}
                    </p>
                </div>
            </div>
            <div class="book-card__back">
                <div class="book-card__content">
                    <h3 class="book-title">
                        {{ book.title }}
                    </h3>
                    <p v-if="authors" class="book-meta mb-1 book-authors book-authors__back">
                        {{ authors }}
                    </p>
                    <p v-if="seriesText" class="book-meta mb-2 book-seriesText book-seriesText__back ">
                        {{ seriesText }}
                    </p>

                    <hr>

                    <p
                        v-if="description"
                        class="book-meta mb-2
                        book-description"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
        </div>
        <hr class="m-0 mb-2"/>
        <div class="mt-auto d-flex flex-wrap align-items-stretch justify-content-evenly m-2 gap-2">
            <div ref="dropdownElement" class="dropdown" @click.stop>
                <div class="btn-group w-100 h-100" role="group">
                    <button :class="statusButtonClass"
                        :disabled="pending"
                        class="btn btn-sm"
                        type="button"
                        @click="mainStatusAction">
                        {{ statusLabel }}
                    </button>
                    <button :aria-expanded="isMenuOpen"
                        :class="statusButtonClass"
                        :disabled="pending"
                        class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                        type="button"
                        @click="isMenuOpen = !isMenuOpen">
                        <span class="visually-hidden">Toggle reading status</span>
                    </button>
                </div>
                <ul v-if="isMenuOpen" class="dropdown-menu show">
                    <li v-for="option in statusOptions" :key="option.value">
                        <button :class="{ active: currentStatus === option.value }"
                            class="dropdown-item"
                            type="button"
                            @click="selectStatus(option.value)">
                            {{ option.label }}
                        </button>
                    </li>
                </ul>
            </div>

            <div class="">
                <button
                    class="btn btn-sm btn-outline-light w-100"
                    type="button"
                    @click="emit('add-to-list', book)"
                >
                    <i class="bi bi-plus-lg"></i> List
                </button>
            </div>
        </div>
        <p v-if="errorMessage" class="small text-danger px-2 mb-2" role="alert">{{ errorMessage }}</p>
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

.dropdown-menu {
    z-index: 1060;
}

.btn-not-in-library {
    color: var(--bs-btn-color) !important;
    border-color: var(--bs-btn-border-color) !important;
    background-color: var(--bs-btn-bg) !important;
    --bs-btn-color: #f5ece8;
    --bs-btn-bg: #625b58;
    --bs-btn-border-color: #625b58;
    --bs-btn-hover-color: #f5ece8;
    --bs-btn-hover-bg: #756c68;
    --bs-btn-hover-border-color: #756c68;
    --bs-btn-active-color: #f5ece8;
    --bs-btn-active-bg: #504a47;
    --bs-btn-active-border-color: #504a47;
}

.book-card__cover-wrapper {
    border-radius: .8rem .8rem 0 0;
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
    color: var(--bs-light);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 12;
    line-clamp: 12;
}

.book-authors {
    font-weight: 600;
}

.book-seriesText {
    font-style: italic;
}

.book-seriesText__back, .book-authors__back {
    font-size: 0.9rem;
}


</style>
