<!-- Made with AI -->

<!--
    Provides the shared control for adding, changing, or removing library status.
    Uses the reading-status dialog for Finished and Did Not Finish details,
    and reports confirmed API changes to cards and the book details page.
-->
<script setup>
import {removeBookStatus, saveBookStatus} from "@/api/books";
import ButtonSpinner from "@/components/loading/ButtonSpinner.vue";
import ReadingStatusDialog from "@/components/ReadingStatusDialog.vue";
import {computed, nextTick, onMounted, onUnmounted, ref, useId, watch} from "vue";

const dropdownElement = ref(null);
const statusButton = ref(null);
const menuId = useId();
const pending = ref(false);
const saving = ref(false);
const isMenuOpen = ref(false);
const errorMessage = ref("");
const finishedDialogOpen = ref(false);
const finishedDetails = ref(null);
const dialogStatus = ref("finished");
let active = true;

const statusOptions = [
    {value: null, label: "Not in library"},
    {value: "want_to_read", label: "Want to read"},
    {value: "reading", label: "Reading"},
    {value: "finished", label: "Finished"},
    {value: "paused", label: "Paused"},
    {value: "dnf", label: "Did not finish"},
];

/**
 * @typedef {Object} BookStatusData
 * @property {string|number} hardcover_id
 * @property {string|null} [status]
 */

/** @type {{book: BookStatusData}} */
const props = defineProps({
    book: {type: Object, required: true},
    beforeStatusChange: {type: Function, default: null},
});
/** User actions that the parent view can handle. */
const emit = defineEmits(["status-change", "menu-toggle"]);

const currentStatus = computed(() => props.book.status ?? null);

// Normalize status spelling so the same status always gets the same label and style.
const normalized = computed(() =>
    String(props.book.status ?? "")
        .toLowerCase()
        .trim()
        .replaceAll("_", "-")
        .replaceAll(" ", "-"),
);
const statusClass = computed(
    () =>
        ({
            "want-to-read": "status-want-read",
            "want-read": "status-want-read",
            reading: "status-reading",
            finished: "status-finished",
            read: "status-finished",
            paused: "status-paused",
            dnf: "status-dnf",
        })[normalized.value] ?? "status-want-read",
);
const statusLabel = computed(
    () =>
        ({
            "want-to-read": "Want to read",
            "want-read": "Want to read",
            reading: "Reading",
            finished: "Finished",
            read: "Finished",
            paused: "Paused",
            dnf: "DNF",
        })[normalized.value] ?? "Not in library",
);
const statusButtonClass = computed(() =>
    currentStatus.value
        ? `btn-status btn-${statusClass.value.replace("status-", "")}`
        : "btn-status btn-not-in-library",
);

/**
 * Persists a status change and reports the updated book to the parent grid.
 *
 * @param {"want_to_read"|"reading"|"finished"|"paused"|"dnf"|null} status
 *        New status, or null to remove the book from the library.
 * @returns {Promise<void>}
 */
async function selectStatus(status) {
    if (pending.value || status === currentStatus.value) {
        isMenuOpen.value = false;
        return;
    }
    const previous = currentStatus.value;
    pending.value = true;
    errorMessage.value = "";
    isMenuOpen.value = false;
    try {
        let saved;
        if (status === "finished" || status === "dnf") {
            dialogStatus.value = status;
            finishedDialogOpen.value = true;
            saved = await new Promise((resolve) => {
                finishedDetails.value = resolve;
            });
            if (saved === null) {
                return;
            }
        } else {
            saved = await persistStatus(status, previous);
        }
        if (!active) {
            return;
        }
        if (saved === null) {
            return;
        }
        if (active) {
            emit("status-change", {
                book: {...props.book, ...saved, status},
                status,
                previous,
            });
        }
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        saving.value = false;
        pending.value = false;
        if (status === "finished" || status === "dnf") {
            nextTick(() => {
                if (document.activeElement === document.body) {
                    statusButton.value?.focus();
                }
            });
        }
    }
}

/**
 * Runs the optional parent hook and persists a confirmed selection.
 * @param {string|null} status New status, or null for removal.
 * @param {string|null} previous Previous library status.
 * @param {Object} [details={}] Confirmed session fields.
 * @returns {Promise<Object|null>} Server result, or null if cancelled.
 * @throws {Error} If the API rejects the change.
 */
