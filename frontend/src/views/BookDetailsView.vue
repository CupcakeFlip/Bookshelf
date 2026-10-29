<!--
    Coordinates the page for a book selected through its Hardcover ID.
    Loads metadata, manages owned formats and reading history through the PHP API,
    and combines shared status and session controls with the book description.
-->
<script setup>
import {
    createReadingSession,
    deleteReadingSession,
    getOwnedFormats,
    getReadingSessions,
    updateOwnedFormats,
    updateReadingSession,
} from "@/api/books";
import BookStatusDropdown from "@/components/BookStatusDropdown.vue";
import BookDetailsSkeleton from "@/components/loading/BookDetailsSkeleton.vue";
import ButtonSpinner from "@/components/loading/ButtonSpinner.vue";
import LoadingIndicator from "@/components/loading/LoadingIndicator.vue";

import Navbar from "@/components/Navbar.vue";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
import {bookText} from "@/utils/bookText";
import {fallbackCoverImage, optimizedCoverSrcset, optimizedCoverUrl} from "@/utils/coverImages";
import {computed, nextTick, onMounted, onUnmounted, ref, watch} from "vue";
import {useRoute} from "vue-router";

const route = useRoute();

const book = ref(null);
const loading = ref(true);
const error = ref("");
const ownedFormats = ref([]);
const sessions = ref([]);
const sessionsLoading = ref(false);
const sessionsError = ref("");
const editingSession = ref(null);
const formOpen = ref(false);
const sessionSaving = ref(false);
const deletingSessionIds = ref([]);
const formatsFetching = ref(false);
const pendingFormat = ref(null);
const bookContent = computed(() => bookText(book.value ?? {}));
const descriptionElement = ref(null);
const descriptionExpanded = ref(false);
const descriptionOverflows = ref(false);
const isMobileDescription = ref(false);
let descriptionObserver;
let mobileQuery;

function measureDescription() {
    const element = descriptionElement.value;
    if (!element || !isMobileDescription.value) {
        descriptionOverflows.value = false;
        return;
    }
    descriptionOverflows.value =
        element.scrollHeight > element.clientHeight + 1;
}

function updateDescriptionViewport() {
    isMobileDescription.value = mobileQuery.matches;
    nextTick(measureDescription);
}

watch(descriptionElement, (element) => {
    descriptionObserver?.disconnect();
    if (element && typeof ResizeObserver !== "undefined") {
        descriptionObserver = new ResizeObserver(measureDescription);
        descriptionObserver.observe(element);
    }
    nextTick(measureDescription);
});
watch(bookContent, () => nextTick(measureDescription));
let detailsRequest = 0;
let sessionsRequest = 0;

/**
 * Loads details for the route, then local ownership and reading history.
 * The request counter prevents an older route response from replacing the current book.
 * @returns {Promise<void>} Failures are displayed in the view.
 */
async function fetchBook() {
    const request = ++detailsRequest;
    loading.value = true;
    error.value = "";
    formatsError.value = "";
    try {
        const response = await fetch(
            `/api/book-details.php?hardcover_id=${route.params.hardcoverId}`,
        );

        if (!response.ok) {
            throw new Error("Could not load book details.");
        }

        const details = await response.json();
        if (request !== detailsRequest) {
            return;
        }
        book.value = details;
        if (details.id) {
            formatsFetching.value = true;
            getOwnedFormats(details.id)
                .then((formats) => {
                    if (request === detailsRequest) {
                        ownedFormats.value = formats;
                    }
                })
                .catch((err) => {
                    if (request === detailsRequest) {
                        formatsError.value = err.message;
                    }
                })
                .finally(() => {
                    if (request === detailsRequest) {
                        formatsFetching.value = false;
                    }
                });
            await loadSessions(details);
        } else {
            ownedFormats.value = [];
            sessions.value = [];
            formatsFetching.value = false;
            sessionsLoading.value = false;
        }
    } catch (err) {
        if (request === detailsRequest) {
            error.value = err.message;
        }
    } finally {
        if (request === detailsRequest) {
            loading.value = false;
        }
    }
}

/**
 * Refreshes reading history without replacing the book metadata.
 * @param {Object} [target] Saved book, defaulting to the displayed book.
 * @returns {Promise<void>}
 */
