<!--
    Displays the saved library with server-side search, filters, sorting, and pages.
    Owns collection state and coordinates the shared filter, grid, and pagination
    components with the books API.
-->
<script setup>
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Navbar from "@/components/Navbar.vue";
import Pagination from "@/components/Pagination.vue";
import {scrollToCollection} from "@/utils/collectionScroll";
import {createLatestRequest} from "@/utils/latestRequest";
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";
import {onMounted, onUnmounted, ref} from "vue";

const collectionElement = ref(null);

const books = ref([]),
    loading = ref(true),
    error = ref(""),
    page = ref(1),
    loadedPage = ref(1),
    limit = ref(getSavedPageLimit()), totalPages = ref(0),
    filters = ref({
        q: "",
        genres: [],
        categories: [],
        sort: "recently_updated_desc",
    });
const requests = createLatestRequest();

/**
 * Fetches one server-filtered page while keeping existing cards visible.
 * Only the latest request can commit results or clear the loading indicator.
 * @param {boolean} [scroll=false] Scroll after a successful page or size change.
 * @returns {Promise<void>} Errors leave the last loaded page visible.
 */
async function fetchBooks(scroll = false) {
    const request = requests.start();
    let loaded = false;
    loading.value = true;
    error.value = "";
    try {
        const next = filters.value;
        const params = new URLSearchParams(
            {q: next.q, sort: next.sort, page: String(page.value), limit: String(limit.value)});
        if (next.categories?.length) {
            params.set("categories", next.categories.join(","));
        } else if (next.genres?.length) {
            params.set("genres", next.genres.join(","));
        }
        const response = await fetch(`/api/books.php?${params}`, {
                signal: request.signal,
            }),
            payload = await response.json().catch(() => ({}));
        if (!requests.isCurrent(request)) {
            return;
        }
        if (!response.ok) {
            throw Error(payload.error || "Could not load the library.");
        }
        books.value = payload.books;
        page.value = payload.page;
        loadedPage.value = payload.page;
        totalPages.value = payload.totalPages;
        loaded = true;
    } catch (errorCause) {
        if (requests.isCurrent(request) && errorCause.name !== "AbortError") {
            error.value = errorCause.message;
            page.value = loadedPage.value;
        }
    } finally {
        if (requests.isCurrent(request)) {
            loading.value = false;
        }
    }
    if (loaded && scroll) {
        await scrollToCollection(collectionElement.value, () => requests.isCurrent(request));
    }
}

function changeFilters(next) {
    filters.value = next;
    page.value = 1;
    fetchBooks();
}

function changePage(next) {
    page.value = next;
    fetchBooks(true);
}

function changeLimit(next) {
    limit.value = next;
    savePageLimit(next);
    page.value = 1;
    fetchBooks(true);
}

function updateBook({book: updatedBook}) {
    const index = books.value.findIndex(
        (book) =>
            String(book.hardcover_id) === String(updatedBook.hardcover_id),
    );
    if (index >= 0) {
        books.value[index] = {...books.value[index], ...updatedBook};
    }
}

const refreshBooks = () => fetchBooks();
onMounted(() => {
    fetchBooks();
    window.addEventListener("bookshelf:books-synced", refreshBooks);
});
onUnmounted(() => {
    requests.abort();
    window.removeEventListener("bookshelf:books-synced", refreshBooks);
});
</script>
<template>
    <Navbar/>
    <main class="container-xl py-4">
        <h1 class="mb-4">My Library</h1>
        <BookCollectionFilters @change="changeFilters"/>
        <Pagination :disabled="loading"
            :limit="limit"
            :page="page"
            :total-pages="totalPages"
            show-page-size
            @page-change="changePage"
            @limit-change="changeLimit"/>
        <div ref="collectionElement" class="book-collection">
            <BookCollectionContent :books="books"
                :error="error"
                :loading="loading"
                :skeleton-count="limit"
                label="Library books"
                @status-change="updateBook"/>
        </div>
        <Pagination v-if="totalPages > 1 && books.length"
            :disabled="loading"
            :page="page"
            :total-pages="totalPages"
            @page-change="changePage"/>
    </main>
</template>
