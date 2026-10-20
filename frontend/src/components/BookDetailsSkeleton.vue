<template>
    <div class="row g-4" aria-hidden="true">
        <div class="col-12 col-lg-8">
            <div class="row g-3">
                <div class="col-6 col-sm-4"><div class="details-skeleton__cover details-skeleton__block"></div></div>
                <div class="col-6 col-sm-8 d-grid align-content-start gap-3">
                    <div class="details-skeleton__line details-skeleton__block w-75"></div>
                    <div class="details-skeleton__line details-skeleton__block w-50"></div>
                    <div class="details-skeleton__line details-skeleton__block w-25"></div>
                </div>
            </div>
            <div class="details-skeleton__paragraph details-skeleton__block mt-4"></div>
        </div>
        <div class="col-12 col-lg-4 d-grid gap-3 align-content-start">
            <div v-for="number in 3" :key="number" class="details-skeleton__panel details-skeleton__block"></div>
        </div>
    </div>
</template>

<style scoped>
.details-skeleton__block { border-radius: var(--bs-border-radius); background: var(--bs-secondary-bg); animation: details-pulse 1.8s ease-in-out infinite; }
.details-skeleton__cover { aspect-ratio: 2 / 3; }
.details-skeleton__line { height: 1.5rem; }
.details-skeleton__paragraph { height: 8rem; }
.details-skeleton__panel { height: 7rem; }
@keyframes details-pulse { 50% { opacity: .55; } }
@media (prefers-reduced-motion: reduce) { .details-skeleton__block { animation: none; } }
</style>
