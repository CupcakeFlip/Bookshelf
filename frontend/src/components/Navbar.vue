<template>
    <!-- Navigationen bruger router-links, så siden skifter uden en fuld genindlæsning. -->
    <nav aria-label="Primary navigation" class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <RouterLink class="navbar-brand fw-semibold" to="/">Bookshelf</RouterLink>
            <button aria-controls="primary-navigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
                class="navbar-toggler"
                data-bs-target="#primary-navigation"
                data-bs-toggle="collapse"
                type="button"><span class="navbar-toggler-icon"/></button>
            <div id="primary-navigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li v-for="item in items" :key="item.to" class="nav-item">
                        <RouterLink :aria-disabled="item.soon ? 'true' : undefined"
                            :tabindex="item.soon ? -1 : undefined"
                            :to="item.to"
                            class="nav-link"
                            @click="item.soon ? stop($event) : undefined">{{ item.label }}<span v-if="item.soon"
                            class="visually-hidden"> (coming soon)</span></RouterLink>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>
<script setup>
/** Navigation entries rendered in the primary menu. */
const items = [
    {label: "Home", to: "/"},
    {label: "Lists", to: "/lists"},
    {label: "Stats", to: "/stats", soon: true},
    {label: "Library", to: "/library"}
];

/** Prevents placeholder links from navigating before their feature exists. */
const stop = event => event.preventDefault();
</script>
