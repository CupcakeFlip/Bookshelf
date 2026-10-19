<script setup>
import BookCard from "./BookCard.vue";

/** Books displayed as cards; an empty array produces the empty-state message. */
defineProps({ books: { type: Array, default: () => [] } });

/** Card events are forwarded so the page view remains the state owner. */
const emit = defineEmits(["open", "status-change", "add-to-list"]);
</script>

<template>
  <!-- Render cards in a responsive grid, or explain that the current filter is empty. -->
  <div v-if="books.length" class="row g-3 g-lg-4">
    <div
      v-for="book in books"
      :key="book.id"
      class="col-6 col-md-4 col-lg-3 col-xl-3"
    >
      <BookCard
        :book="book"
        @open="emit('open', $event)"
        @status-change="emit('status-change', $event)"
        @add-to-list="emit('add-to-list', $event)"
      />
    </div>
  </div>
  <p v-else class="py-5 text-body-secondary text-center">
    No books match your selection.
  </p>
</template>

<style scoped></style>
