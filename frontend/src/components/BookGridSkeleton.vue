<script setup>
defineProps({ count: { type: Number, default: 24 } });
</script>

<template>
    <div class="row g-3 g-lg-4" aria-hidden="true">
        <div v-for="number in count" :key="number" class="col-6 col-md-4 col-lg-3 col-xl-3">
            <div class="h-100 book-skeleton">
                <div class="book-skeleton__cover book-skeleton__block"></div>
                <div class="p-3">
                    <div class="book-skeleton__line book-skeleton__block mb-2"></div>
                    <div class="book-skeleton__line book-skeleton__line--short book-skeleton__block"></div>
                </div>
                <div class="mt-auto p-2 d-flex gap-2">
                    <div class="book-skeleton__button book-skeleton__block flex-grow-1"></div>
                    <div class="book-skeleton__button book-skeleton__block flex-grow-1"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.book-skeleton { display: flex; flex-direction: column; border-radius: .8rem; overflow: hidden; background: var(--bs-tertiary-bg); }
.book-skeleton__block { background: var(--bs-secondary-bg); background-image: linear-gradient(90deg, transparent, var(--bs-tertiary-bg), transparent); background-size: 200% 100%; animation: skeleton-shimmer 1.8s ease-in-out infinite; }
.book-skeleton__cover { width: 100%; aspect-ratio: 2 / 3; }
.book-skeleton__line { height: 1rem; border-radius: .25rem; }
.book-skeleton__line--short { width: 60%; }
.book-skeleton__button { height: 2rem; border-radius: var(--bs-border-radius); }
@keyframes skeleton-shimmer { to { background-position-x: -200%; } }
@media (prefers-reduced-motion: reduce) { .book-skeleton__block { animation: none; background-image: none; } }
</style>
