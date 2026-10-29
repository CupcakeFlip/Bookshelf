<!--
    Displays saved books for the reading status selected in the route.
    Owns the list filters and pagination and uses shared collection components
    with the books API to load and refresh the matching books.
-->
<script setup>
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
import Navbar from "@/components/Navbar.vue";
import Pagination from "@/components/Pagination.vue";
import {scrollToCollection} from "@/utils/collectionScroll";
import {createLatestRequest} from "@/utils/latestRequest";
import {getSavedPageLimit, savePageLimit} from "@/utils/pagination";
import {computed, onMounted, onUnmounted, ref, watch} from "vue";
import {useRoute} from "vue-router";

const collectionElement = ref(null);

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

/**
 * Fetches one server-filtered page while keeping existing cards visible.
 * Only the latest request can commit results or clear the loading indicator.
 * @param {boolean} [scroll=false] Scroll after a successful page or size change.
 * @returns {Promise<void>} Errors leave the last loaded page visible.
 */
async function fetchBooks(scroll = false) {
    requests.abort();
    if (!title.value) {
        books.value = [];
        loading.value = false;
        return;
    }
    const request = requests.start();
    let loaded = false;
    loading.value = true;
    error.value = "";
    try {
        const next = filters.value;
        const params = new URLSearchParams({
            status: status.value,
            q: next.q,
            sort: next.sort,
            page: String(page.value),
            limit: String(limit.value),
        });
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
            throw Error(payload.error || "Could not load this list.");
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
            <div ref="collectionElement" class="book-collection">
                <BookCollectionContent :books="books"
                    :error="error"
                    :loading="loading"
                    :skeleton-count="limit"
                    label="Reading list books"
                    @status-change="refreshBooks"/>
            </div>
            <Pagination v-if="totalPages > 1 && books.length"
                :disabled="loading"
                :page="page"
                :total-pages="totalPages"
                @page-change="changePage"/>
        </template>
    </main>
</template>
