<script setup>
import Navbar from "@/components/Navbar.vue";
import BookListRow from "@/components/BookListRow.vue";

const lists = [
    {
        id: 1,
        title: "To read next",
        recentBooks: [
            {id: 1, title: "Plier", cover: "https://placehold.co/120x180?text=Plier"},
            {id: 2, title: "The Gravewood", cover: "https://placehold.co/120x180?text=Gravewood"},
            {id: 3, title: "The Long Way Home", cover: "https://placehold.co/120x180?text=Home"}
        ]
    },
    {
        id: 2,
        title: "Favourite fantasy",
        recentBooks: [
            {id: 4, title: "Moonfall", cover: "https://placehold.co/120x180?text=Moonfall"},
            {id: 5, title: "Wildwood", cover: "https://placehold.co/120x180?text=Wildwood"}
        ]
    }
];
const openList = list => console.log(list);
</script>
<template>
    <Navbar/>
    <main class="container py-4"><h1 class="mb-4">Lists</h1>
        <div class="d-grid gap-3">
            <BookListRow v-for="list in lists" :key="list.id" :list="list" @open="openList"/>
        </div>
    </main>
</template>
