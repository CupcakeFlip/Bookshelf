<!--
    Provides the root component mounted by main.js.
    Renders the current router view so each page can provide its own content
    and navigation within the same Vue application.
-->
<script setup>
import {RouterView} from "vue-router";
</script>

<template>
    <!-- RouterView renders the view selected by the client-side router. -->
    <RouterView/>
</template>
