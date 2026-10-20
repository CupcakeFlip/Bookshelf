<script setup>
defineProps({ noMargin: Boolean });
</script>

<template>
    <span class="spinner-border spinner-border-sm" :class="{ 'me-1': !noMargin }" aria-hidden="true"></span>
</template>
