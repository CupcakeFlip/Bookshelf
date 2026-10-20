<script setup>
import {computed} from "vue";

const props = defineProps({
    page: {type: Number, required: true},
    totalPages: {type: Number, default: null},
    hasNext: {type: Boolean, default: false},
    limit: {type: Number, default: 24},
    showPageSize: {type: Boolean, default: false},
    disabled: {type: Boolean, default: false},
});
const emit = defineEmits(["page-change", "limit-change"]);
const pages = computed(() => {
    if (props.totalPages === null) return [];
    const first = Math.max(1, Math.min(props.page - 2, props.totalPages - 4));
    return Array.from({length: Math.min(5, props.totalPages)}, (_, index) => first + index);
});
const nextEnabled = computed(() =>
    props.totalPages === null ? props.hasNext : props.page < props.totalPages,
);

function go(page) {
    if (!props.disabled && page >= 1 && (props.totalPages === null || page <= props.totalPages))
        emit("page-change", page);
}
</script>

<template>
    <div
        :class="showPageSize ? 'justify-content-between' : 'justify-content-center'"
        class="d-flex flex-wrap align-items-center gap-3 my-4"
    >
        <div v-if="showPageSize" class="d-flex align-items-center gap-2">
            <label for="books-page-size">Books per page</label>
            <select
                id="books-page-size"
                :value="limit"
                :disabled="disabled"
                class="form-select form-select-sm w-auto"
                @change="emit('limit-change', Number($event.target.value))"
            >
                <option v-for="size in [12, 24, 48, 96]" :key="size" :value="size">{{ size }}</option>
            </select>
        </div>
        <nav aria-label="Book pages">
            <ul class="pagination mb-0 flex-wrap">
                <li :class="{ disabled: disabled || page <= 1 }" class="page-item">
                    <button :disabled="disabled || page <= 1" class="page-link" type="button" @click="go(page - 1)">Previous
                    </button>
                </li>
                <li v-for="number in pages" :key="number" :class="{ active: number === page }" class="page-item">
                    <button :aria-current="number === page ? 'page' : undefined"
                        :aria-label="`Page ${number}`"
                        :disabled="disabled"
                        class="page-link"
                        type="button"
                        @click="go(number)">{{ number }}
                    </button>
                </li>
                <li v-if="totalPages === null || totalPages === 0" class="page-item disabled">
                    <span aria-current="page" class="page-link">Page {{ page }}</span>
                </li>
                <li :class="{ disabled: disabled || !nextEnabled }" class="page-item">
                    <button :disabled="disabled || !nextEnabled" class="page-link" type="button" @click="go(page + 1)">Next</button>
                </li>
            </ul>
        </nav>
    </div>
</template>
