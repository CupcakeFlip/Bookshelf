<script setup>
import {computed, onMounted, onUnmounted, ref} from "vue";
import {useRoute} from "vue-router";

import {
    createReadingSession,
    deleteReadingSession,
    getOwnedFormats,
    getReadingSessions,
    updateOwnedFormats,
    updateReadingSession,
} from "@/api/books";

import Navbar from "@/components/Navbar.vue";
import BookStatusDropdown from "@/components/BookStatusDropdown.vue";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
import {bookText} from "@/utils/bookText";
import {optimizedCoverUrl, optimizedCoverSrcset} from "@/utils/coverImages";

const route = useRoute();
//const isOwned = ref(false);

const book = ref(null);
const loading = ref(true);
const error = ref("");
const ownedFormats = ref([]);
const sessions = ref([]);
const sessionsLoading = ref(false);
const sessionsError = ref("");
const editingSession = ref(null);
const formOpen = ref(false);
const bookContent = computed(() => bookText(book.value ?? {}));

/** Loads the normalized local or external representation for the route book. */
async function fetchBook() {
    ownedFormats.value = [];
    try {
        const response = await fetch(
            `/api/book-details.php?hardcover_id=${route.params.hardcoverId}`,
        );

        if (!response.ok) {
            throw new Error("Could not load book details.");
        }

        book.value = await response.json();

        if (book.value.id) {
            ownedFormats.value = await getOwnedFormats(book.value.id);
            await loadSessions();
        }
    } catch (err) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
}

async function loadSessions() {
    if (!book.value?.id) return;
    sessionsLoading.value = true;
    sessionsError.value = "";
    try {
        sessions.value = await getReadingSessions(book.value);
    } catch (err) {
        sessionsError.value = err.message;
    } finally {
        sessionsLoading.value = false;
    }
}

function dateLabel(value, precision) {
    if (!value || precision === "unknown") return "Unknown date";
    const [year, month, day] = value.split("-");
    if (precision === "year") return year;
    if (precision === "month")
        return new Date(`${year}-${month}-01T00:00:00`).toLocaleDateString(
            undefined,
            {month: "short", year: "numeric"},
        );
    return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
}

function dateRange(session) {
    const start = dateLabel(session.start_date, session.start_date_precision),
        end = dateLabel(session.finish_date, session.finish_date_precision);
    if (session.status === "dnf")
        return `${session.start_date ? `Started: ${start}` : ""}${
            session.finish_date
                ? `${session.start_date ? " · " : ""}Stopped: ${end}`
                : ""
        }`;
    return session.finish_date ? `${start} – ${end}` : `Started: ${start}`;
}

function progressLabel(session) {
    if (!session.dnf_progress_type) return "";
    if (session.dnf_progress_type === "time")
        return `Progress: ${Math.floor(
            Number(session.dnf_progress_value) / 60,
        )}h ${Number(session.dnf_progress_value) % 60}m`;
    return `Progress: ${session.dnf_progress_value}${session.dnf_progress_type === "percentage" ? "%" : " pages"}`;
}

async function saveSession(session) {
    sessionsError.value = "";
    try {
        const saved = editingSession.value
            ? await updateReadingSession(editingSession.value.id, session)
            : await createReadingSession(book.value, session);
        const index = sessions.value.findIndex((item) => item.id === saved.id);
        if (index < 0) sessions.value.push(saved);
        else sessions.value[index] = saved;
        sessions.value.sort(
            (a, b) =>
                String(b.start_date ?? b.created_at).localeCompare(
                    String(a.start_date ?? a.created_at),
                ) || b.id - a.id,
        );
        formOpen.value = false;
        editingSession.value = null;
        if (saved.status === book.value.status) return;
        book.value = {...book.value, status: saved.status};
    } catch (err) {
        sessionsError.value = err.message;
    }
}

async function removeSession(session) {
    if (!window.confirm("Delete this reading session?")) return;
    try {
        await deleteReadingSession(session.id);
        sessions.value = sessions.value.filter(
            (item) => item.id !== session.id,
        );
    } catch (err) {
        sessionsError.value = err.message;
    }
}

