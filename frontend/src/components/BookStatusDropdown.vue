<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from "vue";
import {removeBookStatus, saveBookStatus} from "@/api/books";

const dropdownElement = ref(null);
const pending = ref(false);
const isMenuOpen = ref(false);
const errorMessage = ref("");

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
    const saved =
      status === null
        ? await removeBookStatus(props.book)
        : await saveBookStatus(props.book, status);
    emit("status-change", {
      book: saved.status === undefined ? {...props.book, status} : saved,
      status,
      previous,
    });
  } catch (error) {
    errorMessage.value = error.message;
  } finally {
    pending.value = false;
  }
}

/**
 * Uses the primary status button as a shortcut for adding an untracked book.
 *
 * @returns {void}
 */
function mainStatusAction() {
  if (currentStatus.value === null) selectStatus("want_to_read");
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
        :class="statusButtonClass"
        :disabled="pending"
        class="btn btn-sm"
        type="button"
        @click="mainStatusAction"
      >
        {{ statusLabel }}
      </button>
      <button
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
    <ul v-if="isMenuOpen" class="dropdown-menu show">
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
    <p
      v-if="errorMessage"
      class="text-danger small mt-2 mb-0"
      role="alert"
    >
      {{ errorMessage }}
    </p>
  </div>
</template>

<style scoped></style>
