<script setup>
import {onMounted, onUnmounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Pagination from "@/components/Pagination.vue";
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";
import {createLatestRequest} from "@/utils/latestRequest";
import {scrollToCollection} from "@/utils/collectionScroll";

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

async function fetchBooks(scroll = false) {
    const request = requests.start();
    let loaded = false;
    loading.value = true;
    error.value = "";
    try {
        const next = filters.value;
        const p = new URLSearchParams(
            {q: next.q, sort: next.sort, page: String(page.value), limit: String(limit.value)});
        if (next.categories?.length) p.set("categories", next.categories.join(","));
        else if (next.genres?.length) p.set("genres", next.genres.join(","));
        const r = await fetch(`/api/books.php?${p}`, {
                signal: request.signal,
            }),
            d = await r.json().catch(() => ({}));
        if (!requests.isCurrent(request)) return;
        if (!r.ok) throw Error(d.error || "Could not load the library.");
        books.value = d.books;
        page.value = d.page;
        loadedPage.value = d.page;
        totalPages.value = d.totalPages;
        loaded = true;
    } catch (e) {
        if (requests.isCurrent(request) && e.name !== "AbortError") {
            error.value = e.message;
            page.value = loadedPage.value;
        }
    } finally {
        if (requests.isCurrent(request)) loading.value = false;
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
    if (index >= 0)
        books.value[index] = {...books.value[index], ...updatedBook};
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
