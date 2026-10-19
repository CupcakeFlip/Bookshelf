<script setup>
import {onMounted, onUnmounted, ref} from "vue";
import {useRoute} from "vue-router";
import Navbar from "@/components/Navbar.vue";
import BookStatusDropdown from "@/components/BookStatusDropdown.vue";

const route = useRoute();

const book = ref(null);
const loading = ref(true);
const error = ref("");

async function fetchBook() {
  try {
    const response = await fetch(
      `/api/book-details.php?hardcover_id=${route.params.hardcoverId}`,
    );

    if (!response.ok) {
      throw new Error("Could not load book details.");
    }

    book.value = await response.json();
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function handleStatusChange({book: updatedBook, status}) {
  book.value = {
    ...book.value,
    ...updatedBook,
    status,
  };
}

onMounted(() => {
  fetchBook();
  window.addEventListener("bookshelf:books-synced", fetchBook);

});
onUnmounted(() => window.removeEventListener("bookshelf:books-synced", fetchBook));
</script>

<template>
  <Navbar/>

  <main class="container-xl py-4 book-details">
    <div class="wrapper">
      <section v-if="loading">
        <p>Loading book...</p>
      </section>

      <section v-else-if="error" class="alert alert-danger">
        {{ error }}
      </section>
      <!-- Book information -->

      <div v-else-if="book" class="row g-4">
        <section class="col-12 col-lg-8 book-details__information">
          <div class="row g-3 align-items-start">
            <!-- Book cover -->
            <div class="col-4 col-sm-3">
              <img
                v-if="book.cover_url"
                :alt="`Cover of ${book.title}`"
                :src="book.cover_url"
                class="rounded book-details__cover img-fluid"
              />
            </div>
            <!-- Book metadata -->
            <div class="col-8 col-sm-9">
              <div class="d-flex flex-column gap-2">
                <p
                  v-if="book.series"
                  class="mb-0 fst-italic book-details__series"
                >
                  {{ book.series.name }} #{{ parseFloat(book.series.position) }}
                </p>
                <h1 class="mb-0 book-details__title">{{ book.title }}</h1>
                <p class="mb-0 book-details__authors lead">
                  {{ book.authors.map((author) => author.name).join(", ") }}
                </p>
                <div class="d-flex flex-wrap gap-2 book-details__genres">
                  <span
                    v-for="genre in book.genres"
                    :key="genre.slug"
                    class="badge rounded-pill text-bg-primary"
                  >
                    {{ genre.name }}
                  </span>
                </div>
                <p class="mb-0 text-body-secondary book-details__release small">
                  {{ book.release_date }}
                </p>
              </div>
            </div>
          </div>
          <hr class="my-4"/>
          <!-- Description -->
          <article class="book-details__description">
            <h2 class="h5">Description</h2>
            <p>
              {{ book.description }}
            </p>
          </article>
        </section>
        <!-- Personal book management -->
        <aside class="col-12 col-lg-4 book-details__management">
          <div class="d-flex flex-column gap-3">
            <!-- Tags -->
            <section class="card book-details__tags">
              <div class="card-body">
                <h2 class="card-title h6">Tags</h2>
                <div class="d-flex flex-wrap gap-2">
                  <span
                    v-for="tag in book.tags ?? []"
                    :key="tag.slug"
                    class="badge rounded-pill text-bg-primary"
                  >
                    {{ tag.name }}
                  </span>
                  <button class="badge rounded-pill text-bg-primary border-0">
                    <i class="bi bi-plus-lg"></i>
                  </button>
                </div>
              </div>
            </section>
            <!-- Owned formats -->
            <section class="card book-details__formats">
              <div class="card-body">
                <h2 class="card-title h6">Format</h2>
                <div class="d-flex flex-wrap gap-2">
                  <button class="btn btn-sm btn-outline-primary" type="button">
                    Physical
                  </button>
                  <button class="btn btn-sm btn-outline-primary" type="button">
                    E-book
                  </button>
                  <button class="btn btn-sm btn-outline-primary" type="button">
                    Audiobook
                  </button>
                </div>
              </div>
            </section>
            <!-- Reading status -->
            <section class="card book-details__status">
              <div class="card-body">
                <h2 class="card-title h6">Status</h2>
                <BookStatusDropdown
                  :book="book"
                  @status-change="handleStatusChange"
                />
              </div>
            </section>
            <!-- Reading history -->
            <section class="card book-details__history">
              <div class="card-body">
                <h2 class="card-title h6">Reading History</h2>
              </div>
            </section>
            <!-- Lists -->
            <section class="card book-details__lists">
              <div class="card-body">
                <h2 class="card-title h6">Lists</h2>
              </div>
            </section>
          </div>
        </aside>
      </div>
    </div>
  </main>
</template>

<style scoped></style>
