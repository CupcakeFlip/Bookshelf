<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Pagination from "@/components/Pagination.vue";

const route = useRoute(),
    names = {
        want_to_read: "Want to Read",
        reading: "Currently Reading",
        finished: "Finished",
        paused: "Paused",
        dnf: "Did Not Finish",
    },
    status = computed(() => String(route.params.status ?? "")),
    title = computed(() => names[status.value] ?? ""),
    books = ref([]),
    loading = ref(true),
    error = ref(""),
    page = ref(1),
    limit = ref(24),
    totalPages = ref(0),
    filters = ref({
        q: "",
        genres: [],
        categories: [],
        sort: "recently_updated_desc",
    });
let controller = null;

async function fetchBooks() {
    controller?.abort();
    if (!title.value) {
        controller = null;
        books.value = [];
        loading.value = false;
        return;
    }
    const request = new AbortController();
    controller = request;
    loading.value = true;
    error.value = "";
    try {
        const next = filters.value;
        const p = new URLSearchParams({
            status: status.value,
            q: next.q,
            sort: next.sort,
            page: String(page.value),
            limit: String(limit.value),
        });
        if (next.categories?.length)
            p.set("categories", next.categories.join(","));
        else if (next.genres?.length) p.set("genres", next.genres.join(","));
        const r = await fetch(`/api/books.php?${p}`, {
                signal: request.signal,
            }),
            d = await r.json().catch(() => ({}));
        if (controller !== request || request.signal.aborted) return;
        if (!r.ok) throw Error(d.error || "Could not load this list.");
        books.value = d.books;
        page.value = d.page;
        totalPages.value = d.totalPages;
    } catch (e) {
        if (controller === request && e.name !== "AbortError") error.value = e.message;
    } finally {
        if (controller === request) loading.value = false;
    }
}

function changeFilters(next) { filters.value = next; page.value = 1; fetchBooks(); }
function changePage(next) { page.value = next; fetchBooks(); }
function changeLimit(next) { limit.value = next; page.value = 1; fetchBooks(); }
const refreshBooks = () => fetchBooks();
watch(status, () => {
    page.value = 1;
    filters.value = {
        q: "",
        genres: [],
        categories: [],
        sort: "recently_updated_desc",
    };
    fetchBooks();
});
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
    <Navbar />
    <main class="container py-4">
        <h1 v-if="title" class="mb-4">{{ title }}</h1>
        <p v-if="!title" class="alert alert-danger">
            This reading list does not exist.
        </p>
        <template v-else>
            <BookCollectionFilters :status="status" @change="changeFilters" />
            <Pagination :page="page" :total-pages="totalPages" :limit="limit" show-page-size @page-change="changePage" @limit-change="changeLimit" />
            <p v-if="loading">Loading books...</p>
            <p v-else-if="error" class="alert alert-danger">{{ error }}</p>
            <BookGrid v-else :books="books" @status-change="refreshBooks" />
            <Pagination v-if="!loading && !error && totalPages > 1" :page="page" :total-pages="totalPages" @page-change="changePage" />
        </template>
    </main>
</template>
