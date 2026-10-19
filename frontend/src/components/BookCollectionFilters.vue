<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  getGenreFilterCategories,
  normalizeGenre,
  STANDARD_GENRES,
} from "@/utils/bookFormatting";

const props = defineProps({ status: { type: String, default: "" } });
const emit = defineEmits(["change"]);
const query = ref(""),
  selectedGenres = ref([]),
  selectedCategories = ref([]),
  sortField = ref("recently_updated"),
  ascending = ref(false),
  advanced = ref(false),
  genres = ref([]),
  genreError = ref("");
let timer = null;
const sortLabels = {
  recently_updated: "Recently updated",
  title: "Title",
  release_date: "Release date",
  author: "Author",
};
const sortKey = computed(
  () => `${sortField.value}_${ascending.value ? "asc" : "desc"}`,
);
const activeAdvanced = computed(() => selectedGenres.value.length > 0);
const standardGenres = computed(() =>
  STANDARD_GENRES.map((name) => ({
    name,
    matches:
      filterCategories.value.find((group) => group.name === name)?.ids ?? [],
  })),
);
const selectedStandardGenre = computed({
  get() {
    if (selectedGenres.value.length === 0) return "";

    return selectedCategories.value.length === 1
      ? selectedCategories.value[0]
      : "";
  },

  set(name) {
    const genre = standardGenres.value.find((genre) => genre.name === name);

    selectedCategories.value = genre ? [genre.name] : [];
    selectedGenres.value = genre ? [...genre.matches] : [];
  },
});

const groupedGenres = computed(() => {
  const groups = new Map();

  for (const genre of genres.value) {
    const name = normalizeGenre(genre.name);
    const key = name.toLowerCase();

    if (!groups.has(key)) {
      groups.set(key, {
        name,
        ids: [],
      });
    }

    groups.get(key).ids.push(Number(genre.id));
  }

  return [...groups.values()].sort((a, b) => a.name.localeCompare(b.name));
});
const filterCategories = computed(() => getGenreFilterCategories(genres.value));

const activeGenres = computed(() =>
  filterCategories.value.filter((group) =>
    selectedCategories.value.includes(group.name),
  ),
);

function removeGenreGroup(group) {
  selectedCategories.value = selectedCategories.value.filter(
    (name) => name !== group.name,
  );
  selectedGenres.value = selectedGenres.value.filter(
    (id) => !group.ids.includes(id),
  );

  emitChange();
}

function emitChange() {
  emit("change", {
    q: query.value,
    genres: [],
    categories: [...selectedCategories.value],
    sort: sortKey.value,
  });
}

function scheduleSearch() {
  clearTimeout(timer);
  timer = setTimeout(emitChange, 250);
}

function selectGenre() {
  emitChange();
}

function removeGenre(id) {
  selectedGenres.value = selectedGenres.value.filter(
    (item) => item !== Number(id),
  );
  emitChange();
}

function toggleAdvancedGenre(group) {
  const allSelected = selectedCategories.value.includes(group.name);
  selectedCategories.value = allSelected
    ? selectedCategories.value.filter((name) => name !== group.name)
    : [...selectedCategories.value, group.name];
  selectedGenres.value = [
    ...new Set(
      selectedCategories.value.flatMap(
        (name) =>
          filterCategories.value.find((item) => item.name === name)?.ids ?? [],
      ),
    ),
  ];

  emitChange();
}

function isGenreSelected(group) {
  return selectedCategories.value.includes(group.name);
}

function toggleDirection() {
  ascending.value = !ascending.value;
  emitChange();
}

function reset() {
  query.value = "";
  selectedGenres.value = [];
  selectedCategories.value = [];
  sortField.value = "recently_updated";
  ascending.value = false;
  emitChange();
}

async function loadGenres() {
  genreError.value = "";
  try {
    const suffix = props.status
      ? `?status=${encodeURIComponent(props.status)}`
      : "";
    const response = await fetch(`/api/genres.php${suffix}`);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw Error(payload.error || "Could not load genres.");
    genres.value = Array.isArray(payload) ? payload : [];
  } catch (error) {
    genreError.value = error.message;
  }
}

