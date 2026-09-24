<template>
    <div v-if="books.length" class="row g-3 g-lg-4">
        <div v-for="book in books" :key="book.id" class="col-6 col-md-4 col-lg-3 col-xl-2">
            <BookCard :book="book"
                @open="emit('open',$event)"
                @status-change="emit('status-change',$event)"
                @add-to-list="emit('add-to-list',$event)"/>
        </div>
    </div>
    <p v-else class="text-body-secondary py-5 text-center">No books match your selection.</p></template>
<script setup>
import BookCard from "./BookCard.vue";

defineProps({books: {type: Array, default: () => []}});
const emit = defineEmits(["open", "status-change", "add-to-list"]);
</script>
