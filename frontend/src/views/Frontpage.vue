<!-- Made with AI -->

<!--
    Displays the catalogue with trending Hardcover books and submitted searches.
    Merges local and external matches, preferring saved copies, and filters the
    visible results by genre while keeping trending pagination in this view.
-->
<script setup>
import {searchHardcoverBooks, searchLocalBooks} from "@/api/books";
import BookCollectionContent from "@/components/BookCollectionContent.vue";
import Navbar from "@/components/Navbar.vue";
import Pagination from "@/components/Pagination.vue";
import {filterBooksByGenre, getGenreOptions} from "@/utils/bookFormatting";
import {scrollToCollection} from "@/utils/collectionScroll";
import {computed, onMounted, onUnmounted, ref} from "vue";

const collectionElement = ref(null);

const query = ref("");
const genre = ref("");
const books = ref([]);
const popularBooks = ref([]);
const loading = ref(false);
const message = ref("");
const partialError = ref("");
const popularPage = ref(1);
const popularHasNext = ref(false);
const showingPopular = ref(true);
// Incremented for each submitted search so a slower earlier response cannot
// overwrite results from the query currently shown in the input.
let searchNumber = 0;

/**
 * Merges sources by Hardcover ID, keeping the locally saved copy on duplicates.
 * @param {Object[]} localBooks Saved matching books.
 * @param {Object[]} hardcoverBooks External matches.
 * @returns {Object[]} Combined results.
 */
function mergeBooks(localBooks, hardcoverBooks) {
    const merged = new Map(
        localBooks.map((book) => [String(book.hardcover_id), book]),
    );
    for (const book of hardcoverBooks) {
        const id = String(book.hardcover_id ?? "");
        if (id && !merged.has(id)) {
            merged.set(id, book);
        }
    }
    return [...merged.values()];
}

/** Updates the visible book after BookCard persists a status change. */
function updateBook({book}) {
    const id = String(book.hardcover_id);
    const replace = (list) =>
        list.map((item) => (String(item.hardcover_id) === id ? book : item));
    books.value = replace(books.value);
    popularBooks.value = replace(popularBooks.value);
}

/**
 * Loads a trending page and ignores responses superseded by another search.
 * @param {number} [page] Requested page, defaulting to the current popular page.
 * @param {boolean} [scroll=false] Scroll after successfully replacing results.
 * @returns {Promise<void>}
 */
async function fetchPopularBooks(page = popularPage.value, scroll = false) {
    const requestNumber = ++searchNumber;
    let loaded = false;
    const previousPage = popularPage.value;
    showingPopular.value = true;
    loading.value = true;
    message.value = "";
    try {
        const response = await fetch(`/api/popular-books.php?page=${page}&limit=24`);
        if (!response.ok) {
            throw new Error("Could not load popular books.");
        }
        const payload = await response.json();
        if (requestNumber !== searchNumber) {
            return;
        }
        popularBooks.value = payload.books;
        popularPage.value = payload.page;
        popularHasNext.value = payload.hasNext;
        books.value = popularBooks.value;
        message.value = "";
        loaded = true;
    } catch (error) {
        if (requestNumber === searchNumber) {
            popularPage.value = previousPage;
            message.value = error.message;
        }
    } finally {
        if (requestNumber === searchNumber) {
            loading.value = false;
        }
    }
    if (loaded && scroll) {
        await scrollToCollection(collectionElement.value, () => requestNumber === searchNumber);
    }
}

function changePopularPage(page) {
    fetchPopularBooks(page, true);
}

/** Searches both sources and ignores responses from an older submitted query. */
async function searchBooks() {
    const term = query.value.trim();
    if (!term) {
        return restorePopularBooks();
    }
    const requestNumber = ++searchNumber;
    showingPopular.value = false;
    loading.value = true;
    message.value = "";
    partialError.value = "";
    // A partial result is useful, so the two independent requests are settled
    // separately instead of failing the whole search when one source errors.
    const [local, hardcover] = await Promise.allSettled([
        searchLocalBooks(term),
        searchHardcoverBooks(term),
    ]);
    if (requestNumber !== searchNumber) {
        return;
    }
    const localBooks = local.status === "fulfilled" ? local.value : [];
    const hardcoverBooks =
        hardcover.status === "fulfilled" ? hardcover.value : [];
    if (local.status === "fulfilled" || hardcover.status === "fulfilled") {
        books.value = mergeBooks(localBooks, hardcoverBooks);
    }
    if (local.status === "rejected" && hardcover.status === "rejected") {
        message.value = "Could not search the local library or Hardcover.";
    } else if (local.status === "rejected") {
        partialError.value = "Local library results could not be loaded.";
    } else if (hardcover.status === "rejected") {
        partialError.value = "Hardcover results could not be loaded.";
    }
    loading.value = false;
}

function restorePopularBooks() {
    ++searchNumber;
    query.value = "";
    message.value = "";
    partialError.value = "";
    popularPage.value = 1;
    fetchPopularBooks(1);
}

function handleInput() {
    if (!query.value.trim()) {
        restorePopularBooks();
    }
}

onMounted(() => {
    fetchPopularBooks();
    window.addEventListener("bookshelf:books-synced", refreshPopularBooks);
});
const refreshPopularBooks = () => {
    if (showingPopular.value) {
        fetchPopularBooks();
    }
};
onUnmounted(() => {
    ++searchNumber;
    window.removeEventListener("bookshelf:books-synced", refreshPopularBooks);
});

const genreOptions = computed(() => getGenreOptions(books.value));
const filteredBooks = computed(() =>
    filterBooksByGenre(books.value, genre.value),
);
</script>

<template>
    <Navbar/>

    <main class="container-xl py-4">
        <div class="mb-4">
            <h1 class="mb-3">Catalogue</h1>

            <form class="row g-3" @submit.prevent="searchBooks">
                <div class="col-md-8">
                    <label
                        class="visually-hidden"
                        for="catalogue-search"
                    >
                        Search books
                    </label>

                    <input
                        id="catalogue-search"
                        v-model="query"
                        class="form-control"
                        placeholder="Search by title or author"
                        type="search"
                        @input="handleInput"
                    />
                </div>

                <div class="col-md-4">
                    <label
                        class="visually-hidden"
                        for="genre-filter"
                    >
                        Filter by genre
                    </label>

                    <select
                        id="genre-filter"
                        v-model="genre"
                        class="form-select"
                    >
                        <option value="">All genres</option>

                        <optgroup label="Standard genres">
                            <option
                                v-for="item in genreOptions.standard"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </optgroup>

                        <optgroup
                            v-if="genreOptions.additional.length"
                            label="Other genres"
                        >
                            <option
                                v-for="item in genreOptions.additional"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </optgroup>
                    </select>
                </div>
            </form>
        </div>

        <div
            ref="collectionElement"
            class="book-collection"
        >
            <BookCollectionContent
                :books="filteredBooks"
                :empty-message="
                    query.trim()
                        ? 'No books match your search.'
                        : ''
                "
                :error="message"
                :loading="loading"
                :loading-label="
                    showingPopular
                        ? 'Loading popular books…'
                        : 'Searching books…'
                "
                :skeleton-count="24"
                :source-book-count="books.length"
                :warning="partialError"
                label="Catalogue books"
                @status-change="updateBook"
            />

            <Pagination
                v-if="showingPopular && (books.length || !loading)"
                :disabled="loading"
                :has-next="popularHasNext"
                :page="popularPage"
                @page-change="changePopularPage"
            />
        </div>
    </main>
</template>