async function loadSessions(target = book.value) {
    if (!target?.id) {
        return;
    }
    const request = ++sessionsRequest;
    sessionsLoading.value = true;
    sessionsError.value = "";
    try {
        const result = await getReadingSessions(target);
        if (request === sessionsRequest) {
            sessions.value = result;
        }
    } catch (err) {
        if (request === sessionsRequest) {
            sessionsError.value = err.message;
        }
    } finally {
        if (request === sessionsRequest) {
            sessionsLoading.value = false;
        }
    }
}

/**
 * Displays only the date parts known at the stored precision.
 * @param {string|null} value Stored date.
 * @param {string} precision day, month, year, or unknown.
 * @returns {string} Localized date or unknown-date label.
 */
function dateLabel(value, precision) {
    if (!value || precision === "unknown") {
        return "Unknown date";
    }
    const [year, month] = value.split("-");
    if (precision === "year") {
        return year;
    }
    if (precision === "month") {
        return new Date(`${year}-${month}-01T00:00:00`).toLocaleDateString(
            undefined,
            {month: "short", year: "numeric"},
        );
    }
    return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
}

function dateRange(session) {
    const start = dateLabel(session.start_date, session.start_date_precision),
        end = dateLabel(session.finish_date, session.finish_date_precision);
    if (session.status === "dnf") {
        return `${session.start_date ? `Started: ${start}` : ""}${
            session.finish_date
                ? `${session.start_date ? " · " : ""}Stopped: ${end}`
                : ""
        }`;
    }
    return session.finish_date ? `${start} – ${end}` : `Started: ${start}`;
}

function progressLabel(session) {
    if (!session.dnf_progress_type) {
        return "";
    }
    if (session.dnf_progress_type === "time") {
        return `Progress: ${Math.floor(
            Number(session.dnf_progress_value) / 60,
        )}h ${Number(session.dnf_progress_value) % 60}m`;
    }
    return `Progress: ${session.dnf_progress_value}${session.dnf_progress_type === "percentage" ? "%" : " pages"}`;
}

/**
 * Creates or updates the selected reading attempt after form validation.
 * @param {Object} session API fields emitted by ReadingSessionForm.
 * @returns {Promise<void>} Errors keep the editor open.
 */
async function saveSession(session) {
    if (sessionSaving.value) {
        return;
    }
    const targetBookId = book.value?.id;
    sessionSaving.value = true;
    sessionsError.value = "";
    try {
        const saved = editingSession.value
            ? await updateReadingSession(editingSession.value.id, session)
            : await createReadingSession(book.value, session);
        if (book.value?.id !== targetBookId) {
            return;
        }
        const index = sessions.value.findIndex((item) => item.id === saved.id);
        if (index < 0) {
            sessions.value.push(saved);
        } else {
            sessions.value[index] = saved;
        }
        sessions.value.sort(
            (a, b) =>
                String(b.start_date ?? b.created_at).localeCompare(
                    String(a.start_date ?? a.created_at),
                ) || b.id - a.id,
        );
        formOpen.value = false;
        editingSession.value = null;
        if (saved.status === book.value.status) {
            return;
        }
        book.value = {...book.value, status: saved.status};
    } catch (err) {
        if (book.value?.id === targetBookId) {
            sessionsError.value = err.message;
        }
    } finally {
        sessionSaving.value = false;
    }
}

/**
 * Deletes one confirmed attempt and removes it from the visible history.
 * @param {Object} session Persisted reading attempt.
 * @returns {Promise<void>}
 */
async function removeSession(session) {
    if (
        deletingSessionIds.value.includes(session.id) ||
        (sessionSaving.value && editingSession.value?.id === session.id)
    ) {
        return;
    }
    if (!window.confirm("Delete this reading session?")) {
        return;
    }
    deletingSessionIds.value = [...deletingSessionIds.value, session.id];
    const targetBookId = book.value?.id;
    sessionsError.value = "";
    try {
        await deleteReadingSession(session.id);
        if (book.value?.id !== targetBookId) {
            return;
        }
        sessions.value = sessions.value.filter(
            (item) => item.id !== session.id,
        );
    } catch (err) {
        if (book.value?.id === targetBookId) {
            sessionsError.value = err.message;
        }
    } finally {
        deletingSessionIds.value = deletingSessionIds.value.filter(
            (id) => id !== session.id,
        );
    }
}

