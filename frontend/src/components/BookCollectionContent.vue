<!-- Made with AI -->

<!--
    Displays shared loading, error, and book-grid content.

    Provides a consistent way to display book collections
    across Catalogue, Library, and reading list pages.
-->

<script setup>
import BookGrid from "@/components/BookGrid.vue";
import BookGridSkeleton from "@/components/loading/BookGridSkeleton.vue";
import LoadingIndicator from "@/components/loading/LoadingIndicator.vue";
import {computed} from "vue";

const props = defineProps({
    books: {type: Array, required: true},
    loading: Boolean,
    error: {type: String, default: ""},
    warning: {type: String, default: ""},
    skeletonCount: {type: Number, default: 24},
    label: {type: String, default: "Books"},
    loadingLabel: {type: String, default: ""},
    emptyMessage: {type: String, default: ""},
    sourceBookCount: {type: Number, default: null},
});

const emit = defineEmits(["status-change"]);

// Use the unfiltered book count when available to determine
// whether to display skeletons or retain the existing content.
const hasSourceBooks = computed(
    () => (props.sourceBookCount ?? props.books.length) > 0
);
</script>

<template>
    <LoadingIndicator
        v-if="loading && hasSourceBooks"
        :label="loadingLabel || `Updating ${label.toLowerCase()}…`"
        class="mb-3"
        small
    />

    <p v-if="warning" class="alert alert-warning" role="status">
        {{ warning }}
    </p>

    <p v-if="error" class="alert alert-danger" role="alert">
        {{ error }}
    </p>

    <div :aria-busy="loading" :aria-label="label">

        <!-- Initial loading -->
        <template v-if="loading && !hasSourceBooks">
            <span class="visually-hidden" role="status">
                Loading {{ label.toLowerCase() }}…
            </span>

            <BookGridSkeleton :count="skeletonCount"/>
        </template>

        <!-- Custom empty state -->
        <p
            v-else-if="!loading && !error && emptyMessage && !books.length"
            class="py-5 text-body-secondary text-center"
        >
            {{ emptyMessage }}
        </p>

        <!-- Book collection -->
        <BookGrid
            v-else-if="books.length || !error"
            :books="books"
            @status-change="emit('status-change', $event)"
        />

    </div>
</template>