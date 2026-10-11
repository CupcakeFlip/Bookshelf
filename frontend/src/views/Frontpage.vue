<script setup>
import {computed, onMounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

/** @type {import('vue').Ref<string>} */
const query = ref("");
const genre = ref("");

/**
 * @typedef {Object} Book
 * @property {number} id
 * @property {string} title
 * @property {{name: string}[]} authors
 * @property {{name: string}[]} genres
 */

/** @type {import('vue').Ref<Book[]>} */
const books = ref([]);

async function fetchBooks() {
    const response = await fetch("/api/books.php");
    books.value = await response.json();
}

onMounted(() => {
    fetchBooks();
    console.log(books);
});

/** Builds a unique list of genre names from the available books. */
const genres = computed(() => [
        ...new Set(
            books.value.flatMap((book) =>
                (book.genres ?? []).map((genre) => genre.name)
            )
        ),
    ].sort()
);

/** Filters books by the search text and selected genre. */
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
    <!-- Søgning og genrefilter er bundet til reaktive værdier og opdaterer grid'et automatisk. -->
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
        <BookGrid :books="filteredBooks"/>
    </main>
</template>
