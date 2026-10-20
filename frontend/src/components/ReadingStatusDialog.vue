<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { getReadingSessions } from "@/api/books";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
import LoadingIndicator from "@/components/LoadingIndicator.vue";

const props = defineProps({
    book: { type: Object, required: true },
    modelValue: Boolean,
    status: { type: String, default: "finished" },
});
const emit = defineEmits(["update:modelValue", "confirm", "cancel"]);
const value = ref(null);
const loading = ref(false);
const error = ref("");
let requestNumber = 0;

function today() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
        date.getDate(),
    ).padStart(2, "0")}`;
}

async function open() {
    const request = ++requestNumber;
    loading.value = true;
    value.value = null;
    error.value = "";
    let sessions = [];
    if (props.book.id) {
        try {
            sessions = await getReadingSessions(props.book);
        } catch (err) {
            if (request === requestNumber) {
                error.value = err.message;
                loading.value = false;
            }
            return;
        }
    }
    if (request !== requestNumber || !props.modelValue) return;
    const active = sessions.find((session) =>
        ["reading", "paused"].includes(session.status),
    );
    value.value = active
        ? {
              ...active,
              status: props.status,
              finish_date: today(),
              finish_date_precision: "day",
          }
        : {
              status: props.status,
              finish_date: today(),
              finish_date_precision: "day",
              formats: [],
          };
    loading.value = false;
}

function cancel() {
    ++requestNumber;
    loading.value = false;
    emit("cancel");
    emit("update:modelValue", false);
}

function confirm(session) {
    ++requestNumber;
    emit("confirm", session);
    emit("update:modelValue", false);
}

watch(
    () => props.modelValue,
    (shown) => {
        if (shown) open();
        else {
            ++requestNumber;
            loading.value = false;
            value.value = null;
        }
    },
);
onMounted(() => {
    if (props.modelValue) open();
});
onUnmounted(() => { ++requestNumber; });
</script>
<template>
    <div
        v-if="modelValue"
        class="d-block modal"
        role="dialog"
        tabindex="-1"
        @click.self="cancel"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">
                        {{
                            status === "dnf"
                                ? "Did not finish"
                                : "Finish reading"
                        }}
                    </h2>
                    <button
                        class="btn-close"
                        type="button"
                        @click="cancel"
                    ></button>
                </div>
                <div class="modal-body">
                    <LoadingIndicator v-if="loading" label="Loading reading session…" small />
                    <div v-if="error" class="alert alert-danger" role="alert">
                        {{ error }}
                        <button class="btn btn-sm btn-outline-danger ms-2" type="button" @click="open">Retry</button>
                    </div>
                    <ReadingSessionForm
                        v-if="value"
                        :model-value="value"
                        @cancel="cancel"
                        @save="confirm"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
