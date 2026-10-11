<script setup>
import {computed, onMounted, onUnmounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

/** @type {import('vue').Ref<string>} Search text entered by the user. */
const query = ref("");
/** @type {import('vue').Ref<string>} Selected genre filter, or an empty string. */
const genre = ref("");

/**
 * @typedef {Object} Book
 * @property {number} id
 * @property {string} title
 * @property {{name: string}[]} authors
 * @property {{name: string, slug?: string}[]} genres
 * @property {string|null} [status]
 */

/** @type {import('vue').Ref<Book[]>} */
const books = ref([]);

/**
 * Replaces a locally displayed book after BookCard reports a persisted status.
 *
 * @param {{book: Book}} change Status-change event emitted by BookGrid.
 * @returns {void}
 */
function updateBook({book}) {
    const index = books.value.findIndex(item => item.hardcover_id === book.hardcover_id);
    if (index >= 0) books.value[index] = book;
}


/**
 * TODO: Make pagination work by fetching only a limited number of books at a time and updating the `books` ref accordingly.
 */


/**
 * Loads the current popular catalogue from the PHP endpoint.
 *
 * @returns {Promise<void>}
 */
async function fetchBooks() {
    const response = await fetch("/api/popular-books.php");
    books.value = await response.json();
}

onMounted(() => {
    fetchBooks();
    window.addEventListener("bookshelf:books-synced", fetchBooks);
    console.log(books);
});
onUnmounted(() => window.removeEventListener("bookshelf:books-synced", fetchBooks));

/** Builds a unique, sorted list of genre names from the available books. */
const genres = computed(() => [
        ...new Set(
            books.value.flatMap((book) =>
                (book.genres ?? []).map((genre) => genre.name)
            )
        ),
    ].sort()
);

/** Filters books by the search text and selected genre before rendering BookGrid. */
const filteredBooks = computed(() =>
    books.value.filter(
        (book) =>
            (!query.value ||
                book.title.toLowerCase().includes(query.value.toLowerCase()) ||
                book.authors.some((author) =>
                    author.name.toLowerCase().includes(query.value.toLowerCase()),
                )) &&
            (!genre.value ||
                book.genres?.some((item) => item.name === genre.value)),
    ),
);
</script>
<template>
    <!-- Reactive filters update the same normalized books that BookGrid passes to BookCard. -->
    <Navbar/>
    <main class="container py-4">
        <div class="mb-4">
            <h1 class="mb-3">Catalogue</h1>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="visually-hidden" for="catalogue-search"
                    >Search books</label
                    ><input
                    id="catalogue-search"
                    v-model="query"
                    class="form-control"
                    placeholder="Search by title or author"
                    type="search"
                />
                </div>
                <div class="col-md-4">
                    <label class="visually-hidden" for="genre-filter"
                    >Filter by genre</label
                    ><select
                    id="genre-filter"
                    v-model="genre"
                    class="form-select"
                >
                    <option value="">All genres</option>
                    <option v-for="item in genres" :key="item">
                        {{ item }}
                    </option>
                </select>
                </div>
            </div>
        </div>
        <BookGrid :books="filteredBooks" @status-change="updateBook"/>
    </main>
</template>

<style scoped></style>
