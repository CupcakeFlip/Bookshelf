<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import Navbar from "@/components/Navbar.vue";
import BookGrid from "@/components/BookGrid.vue";
import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
const books = ref([]),
  loading = ref(true),
  error = ref(""),
  filters = ref({
    q: "",
    genres: [],
    categories: [],
    sort: "recently_updated_desc",
  });
let controller = null;
async function fetchBooks(next = filters.value) {
  filters.value = next;
  controller?.abort();
  controller = new AbortController();
  loading.value = true;
  try {
    const p = new URLSearchParams({ q: next.q, sort: next.sort });
    if (next.categories?.length) p.set("categories", next.categories.join(","));
    else if (next.genres.length) p.set("genres", next.genres.join(","));
    const r = await fetch(`/api/books.php?${p}`, { signal: controller.signal }),
      d = await r.json().catch(() => ({}));
    if (!r.ok) throw Error(d.error || "Could not load the library.");
    books.value = Array.isArray(d) ? d : [];
  } catch (e) {
    if (e.name !== "AbortError") error.value = e.message;
  } finally {
    if (!controller.signal.aborted) loading.value = false;
  }
}
const refreshBooks = () => fetchBooks(filters.value);
onMounted(() => {
  fetchBooks();
  window.addEventListener("bookshelf:books-synced", refreshBooks);
});
onUnmounted(() => {
  controller?.abort();
  window.removeEventListener("bookshelf:books-synced", refreshBooks);
});
</script>
<template>
  <Navbar />
  <main class="container-xl px-4 py-4">
    <h1 class="mb-4">My Library</h1>
    <BookCollectionFilters @change="fetchBooks" />
    <p v-if="loading">Loading books...</p>
    <p v-else-if="error" class="alert alert-danger">{{ error }}</p>
    <BookGrid v-else :books="books" @status-change="refreshBooks" />
  </main>
</template>