const formatsLoading = ref(false);
const formatsError = ref("");

/**
 * Replaces the owned-format selection after server confirmation.
 * @param {string} format physical, ebook, or audiobook.
 * @returns {Promise<void>} A failed write retains the previous selection.
 */
async function toggleFormat(format) {
    if (formatsLoading.value || formatsFetching.value) {
        return;
    }
    if (!book.value?.id) {
        formatsError.value = "The book does not exist in the database.";
        return;
    }
    const previousFormats = [...ownedFormats.value];
    const targetBookId = book.value.id;
    formatsLoading.value = true;
    pendingFormat.value = format;
    formatsError.value = "";

    try {
        let nextFormats;
        if (ownedFormats.value.includes(format)) {
            nextFormats = ownedFormats.value.filter((item) => item !== format);
        } else {
            nextFormats = [...ownedFormats.value, format];
        }
        await updateOwnedFormats(targetBookId, nextFormats);
        if (book.value?.id === targetBookId) {
            ownedFormats.value = nextFormats;
        }
    } catch (err) {
        if (book.value?.id === targetBookId) {
            formatsError.value = err.message;
            ownedFormats.value = previousFormats;
        }
    } finally {
        formatsLoading.value = false;
        pendingFormat.value = null;
    }
}

/** Merges the child status event into the current detail record. */
async function handleStatusChange({book: updatedBook, status}) {
    book.value = {
        ...book.value,
        ...updatedBook,
        status,
    };
    await loadSessions();
}

onMounted(() => {
    mobileQuery = window.matchMedia("(max-width: 767.98px)");
    mobileQuery.addEventListener("change", updateDescriptionViewport);
    updateDescriptionViewport();
    // Refresh after the global sync event because the current route may already
    // be open when shared metadata is updated.
    fetchBook();
    window.addEventListener("bookshelf:books-synced", fetchBook);
});
watch(
    () => route.params.hardcoverId,
    () => {
        descriptionExpanded.value = false;
        descriptionOverflows.value = false;
        ++sessionsRequest;
        book.value = null;
        ownedFormats.value = [];
        sessions.value = [];
        formatsFetching.value = false;
        sessionsLoading.value = false;
        fetchBook();
    },
);
onUnmounted(() => {
    descriptionObserver?.disconnect();
    mobileQuery?.removeEventListener("change", updateDescriptionViewport);
    ++detailsRequest;
    ++sessionsRequest;
    window.removeEventListener("bookshelf:books-synced", fetchBook);
});
</script>

