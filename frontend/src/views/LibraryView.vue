<script setup>
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

// Eksempeldata for bøgerne i brugerens personlige bibliotek.
const books = [
    {
        id: 1,
        title: "Plier",
        authors: ["Jane Washington"],
        cover: "https://placehold.co/300x450?text=Plier",
        status: "reading",
        format: "Paperback"
    },
    {
        id: 2,
        title: "The Gravewood",
        authors: ["A. N. Author"],
        cover: "https://placehold.co/300x450?text=Gravewood",
        status: "want-to-read",
        format: "E-book"
    }
];
</script>
<template>
    <!-- BookGrid står for både layout og visning af den tomme tilstand. -->
    <Navbar/>
    <main class="container py-4"><h1 class="mb-4">My Library</h1>
        <BookGrid :books="books"/>
    </main>
</template>