const formatsLoading = ref(false);
const formatsError = ref("");

async function toggleFormat(format) {
    if (formatsLoading.value) return;
    if (!book.value?.id) {
        formatsError.value = "The book does not exist in the database.";
        return;
    }
    const previousFormats = [...ownedFormats.value];
    formatsLoading.value = true;
    formatsError.value = "";

    try {
        if (ownedFormats.value.includes(format)) {
            ownedFormats.value = ownedFormats.value.filter(
                (item) => item !== format,
            );
        } else {
            ownedFormats.value.push(format);
        }
        await updateOwnedFormats(book.value.id, ownedFormats.value);
    } catch (err) {
        formatsError.value = err.message;
        ownedFormats.value = previousFormats;
    } finally {
        formatsLoading.value = false;
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
    // Refresh after the global sync event because the current route may already
    // be open when shared metadata is updated.
    fetchBook();
    window.addEventListener("bookshelf:books-synced", fetchBook);
});
onUnmounted(() =>
    window.removeEventListener("bookshelf:books-synced", fetchBook),
);
</script>

<template>
    <Navbar/>

    <main class="container-xl py-4 book-details">
        <div class="wrapper">
            <section v-if="loading">
                <p>Loading book...</p>
            </section>

            <section v-else-if="error" class="alert alert-danger">
                {{ error }}
            </section>
            <!-- Book information -->

            <div v-else-if="book" class="row g-4">
                <section class="col-12 col-lg-8 book-details__information">
                    <div class="row g-3 align-items-start">
                        <!-- Book cover -->
                        <div class="col-6 col-sm-4 col-md-4">
                            <img
                                v-if="book.cover_url"
                                :alt="`Cover of ${book.title}`"
                                :src="optimizedCoverUrl(book.cover_url)"
                                :srcset="optimizedCoverSrcset(book.cover_url)"
                                sizes="(max-width: 767px) 177px, 275px"
                                loading="eager"
                                decoding="async"
                                class="rounded book-details__cover img-fluid"
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
                        <h2 class="h5">Description</h2>
                        <p
                            v-if="bookContent.headline"
                            class="book-details__headline"
                        >
                            {{ bookContent.headline }}
                        </p>
                        <p v-if="bookContent.description">
                            {{ bookContent.description }}
                        </p>
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
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        :class="
                                            ownedFormats.includes('physical')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('physical')"
                                    >
                                        Physical
                                    </button>
                                    <button
                                        :class="
                                            ownedFormats.includes('ebook')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('ebook')"
                                    >
                                        E-book
                                    </button>
                                    <button
                                        :class="
                                            ownedFormats.includes('audiobook')
                                                ? 'btn-primary'
                                                : 'btn-outline-primary'
                                        "
                                        class="btn btn-sm"
                                        type="button"
                                        @click="toggleFormat('audiobook')"
                                    >
                                        Audiobook
                                    </button>
                                </div>
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
                                <p
                                    v-if="sessionsLoading"
                                    class="text-body-secondary small"
                                >
                                    Loading reading history...
                                </p>
                                <p
                                    v-else-if="sessionsError"
                                    class="py-2 alert alert-danger"
                                >
                                    {{ sessionsError }}
                                </p>
                                <p
                                    v-else-if="!sessions.length"
                                    class="text-body-secondary small"
                                >
                                    No reading sessions yet.
                                </p>
                                <div v-else class="vstack gap-3">
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
                                            class="ps-0 btn btn-link btn-sm"
                                            @click="
                                                editingSession = session;
                                                formOpen = true;
                                            "
                                        >
                                            Edit
                                        </button>
                                        <button
                                            class="btn btn-link btn-sm text-danger"
                                            @click="removeSession(session)"
                                        >
                                            Delete
                                        </button>
                                    </article>
                                </div>
                                <div v-if="formOpen" class="mt-3">
                                    <ReadingSessionForm
                                        :model-value="editingSession"
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
</style>