watch(
  () => props.status,
  () => {
    reset();
    loadGenres();
  },
);
onMounted(loadGenres);
onUnmounted(() => clearTimeout(timer));
</script>
<template>
  <section aria-label="Book collection filters" class="mb-4 book-filters">
    <div class="book-filters__bar">
      <div class="book-filters__search">
        <label class="form-label" for="book-search"> Search books </label>
        <input
          id="book-search"
          v-model="query"
          class="form-control"
          placeholder="Title or author"
          type="search"
          @input="scheduleSearch"
        />
      </div>
      <div>
        <label class="form-label" for="book-genre">Genre</label
        ><select
          id="book-genre"
          v-model="selectedStandardGenre"
          class="form-select"
          @change="selectGenre"
        >
          <option value="">All genres</option>

          <option
            v-for="genre in standardGenres"
            :key="genre.name"
            :disabled="genre.matches.length === 0"
            :value="genre.name"
          >
            {{ genre.name }}
          </option>
        </select>
      </div>
      <div class="book-filters__sort">
        <label class="form-label" for="book-sort">Sort by</label>
        <div class="input-group">
          <select
            id="book-sort"
            v-model="sortField"
            class="form-select"
            @change="emitChange"
          >
            <option
              v-for="(label, field) in sortLabels"
              :key="field"
              :value="field"
            >
              {{ label }}
            </option>
          </select>
          <button
            :aria-label="ascending ? 'Sort ascending' : 'Sort descending'"
            :title="ascending ? 'Ascending' : 'Descending'"
            class="btn btn-outline-secondary"
            type="button"
            @click="toggleDirection"
          >
            <i
              :class="ascending ? 'bi bi-sort-up' : 'bi bi-sort-down'"
              aria-hidden="true"
            ></i
            ><span class="visually-hidden">{{
              ascending ? "Ascending" : "Descending"
            }}</span>
          </button>
        </div>
      </div>
      <div class="book-filters__actions">
        <button
          :aria-expanded="advanced"
          class="btn btn-outline-secondary btn-sm"
          type="button"
          @click="advanced = !advanced"
        >
          <i aria-hidden="true" class="bi bi-sliders2"></i> Advanced
          Filtering<span
            v-if="activeAdvanced"
            aria-label="Advanced filters active"
            class="ms-1"
            >•</span
          >
        </button>
        <button class="btn btn-secondary btn-sm" type="button" @click="reset">
          <i aria-hidden="true" class="bi bi-arrow-counterclockwise"></i> Reset
        </button>
      </div>
    </div>
    <div
      v-if="activeGenres.length"
      aria-label="Active genre filters"
      class="book-filters__chips"
    >
      <span
        v-for="genre in activeGenres"
        :key="genre.name"
        class="badge rounded-pill text-bg-secondary"
      >
        {{ genre.name }}
        <button
          :aria-label="`Remove ${genre.name} filter`"
          class="ms-1 btn-close btn-close-white"
          type="button"
          @click="removeGenreGroup(genre)"
        ></button>
      </span>
    </div>
    <div v-if="advanced" class="mt-3 book-filters__advanced">
      <fieldset>
        <legend class="form-label fs-6">Additional genres</legend>
        <p v-if="genreError" class="text-danger small">{{ genreError }}</p>
        <div v-if="filterCategories.length" class="d-flex flex-wrap gap-2">
          <label
            v-for="genre in filterCategories"
            :key="genre.name"
            class="mb-0 form-check form-check-inline"
          >
            <input
              :checked="isGenreSelected(genre)"
              class="form-check-input"
              type="checkbox"
              @change="toggleAdvancedGenre(genre)"
            />

            <span class="form-check-label">
              {{ genre.name }}
            </span>
          </label>
        </div>
        <span v-else class="text-body-secondary small"
          >No genres available.</span
        >
      </fieldset>
    </div>
  </section>
</template>
<style lang="scss" scoped>
.book-filters__bar {
  display: grid;
  align-items: end;
  grid-template-columns:
    minmax(12rem, 1.5fr) minmax(9rem, 1fr) minmax(12rem, 1.25fr)
    auto;
  gap: 0.75rem;
}

.book-filters__search {
  min-width: 0;
}

.book-filters__actions {
  display: flex;
  align-items: center;
  white-space: nowrap;
  gap: 0.5rem;
}

.book-filters__advanced {
  padding: 0.75rem 1rem;
  border: 1px solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
  background: var(--bs-tertiary-bg);
}

.book-filters__chips {
  display: flex;
  flex-wrap: wrap;
  margin-top: 0.6rem;
  gap: 0.35rem;
}

.book-filters__chips .badge {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 0.55rem;
}

.book-filters__chips .btn-close {
  width: 0.55rem;
  height: 0.55rem;
  padding: 0;
}

.book-filters__sort {
  .input-group > .btn {
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .bi {
    line-height: 1;
  }
}

@media (max-width: 991.98px) {
  .book-filters__bar {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .book-filters__search {
    grid-column: auto;
  }

  .book-filters__actions {
    justify-content: flex-end;
  }
}

@media (max-width: 575.98px) {
  .book-filters__bar {
    grid-template-columns: 1fr;
  }
  .book-filters__search {
    grid-column: auto;
  }
  .book-filters__actions {
    flex-wrap: wrap;
  }
}
</style>
