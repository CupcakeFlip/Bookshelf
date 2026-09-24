<template>
    <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Primary navigation">
        <div class="container">
            <RouterLink class="navbar-brand fw-semibold" to="/">Bookshelf</RouterLink>
            <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#primary-navigation"
                aria-controls="primary-navigation"
                aria-expanded="false"
                aria-label="Toggle navigation"><span class="navbar-toggler-icon"/></button>
            <div id="primary-navigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li v-for="item in items" :key="item.to" class="nav-item">
                        <RouterLink class="nav-link"
                            :to="item.to"
                            :aria-disabled="item.soon ? 'true' : undefined"
                            :tabindex="item.soon ? -1 : undefined"
                            @click="item.soon ? stop($event) : undefined">{{ item.label }}<span v-if="item.soon"
                            class="visually-hidden"> (coming soon)</span></RouterLink>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>
<script setup>
const items = [
    {label: "Home", to: "/"},
    {label: "Lists", to: "/lists"},
    {label: "Stats", to: "/stats", soon: true},
    {label: "Library", to: "/library"}
];
const stop = event => event.preventDefault();
</script>
