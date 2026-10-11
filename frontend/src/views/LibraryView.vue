<script setup>
import { onMounted, ref } from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

// Temporary library data used until this view reads from the backend.
/*
const books = [
    {
        id: 1,
        title: "Plier",
        authors: ["Jane Washington"],
        cover: "https://placehold.co/300x450?text=Plier",
        status: "reading",
        format: "Paperback",
    },
    {
        id: 2,
        title: "The Gravewood",
        authors: ["A. N. Author"],
        cover: "https://placehold.co/300x450?text=Gravewood",
        status: "want-to-read",
        format: "E-book",
    },
];
*/

const books = ref([]);

async function fetchBooks() {
    const response = await fetch("/api/books.php");
    const data = await response.json();
    books.value = data;
}

onMounted(() => {
    fetchBooks();
    console.log(books);
});
</script>
<template>
    <!-- BookGrid står for både layout og visning af den tomme tilstand. -->
    <Navbar />
    <main class="container py-4">
        <h1 class="mb-4">My Library</h1>
        <BookGrid :books="books" />
    </main>
</template>
