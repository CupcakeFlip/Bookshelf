<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from "vue";
import {useRoute} from "vue-router";
import Navbar from "@/components/Navbar.vue";
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Pagination from "@/components/Pagination.vue";
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";
import {createLatestRequest} from "@/utils/latestRequest";

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
    loadedPage = ref(1),
    limit = ref(getSavedPageLimit()),
    totalPages = ref(0),
    filters = ref({
        q: "",
        genres: [],
        categories: [],
        sort: "recently_updated_desc",
    });
const requests = createLatestRequest();

async function fetchBooks() {
    requests.abort();
    if (!title.value) {
        books.value = [];
        loading.value = false;
        return;
    }
    const request = requests.start();
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
        if (!requests.isCurrent(request)) return;
        if (!r.ok) throw Error(d.error || "Could not load this list.");
        books.value = d.books;
        page.value = d.page;
        loadedPage.value = d.page;
        totalPages.value = d.totalPages;
    } catch (e) {
        if (requests.isCurrent(request) && e.name !== "AbortError") {
            error.value = e.message;
            page.value = loadedPage.value;
        }
    } finally {
        if (requests.isCurrent(request)) loading.value = false;
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

const refreshBooks = () => fetchBooks();
watch(status, () => {
    page.value = 1;
    loadedPage.value = 1;
    books.value = [];
    totalPages.value = 0;
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
    requests.abort();
    window.removeEventListener("bookshelf:books-synced", refreshBooks);
});
</script>
<template>
    <Navbar/>
    <main class="container-xl py-4">
        <h1 v-if="title" class="mb-4">{{ title }}</h1>
        <p v-if="!title" class="alert alert-danger">
            This reading list does not exist.
        </p>
        <template v-else>
            <BookCollectionFilters :status="status" @change="changeFilters"/>
            <Pagination :disabled="loading"
                :limit="limit"
                :page="page"
                :total-pages="totalPages"
                show-page-size
                @page-change="changePage"
                @limit-change="changeLimit"/>
            <BookCollectionContent :books="books"
                :error="error"
                :loading="loading"
                :skeleton-count="limit"
                label="Reading list books"
                @status-change="refreshBooks"/>
            <Pagination v-if="totalPages > 1 && books.length"
                :disabled="loading"
                :page="page"
                :total-pages="totalPages"
                @page-change="changePage"/>
        </template>
    </main>
</template>
