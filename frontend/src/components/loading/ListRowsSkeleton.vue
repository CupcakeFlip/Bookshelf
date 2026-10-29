<!-- Made with AI -->

<!--
    Displays five placeholder rows while the reading-lists overview loads.
    Resembles the list titles and cover previews so the page can show its
    expected layout before the API response arrives.
-->
<template>
    <div class="d-grid gap-3" aria-hidden="true">
        <div v-for="number in 5" :key="number" class="card p-3 d-flex flex-row align-items-center gap-3">
            <span class="list-skeleton__title list-skeleton__block"></span>
            <span v-for="cover in 4" :key="cover" class="list-skeleton__cover list-skeleton__block"></span>
        </div>
    </div>
</template>

<style scoped>
.list-skeleton__block { display: block; border-radius: .25rem; background: var(--bs-secondary-bg); animation: list-pulse 1.8s ease-in-out infinite; }
.list-skeleton__title { width: min(25%, 12rem); height: 1.25rem; margin-right: 1rem; }
.list-skeleton__cover { width: 3.5rem; aspect-ratio: 2 / 3; }
@keyframes list-pulse { 50% { opacity: .55; } }
@media (prefers-reduced-motion: reduce) { .list-skeleton__block { animation: none; } }
@media (max-width: 575.98px) { .list-skeleton__cover:not(:nth-child(2)) { display: none; } }
</style>
