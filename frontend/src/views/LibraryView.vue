<script setup>
import {onMounted, onUnmounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Pagination from "@/components/Pagination.vue";
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";

const books = ref([]),
    loading = ref(true),
    error = ref(""),
    page = ref(1),
    limit = ref(getSavedPageLimit()), totalPages = ref(0),
    filters = ref({
        q: "",
        genres: [],
        categories: [],
        sort: "recently_updated_desc",
    });
let controller = null;

async function fetchBooks() {
    controller?.abort();
    const request = new AbortController();
    controller = request;
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
        if (controller !== request || request.signal.aborted) return;
        if (!r.ok) throw Error(d.error || "Could not load the library.");
        books.value = d.books;
        page.value = d.page;
        totalPages.value = d.totalPages;
    } catch (e) {
        if (controller === request && e.name !== "AbortError") error.value = e.message;
    } finally {
        if (controller === request) loading.value = false;
    }
}

function changeFilters(next) {
    filters.value = next;
    page.value = 1;
    fetchBooks();
}

function changePage(next) {
    page.value = next;
    fetchBooks();
}

function changeLimit(next) {
    limit.value = next;
    savePageLimit(next);
    page.value = 1;
    fetchBooks();
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
    controller?.abort();
    window.removeEventListener("bookshelf:books-synced", refreshBooks);
});
</script>
<template>
    <Navbar/>
    <main class="container-xl px-4 py-4">
        <h1 class="mb-4">My Library</h1>
        <BookCollectionFilters @change="changeFilters"/>
        <Pagination :limit="limit"
            :page="page"
            :total-pages="totalPages"
            show-page-size
            @page-change="changePage"
            @limit-change="changeLimit"/>
        <p v-if="loading">Loading books...</p>
        <p v-else-if="error" class="alert alert-danger">{{ error }}</p>
        <BookGrid v-else :books="books" @status-change="updateBook"/>
        <Pagination v-if="!loading && !error && totalPages > 1"
            :page="page"
            :total-pages="totalPages"
            @page-change="changePage"/>
    </main>
</template>
