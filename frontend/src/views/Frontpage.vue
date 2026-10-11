<script setup>
import {computed, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

const query = ref("");
const genre = ref("");

// Temporary catalogue data until the page is connected to the backend or a store.
const books = [
    {
        id: 1,
        title: "Plier",
        authors: ["Jane Washington"],
        series: {name: "Ironside Academy", position: 5},
        cover_url: "https://m.media-amazon.com/images/S/compressed.photo.goodreads.com/books/1732736809i/222024155.jpg",
        format: "Audiobook",
        status: "reading",
        genres: ["Fantasy", "Romance"]
    },
    {
        id: 2,
        title: "The Gravewood",
        authors: ["A. N. Author"],
        cover_url: "https://placehold.co/300x450?text=Gravewood",
        format: "E-book",
        status: "want-to-read",
        genres: ["Mystery", "Romance"]
    },
    {
        id: 3,
        title: "The Long Way Home",
        authors: ["R. North"],
        cover_url: "https://placehold.co/300x450?text=Home",
        format: "Hardcover",
        status: "finished",
        genres: ["Fantasy"]
    }
];

/** Builds the genre filter options from the available books. */
const genres = computed(() => [...new Set(books.flatMap(book => book.genres ?? []))]);

/** Filters books by the search text and selected genre. */
const filteredBooks = computed(() => books.filter(
    book => (!query.value || book.title.toLowerCase().includes(query.value.toLowerCase()) ||
            book.authors.some(author => author.toLowerCase().includes(query.value.toLowerCase()))) &&
        (!genre.value || book.genres?.includes(genre.value))));
</script>
<template>
    <!-- Søgning og genrefilter er bundet til reaktive værdier og opdaterer grid'et automatisk. -->
    <Navbar/>
    <main class="container py-4">
        <div class="mb-4"><h1 class="mb-3">Catalogue</h1>
            <div class="row g-3">
                <div class="col-md-8"><label class="visually-hidden" for="catalogue-search">Search books</label><input
                    id="catalogue-search"
                    v-model="query"
                    class="form-control"
                    placeholder="Search by title or author"
                    type="search"></div>
                <div class="col-md-4"><label class="visually-hidden" for="genre-filter">Filter by genre</label><select
                    id="genre-filter"
                    v-model="genre"
                    class="form-select">
                    <option value="">All genres</option>
                    <option v-for="item in genres" :key="item">{{ item }}</option>
                </select></div>
            </div>
        </div>
        <BookGrid :books="filteredBooks"/>
    </main>
</template>