<template>
    <Navbar/>

    <main class="container-xl py-4 book-details">
        <div class="wrapper">
            <section
                v-if="loading && !book"
                aria-busy="true"
                aria-label="Book details"
            >
                <span class="visually-hidden" role="status"
                >Loading book details…</span
                >
                <BookDetailsSkeleton/>
            </section>

            <LoadingIndicator
                v-if="loading && book"
                class="mb-3"
                label="Updating book details…"
                small
            />
            <section v-if="error" class="alert alert-danger" role="alert">
                {{ error }}
            </section>
            <!-- Book information -->

            <div v-if="book" :aria-busy="loading" class="row g-4">
                <section class="col-12 col-lg-8 book-details__information">
                    <div class="row g-3 align-items-start">
                        <!-- Book cover -->
                        <div class="col-6 col-sm-4 col-md-4">
                            <img
                                v-if="book.cover_url"
                                :alt="`Cover of ${book.title}`"
                                :src="optimizedCoverUrl(book.cover_url)"
                                :srcset="optimizedCoverSrcset(book.cover_url)"
                                class="rounded book-details__cover img-fluid"
                                decoding="async"
                                loading="eager"
                                sizes="(max-width: 767px) 177px, 275px"
                                @error="fallbackCoverImage($event, book.cover_url)"
                            />
                        </div>
                        <!-- Book metadata -->
                        <div class="col-6 col-sm-8 col-md-8">
                            <div class="d-flex flex-column gap-2">
                                <p
                                    v-if="book.series"
                                    class="mb-0 fst-italic book-details__series"
                                >
                                    {{ book.series.name }} #{{
                                        parseFloat(book.series.position)
                                    }}
                                </p>
                                <h1 class="mb-0 book-details__title">
                                    {{ book.title }}
                                </h1>
                                <p class="mb-0 book-details__authors lead">
                                    {{
                                        book.authors
                                            .map((author) => author.name)
                                            .join(", ")
                                    }}
                                </p>
                                <div
                                    class="d-flex flex-wrap gap-2 book-details__genres"
                                >
                                    <span
                                        v-for="genre in book.genres"
                                        :key="genre.slug"
                                        class="badge rounded-pill text-bg-primary"
                                    >
                                        {{ genre.name }}
                                    </span>
                                </div>
                                <p
                                    class="mb-0 text-body-secondary book-details__release small"
                                >
                                    {{ book.release_date }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4"/>
                    <!-- Description -->
                    <article class="book-details__description">
                        <h2 class="h5 visually-hidden">Description</h2>
                        <p
                            v-if="bookContent.headline"
                            class="book-details__headline"
                        >
                            {{ bookContent.headline }}
                        </p>
                        <p
                            v-if="bookContent.description"
                            id="book-details-description-text"
                            ref="descriptionElement"
                            :class="{
                                'book-details__description--collapsed':
                                    !descriptionExpanded,
                            }"
                        >
                            {{ bookContent.description }}
                        </p>
                        <button
                            v-if="
                                isMobileDescription &&
                                (descriptionOverflows || descriptionExpanded)
                            "
                            :aria-expanded="descriptionExpanded"
                            aria-controls="book-details-description-text"
                            class="ps-0 btn btn-link"
                            type="button"
                            @click="descriptionExpanded = !descriptionExpanded"
                        >
                            {{
                                descriptionExpanded ? "Read less" : "Read more"
                            }}
                        </button>
                        <p
                            v-if="
                                !bookContent.headline &&
                                !bookContent.description
                            "
                            class="text-body-secondary"
                        >
                            No description available.
                        </p>
                    </article>
                </section>
                <!-- Personal book management -->
                <aside class="col-12 col-lg-4 book-details__management">
                    <div class="d-flex flex-column gap-3">
                        <!-- Tags -->
                        <section class="card book-details__tags">
                            <div class="card-body">
                                <h2 class="card-title h6">Tags</h2>
                                <div class="d-flex flex-wrap gap-2">
                                    <span
                                        v-for="tag in book.tags ?? []"
                                        :key="tag.slug"
                                        class="badge rounded-pill text-bg-primary"
                                    >
                                        {{ tag.name }}
                                    </span>
                                    <button
                                        class="badge rounded-pill text-bg-primary border-0"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </section>
                        <!-- Owned formats -->
                        <section class="card book-details__formats">
                            <div class="card-body">
                                <h2 class="card-title h6">Format</h2>
                                <LoadingIndicator
                                    v-if="formatsFetching"
                                    class="mb-2"
                                    label="Loading owned formats…"
                                    small
                                />
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        :aria-busy="
                                            pendingFormat === 'physical'
                                        "
                                        :class="
                                            ownedFormats.includes('physical')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        :disabled="
                                            formatsLoading || formatsFetching
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('physical')"
                                    >
                                        <ButtonSpinner
                                            v-if="pendingFormat === 'physical'"
                                        />
                                        Physical
                                    </button>
                                    <button
                                        :aria-busy="pendingFormat === 'ebook'"
                                        :class="
                                            ownedFormats.includes('ebook')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        :disabled="
                                            formatsLoading || formatsFetching
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('ebook')"
                                    >
                                        <ButtonSpinner
                                            v-if="pendingFormat === 'ebook'"
                                        />
                                        E-book
                                    </button>
                                    <button
                                        :aria-busy="
                                            pendingFormat === 'audiobook'
                                        "
                                        :class="
                                            ownedFormats.includes('audiobook')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        :disabled="
                                            formatsLoading || formatsFetching
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('audiobook')"
                                    >
                                        <ButtonSpinner
                                            v-if="pendingFormat === 'audiobook'"
                                        />
                                        Audiobook
                                    </button>
                                </div>
                                <p
                                    v-if="formatsError"
                                    class="mt-2 mb-0 text-danger small"
                                    role="alert"
                                >
                                    {{ formatsError }}
                                </p>
                            </div>
                        </section>
                        <!-- Reading status -->
                        <section class="card book-details__status">
                            <div class="card-body">
                                <h2 class="card-title h6">Status</h2>
                                <BookStatusDropdown
                                    :book="book"
                                    @status-change="handleStatusChange"
                                />
                            </div>
                        </section>
                        <!-- Reading history -->
                        <section class="card book-details__history">
                            <div class="card-body">
                                <h2 class="card-title h6">Reading History</h2>
                                <LoadingIndicator
                                    v-if="sessionsLoading"
                                    class="mb-2"
                                    label="Loading reading history…"
                                    small
                                />
                                <p
                                    v-if="sessionsError"
                                    class="py-2 alert alert-danger"
                                    role="alert"
                                >
                                    {{ sessionsError }}
                                </p>
                                <p
                                    v-if="
                                        !sessionsLoading &&
                                        !sessionsError &&
                                        !sessions.length
                                    "
                                    class="text-body-secondary small"
                                >
                                    No reading sessions yet.
                                </p>
                                <div
                                    v-if="sessions.length"
                                    class="vstack gap-3"
                                >
                                    <article
                                        v-for="(session, index) in sessions"
                                        :key="session.id"
                                        class="pb-3 border-bottom"
                                    >
                                        <div
                                            class="d-flex gap-2 justify-content-between"
                                        >
                                            <strong
                                            >Read #{{
                                                    sessions.length - index
                                                }}
                                                — {{ session.status }}</strong
                                            ><span
                                            class="text-body-secondary small"
                                        >{{ dateRange(session) }}</span
                                        >
                                        </div>
                                        <div class="text-body-secondary small">
                                            <span
                                                v-if="progressLabel(session)"
                                            >{{
                                                    progressLabel(session)
                                                }}</span
                                            ><span
                                            v-if="session.formats?.length"
                                        >{{
                                                progressLabel(session)
                                                    ? " · "
                                                    : ""
                                            }}{{
                                                session.formats.join(", ")
                                            }}</span
                                        >
                                        </div>
                                        <button
                                            :disabled="
                                                sessionSaving ||
                                                deletingSessionIds.includes(
                                                    session.id,
                                                )
                                            "
                                            class="ps-0 btn btn-link btn-sm"
                                            @click="
                                                editingSession = session;
                                                formOpen = true;
                                            "
                                        >
                                            Edit
                                        </button>
                                        <button
                                            :aria-busy="
                                                deletingSessionIds.includes(
                                                    session.id,
                                                )
                                            "
                                            :disabled="
                                                deletingSessionIds.includes(
                                                    session.id,
                                                ) ||
                                                (sessionSaving &&
                                                    editingSession?.id ===
                                                        session.id)
                                            "
                                            class="btn btn-link btn-sm text-danger"
                                            @click="removeSession(session)"
                                        >
                                            <ButtonSpinner
                                                v-if="
                                                    deletingSessionIds.includes(
                                                        session.id,
                                                    )
                                                "
                                            />
                                            Delete
                                        </button>
                                    </article>
                                </div>
                                <div v-if="formOpen" class="mt-3">
                                    <ReadingSessionForm
                                        :model-value="editingSession"
                                        :saving="sessionSaving"
                                        @cancel="
                                            formOpen = false;
                                            editingSession = null;
                                        "
                                        @save="saveSession"
                                    />
                                </div>
                                <button
                                    v-else
                                    class="mt-2 btn btn-outline-primary btn-sm"
                                    @click="
                                        editingSession = null;
                                        formOpen = true;
                                    "
                                >
                                    + Add reading session
                                </button>
                            </div>
                        </section>
                        <!-- Lists -->
                        <section class="card book-details__lists">
                            <div class="card-body">
                                <h2 class="card-title h6">Lists</h2>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</template>

<style scoped>
.book-details__headline,
.book-details__description p {
    white-space: pre-line;
}

.book-details__cover {
    display: block;
    width: 100%;
    height: auto;
}

@media (max-width: 767.98px) {
    .book-details__description--collapsed {
        display: -webkit-box;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        line-clamp: 3;
    }
}
</style>
