<script setup>
import Navbar from "@/components/Navbar.vue";
</script>

<template>
  <Navbar />

  <main class="container-xl py-4 book-details">
    <div class="row g-4">
      <!-- Book information -->
      <section class="col-12 col-lg-8 book-details__information">
        <div class="row g-3 align-items-start">
          <!-- Book cover -->
          <div class="col-4 col-sm-3">
            <img
              src="#"
              alt="Book cover"
              class="rounded book-details__cover img-fluid"
            />
          </div>

          <!-- Book metadata -->
          <div class="col-8 col-sm-9">
            <div class="d-flex flex-column gap-2">
              <p class="mb-0 fst-italic book-details__series">
                Series and number
              </p>

              <h1 class="mb-0 book-details__title">Book title</h1>

              <p class="mb-0 book-details__authors lead">Author</p>

              <div class="d-flex flex-wrap gap-2 book-details__genres">
                <span class="badge rounded-pill text-bg-primary">
                  Genre 1
                </span>
                <span class="badge rounded-pill text-bg-secondary">
                  Genre 2
                </span>
              </div>

              <p class="mb-0 text-body-secondary book-details__release small">
                Came out x years ago
              </p>
            </div>
          </div>
        </div>

        <hr class="my-4" />

        <!-- Description -->
        <article class="book-details__description">
          <h2 class="h5">Description</h2>
          <p>
            Book description. Lorem ipsum dolor sit amet, consectetur
            adipisicing elit.
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
                <span class="badge rounded-pill text-bg-primary"> Cozy </span>
                <span class="badge rounded-pill text-bg-primary"> Cute </span>
                <span class="badge rounded-pill text-bg-primary">
                  Found Family
                </span>
              </div>
            </div>
          </section>

          <!-- Owned formats -->
          <section class="card book-details__formats">
            <div class="card-body">
              <h2 class="card-title h6">Format</h2>

              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary">
                  Physical
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary">
                  E-book
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary">
                  Audiobook
                </button>
              </div>
            </div>
          </section>

          <!-- Reading status -->
          <section class="card book-details__status">
            <div class="card-body">
              <h2 class="card-title h6">Status</h2>

              <div ref="dropdownElement" class="dropdown" @click.stop>
                <div class="btn-group w-100" role="group">
                  <button
                    :class="statusButtonClass"
                    :disabled="pending"
                    class="btn btn-sm"
                    type="button"
                    @click="mainStatusAction"
                  >
                    {{ statusLabel }}
                  </button>

                  <button
                    :aria-expanded="isMenuOpen"
                    :class="statusButtonClass"
                    :disabled="pending"
                    class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                    type="button"
                    @click="isMenuOpen = !isMenuOpen"
                  >
                    <span class="visually-hidden"> Toggle reading status </span>
                  </button>
                </div>

                <ul v-if="isMenuOpen" class="dropdown-menu show">
                  <li v-for="option in statusOptions" :key="option.value">
                    <button
                      :class="{ active: currentStatus === option.value }"
                      class="dropdown-item"
                      type="button"
                      @click="selectStatus(option.value)"
                    >
                      {{ option.label }}
                    </button>
                  </li>
                </ul>
              </div>
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
  </main>
</template>

<style scoped></style>
