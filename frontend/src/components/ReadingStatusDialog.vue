<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, useId, watch} from "vue";
import {getReadingSessions} from "@/api/books";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
import LoadingIndicator from "@/components/LoadingIndicator.vue";

const props = defineProps({
    book: {type: Object, required: true},
    modelValue: Boolean,
    status: {type: String, default: "finished"},
    returnFocus: {type: Object, default: null},
});
const emit = defineEmits(["update:modelValue", "confirm", "cancel"]);
const value = ref(null);
const loading = ref(false);
const error = ref("");
const modal = ref(null);
const titleId = useId();
let requestNumber = 0;
let previousFocus;
let previousOverflow;

function activateDialog() {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", handleKeydown);
    document.addEventListener("focusin", containFocus);
    nextTick(() => modal.value?.querySelector(".btn-close")?.focus());
}

function deactivateDialog() {
    if (previousOverflow === undefined) return;
    document.body.style.overflow = previousOverflow;
    previousOverflow = undefined;
    document.removeEventListener("keydown", handleKeydown);
    document.removeEventListener("focusin", containFocus);
    const target = props.returnFocus ?? previousFocus;
    // Wait for the dropdown's pending action to settle and enable its trigger.
    nextTick(() => {
        if (target?.isConnected) target.focus();
    });
}

function containFocus(event) {
    if (modal.value && !modal.value.contains(event.target)) {
        modal.value.focus();
    }
}

function handleKeydown(event) {
    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        cancel();
    } else if (event.key === "Tab") {
        const controls = [
            ...modal.value.querySelectorAll(
                'button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex="0"]',
            )
        ];
        const first = controls[0];
        const last = controls.at(-1);
        const current = document.activeElement;
        if (event.shiftKey && (current === first || !controls.includes(current))) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && (current === last || !controls.includes(current))) {
            event.preventDefault();
            first?.focus();
        }
    }
}

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
        if (shown) {
            activateDialog();
            open();
        } else {
            ++requestNumber;
            loading.value = false;
            value.value = null;
            deactivateDialog();
        }
    },
);
onMounted(() => {
    if (props.modelValue) {
        activateDialog();
        open();
    }
});
onBeforeUnmount(() => {
    ++requestNumber;
    deactivateDialog();
});
</script>
<template>
    <Teleport to="body">
        <div v-if="modelValue"
            aria-hidden="true"
            class="modal-backdrop show reading-status-backdrop"
            @click="cancel"></div>
        <div
            v-if="modelValue"
            ref="modal"
            :aria-labelledby="titleId"
            aria-modal="true"
            class="d-block modal reading-status-modal"
            role="dialog"
            tabindex="-1"
            @click.self="cancel"
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" @click.self="cancel">
                <div class="modal-content" @click.stop>
                    <div class="modal-header">
                        <h2 :id="titleId" class="modal-title h5">
                            {{
                                status === "dnf"
                                    ? "Did not finish"
                                    : "Finish reading"
                            }}
                        </h2>
                        <button
                            aria-label="Close reading status dialog"
                            class="btn-close"
                            type="button"
                            @click="cancel"
                        ></button>
                    </div>
                    <div class="modal-body">
                        <LoadingIndicator v-if="loading" label="Loading reading session…" small/>
                        <div v-if="error" class="alert alert-danger" role="alert">
                            {{ error }}
                            <button class="btn btn-sm btn-outline-danger ms-2" type="button" @click="open">Retry
                            </button>
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
    </Teleport>
</template>

<style scoped>
.reading-status-backdrop {
    z-index: 1065;
}

.reading-status-modal {
    position: fixed;
    z-index: 1070;
    overflow-y: auto;
    height: 100dvh;
    inset: 0;
    overscroll-behavior: contain;
}

.modal-dialog {
    width: calc(100% - 1rem);
    max-width: 500px;
    height: calc(100% - 1rem);
    height: calc(100dvh - 1rem);
    min-height: calc(100% - 1rem);
    margin: 0.5rem auto;
    pointer-events: auto;
}

.modal-body {
    overscroll-behavior: contain;
}

.btn-close {
    box-sizing: border-box;
    min-width: 44px;
    min-height: 44px;
    margin-block: -0.5rem;
}
</style>
