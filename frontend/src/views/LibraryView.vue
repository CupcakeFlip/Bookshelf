<script setup>
import {onMounted, onUnmounted, ref} from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";

const books = ref([]);

/**
 * Applies a status-change event after BookCard has persisted it through PHP.
 *
 * @param {{book: Object, status: string|null}} change Updated book and status.
 * @returns {void}
 */
function updateBook({book}) {
  const index = books.value.findIndex(
    item => String(item.hardcover_id) === String(book.hardcover_id)
  );

  if (index >= 0) {
    books.value[index] = book;
  }
}

/** Fetches all books that have a row in the local user_books table. */
async function fetchBooks() {
  const response = await fetch("/api/books.php");
  books.value = await response.json();
}

onMounted(() => {
  fetchBooks();
  window.addEventListener("bookshelf:books-synced", fetchBooks);

});
onUnmounted(() => window.removeEventListener("bookshelf:books-synced", fetchBooks));
</script>
<template>
  <!-- BookGrid owns layout, empty-state rendering, and status event forwarding. -->
  <Navbar/>
  <main class="container py-4">
    <h1 class="mb-4">My Library</h1>
    <BookGrid :books="books" @status-change="updateBook"/>
  </main>
</template>

<style scoped></style>