async function persistStatus(status, previous, details = {}) {
    if (props.beforeStatusChange) {
        details = await props.beforeStatusChange(status, previous);
        if (details === null) {
            return null;
        }
    }
    if (!active) {
        return null;
    }
    saving.value = true;
    try {
        return status === null
            ? await removeBookStatus(props.book)
            : await saveBookStatus(props.book, status, details);
    } finally {
        saving.value = false;
    }
}

function saveDialogSession(session) {
    return persistStatus(dialogStatus.value, currentStatus.value, session);
}

/**
 * Adds an untracked book, or toggles the menu for a book already in the library.
 *
 * @returns {void}
 */
function mainStatusAction() {
    if (currentStatus.value === null) {
        selectStatus("want_to_read");
    } else {
        isMenuOpen.value = !isMenuOpen.value;
    }
}

/**
 * Closes the status dropdown when clicking outside it.
 */
function handleOutsideClick(event) {
    if (
        isMenuOpen.value &&
        dropdownElement.value &&
        !dropdownElement.value.contains(event.target)
    ) {
        isMenuOpen.value = false;
    }
}

/**
 * Closes the status dropdown when pressing Escape.
 */
function handleEscape(event) {
    if (event.key === "Escape" && isMenuOpen.value) {
        isMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener("pointerdown", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
    active = false;
    finishedDetails.value?.(null);
    finishedDetails.value = null;
    document.removeEventListener("pointerdown", handleOutsideClick);
    document.removeEventListener("keydown", handleEscape);
});

watch(isMenuOpen, (isOpen) => {
    emit("menu-toggle", isOpen);
});
</script>

<template>
    <div ref="dropdownElement" class="dropdown" @click.stop>
        <div class="btn-group w-100 h-100" role="group">
            <button
                ref="statusButton"
                :aria-busy="saving"
                :aria-controls="currentStatus === null ? undefined : menuId"
                :aria-expanded="currentStatus === null ? undefined : isMenuOpen"
                :class="statusButtonClass"
                :disabled="pending"
                class="btn btn-sm"
                type="button"
                @click="mainStatusAction"
            >
                <ButtonSpinner v-if="saving"/>
                {{ statusLabel }}
            </button>
            <button
                :aria-controls="menuId"
                :aria-expanded="isMenuOpen"
                :class="statusButtonClass"
                :disabled="pending"
                class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                type="button"
                @click="isMenuOpen = !isMenuOpen"
            >
                <span class="visually-hidden">Toggle reading status</span>
            </button>
        </div>
        <ul v-if="isMenuOpen" :id="menuId" class="dropdown-menu show">
            <li v-for="option in statusOptions" :key="option.value">
                <button
                    :class="{ active: currentStatus === option.value }"
                    class="dropdown-item"
                    type="button"
                    @click="selectStatus(option.value)"
                >
                    {{ option.label }}
                </button>
            </li>
        </ul>
        <p v-if="errorMessage" class="mt-2 mb-0 text-danger small" role="alert">
            {{ errorMessage }}
        </p>
        <ReadingStatusDialog
            v-model="finishedDialogOpen"
            :book="book"
            :return-focus="statusButton"
            :save-session="saveDialogSession"
            :status="dialogStatus"
            @cancel="finishedDetails?.(null)"
            @confirm="finishedDetails?.($event)"
        />
    </div>
</template>

<style lang="scss" scoped>
.dropdown-menu {
    z-index: 1060;
}

.btn-status,
.dropdown-item {
    min-width: 44px;
    min-height: 24px;
}

.btn-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 767.98px), (pointer: coarse) {
    .btn-status,
    .dropdown-item {
        min-height: 44px;
    }
}

.btn-not-in-library {
    color: var(--bs-btn-color) !important;
    border-color: var(--bs-btn-border-color) !important;
    background-color: var(--bs-btn-bg) !important;
    --bs-btn-color: #f5ece8;
    --bs-btn-bg: #625b58;
    --bs-btn-border-color: #625b58;
    --bs-btn-hover-color: #f5ece8;
    --bs-btn-hover-bg: #756c68;
    --bs-btn-hover-border-color: #756c68;
    --bs-btn-active-color: #f5ece8;
    --bs-btn-active-bg: #504a47;
    --bs-btn-active-border-color: #504a47;
}
</style>
