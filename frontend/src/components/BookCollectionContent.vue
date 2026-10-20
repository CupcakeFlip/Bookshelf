<script setup>
import BookGrid from "@/components/BookGrid.vue";
import BookGridSkeleton from "@/components/BookGridSkeleton.vue";
import LoadingIndicator from "@/components/LoadingIndicator.vue";

defineProps({
    books: { type: Array, required: true },
    loading: Boolean,
    error: { type: String, default: "" },
    skeletonCount: { type: Number, default: 24 },
    label: { type: String, default: "Books" },
});
const emit = defineEmits(["status-change"]);
</script>

<template>
    <LoadingIndicator v-if="loading && books.length" :label="`Updating ${label.toLowerCase()}…`" small class="mb-3" />
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <div :aria-busy="loading" :aria-label="label">
        <template v-if="loading && !books.length">
            <span class="visually-hidden" role="status">Loading {{ label.toLowerCase() }}…</span>
            <BookGridSkeleton :count="skeletonCount" />
        </template>
        <BookGrid v-else-if="books.length || !error" :books="books" @status-change="emit('status-change', $event)" />
    </div>
</template>
