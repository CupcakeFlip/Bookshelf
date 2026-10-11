<script setup>
import {onMounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

const books = ref([]);
function updateBook({book, status}) { if (status === null) books.value = books.value.filter(item => item.hardcover_id !== book.hardcover_id); else { const index = books.value.findIndex(item => item.hardcover_id === book.hardcover_id); if (index >= 0) books.value[index] = book; } }

async function fetchBooks() {
    const response = await fetch("/api/books.php");
    books.value = await response.json();
}

onMounted(() => {
    fetchBooks();
    console.log(books);
});
</script>
<template>
    <!-- BookGrid står for både layout og visning af den tomme tilstand. -->
    <Navbar/>
    <main class="container py-4">
        <h1 class="mb-4">My Library</h1>
        <BookGrid :books="books" @status-change="updateBook"/>
    </main>
</template>
