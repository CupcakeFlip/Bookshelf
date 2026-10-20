<script setup>
defineProps({ label: { type: String, default: "Loading books…" }, small: Boolean });
</script>

<template>
    <div class="d-flex align-items-center gap-2 text-body-secondary" role="status" aria-live="polite">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <span :class="{ small }">{{ label }}</span>
    </div>
</template>
