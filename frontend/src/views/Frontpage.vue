<script setup>
import {computed, onMounted, onUnmounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";
import {searchHardcoverBooks, searchLocalBooks} from "@/api/books";

const query = ref("");
const genre = ref("");
const books = ref([]);
const popularBooks = ref([]);
const loading = ref(false);
const message = ref("");
const partialError = ref("");
let searchNumber = 0;

/** Combines normalized books, preferring the locally saved copy on duplicate IDs. */
function mergeBooks(localBooks, hardcoverBooks) {
    const merged = new Map(localBooks.map(book => [String(book.hardcover_id), book]));
    for (const book of hardcoverBooks) {
        const id = String(book.hardcover_id ?? "");
        if (id && !merged.has(id)) merged.set(id, book);
    }
    return [...merged.values()];
}

/** Updates the visible book after BookCard persists a status change. */
function updateBook({book}) {
    const id = String(book.hardcover_id);
    const replace = list => list.map(item => String(item.hardcover_id) === id ? book : item);
    books.value = replace(books.value);
    popularBooks.value = replace(popularBooks.value);
}

async function fetchPopularBooks() {
    try {
        const response = await fetch("/api/popular-books.php");
        if (!response.ok) throw new Error("Could not load popular books.");
        popularBooks.value = await response.json();
        books.value = popularBooks.value;
        message.value = "";
    } catch (error) {
        message.value = error.message;
    }
}

/** Searches both sources and ignores responses from an older submitted query. */
async function searchBooks() {
    const term = query.value.trim();
    if (!term) return restorePopularBooks();
    const requestNumber = ++searchNumber;
    loading.value = true;
    message.value = "";
    partialError.value = "";
    const [local, hardcover] = await Promise.allSettled([searchLocalBooks(term), searchHardcoverBooks(term)]);
    if (requestNumber !== searchNumber) return;
    const localBooks = local.status === "fulfilled" ? local.value : [];
    const hardcoverBooks = hardcover.status === "fulfilled" ? hardcover.value : [];
    books.value = mergeBooks(localBooks, hardcoverBooks);
    if (local.status === "rejected" && hardcover.status === "rejected") message.value = "Could not search the local library or Hardcover.";
    else if (local.status === "rejected") partialError.value = "Local library results could not be loaded.";
    else if (hardcover.status === "rejected") partialError.value = "Hardcover results could not be loaded.";
    loading.value = false;
}

function restorePopularBooks() {
    ++searchNumber;
    loading.value = false;
    query.value = "";
    books.value = popularBooks.value;
    message.value = "";
    partialError.value = "";
}

function handleInput() {
    if (!query.value.trim()) restorePopularBooks();
}

onMounted(() => {
    fetchPopularBooks();
    window.addEventListener("bookshelf:books-synced", fetchPopularBooks);
});
onUnmounted(() => window.removeEventListener("bookshelf:books-synced", fetchPopularBooks));

const genres = computed(() => [...new Set(books.value.flatMap(book => (book.genres ?? []).map(item => item?.name).filter(Boolean)))].sort());
const filteredBooks = computed(() => books.value.filter(book => !genre.value || (book.genres ?? []).some(item => item?.name === genre.value)));
</script>

<template>
    <Navbar/>
    <main class="container py-4">
        <div class="mb-4">
            <h1 class="mb-3">Catalogue</h1>
            <form class="row g-3" @submit.prevent="searchBooks">
                <div class="col-md-8">
                    <label class="visually-hidden" for="catalogue-search">Search books</label>
                    <input id="catalogue-search" v-model="query" class="form-control" placeholder="Search by title or author" type="search" @input="handleInput"/>
                </div>
                <div class="col-md-4">
                    <label class="visually-hidden" for="genre-filter">Filter by genre</label>
                    <select id="genre-filter" v-model="genre" class="form-select">
                        <option value="">All genres</option>
                        <option v-for="item in genres" :key="item" :value="item">{{ item }}</option>
                    </select>
                </div>
            </form>
        </div>
        <p v-if="loading" class="text-body-secondary py-3 text-center" aria-live="polite">Searching books...</p>
        <div v-else>
            <p v-if="partialError" class="alert alert-warning" role="status">{{ partialError }}</p>
            <p v-if="message" class="alert alert-danger" role="alert">{{ message }}</p>
            <p v-else-if="query.trim() && !filteredBooks.length" class="text-body-secondary py-5 text-center">No books match your search.</p>
            <BookGrid v-else :books="filteredBooks" @status-change="updateBook"/>
        </div>
    </main>
</template>

<style scoped></style>
