<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from "vue";
import {normalizeGenre, STANDARD_GENRES} from "@/utils/bookFormatting";

const props = defineProps({status: {type: String, default: ""}});
const emit = defineEmits(["change"]);
const query = ref(""), singleGenre = ref(""), selectedGenres = ref([]), sortField = ref("recently_updated"),
  ascending = ref(false), advanced = ref(false), genres = ref([]), genreError = ref("");
let timer = null;
const sortLabels = {
  recently_updated: "Recently updated",
  title: "Title",
  release_date: "Release date",
  author: "Author"
};
const sortKey = computed(() => `${sortField.value}_${ascending.value ? "asc" : "desc"}`);
const activeAdvanced = computed(() => selectedGenres.value.length > 0);
const standardGenres = computed(() => STANDARD_GENRES.map(name => ({
  name,
  match: genres.value.find(genre => normalizeGenre(genre.name) === name) ?? null
})));
const activeGenres = computed(() => [...new Set(selectedGenres.value)].map(id => genres.value.find(genre => Number(genre.id) === id)).filter(Boolean));

function emitChange() {
  emit("change", {q: query.value, genres: [...selectedGenres.value], sort: sortKey.value});
}

function scheduleSearch() {
  clearTimeout(timer);
  timer = setTimeout(emitChange, 250)
}

function selectGenre() {
  selectedGenres.value = singleGenre.value ? [Number(singleGenre.value)] : [];
  emitChange()
}

function removeGenre(id) {
  selectedGenres.value = selectedGenres.value.filter(item => item !== Number(id));
  singleGenre.value = selectedGenres.value.length === 1 ? String(selectedGenres.value[0]) : "";
  emitChange()
}

function toggleAdvancedGenre(id) {
  const value = Number(id);
  if (selectedGenres.value.includes(value)) selectedGenres.value =
    selectedGenres.value.filter(item => item !== value); else selectedGenres.value = [...selectedGenres.value, value];
  singleGenre.value = selectedGenres.value.length === 1 ? String(selectedGenres.value[0]) : "";
  emitChange()
}

function toggleDirection() {
  ascending.value = !ascending.value;
  emitChange()
}

function reset() {
  query.value = "";
  singleGenre.value = "";
  selectedGenres.value = [];
  sortField.value = "recently_updated";
  ascending.value = false;
  emitChange()
}

async function loadGenres() {
  genreError.value = "";
  try {
    const suffix = props.status ? `?status=${encodeURIComponent(props.status)}` : "";
    const response = await fetch(`/api/genres.php${suffix}`);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw Error(payload.error || "Could not load genres.");
    genres.value = Array.isArray(payload) ? payload : []
  } catch (error) {
    genreError.value = error.message
  }
}

watch(() => props.status, () => {
  reset();
  loadGenres()
});
onMounted(loadGenres);
onUnmounted(() => clearTimeout(timer));
</script>
<template>
  <section aria-label="Book collection filters" class="book-filters mb-4">
    <div class="book-filters__bar">
      <div class="book-filters__search"><label class="form-label" for="book-search">Search
        books</label><input id="book-search"
        v-model="query"
        class="form-control"
        placeholder="Title or author"
        type="search"
        @input="scheduleSearch"></div>
      <div><label class="form-label" for="book-genre">Genre</label><select id="book-genre"
        v-model="singleGenre"
        class="form-select"
        @change="selectGenre">
        <option value="">All genres</option>
        <option v-for="genre in standardGenres" :key="genre.name" :disabled="!genre.match" :value="genre.match ? genre.match.id : ''">{{ genre.name }}</option>
      </select></div>
      <div class="book-filters__sort"><label class="form-label" for="book-sort">Sort by</label>
        <div class="input-group"><select id="book-sort" v-model="sortField" class="form-select" @change="emitChange">
          <option v-for="(label,field) in sortLabels" :key="field" :value="field">{{ label }}</option>
        </select>
          <button :aria-label="ascending ? 'Sort ascending' : 'Sort descending'"
            :title="ascending ? 'Ascending' : 'Descending'"
            class="btn btn-outline-secondary"
            type="button"
            @click="toggleDirection"><i :class="ascending ? 'bi bi-sort-up' : 'bi bi-sort-down'" aria-hidden="true"></i><span
            class="visually-hidden">{{ ascending ? 'Ascending' : 'Descending' }}</span></button>
        </div>
      </div>
      <div class="book-filters__actions">
        <button :aria-expanded="advanced"
          class="btn btn-outline-secondary btn-sm"
          type="button"
          @click="advanced=!advanced"><i aria-hidden="true" class="bi bi-sliders2"></i> Advanced
          Filtering<span v-if="activeAdvanced" aria-label="Advanced filters active" class="ms-1">•</span></button>
        <button class="btn btn-secondary btn-sm" type="button" @click="reset"><i aria-hidden="true"
          class="bi bi-arrow-counterclockwise"></i> Reset
        </button>
      </div>
    </div>
    <div v-if="activeGenres.length" class="book-filters__chips" aria-label="Active genre filters">
      <span v-for="genre in activeGenres" :key="genre.id" class="badge rounded-pill text-bg-secondary">
        {{ genre.name }} <button class="btn-close btn-close-white ms-1" type="button" :aria-label="`Remove ${genre.name} filter`" @click="removeGenre(genre.id)"></button>
      </span>
    </div>
    <div v-if="advanced" class="book-filters__advanced mt-3">
      <fieldset>
        <legend class="form-label fs-6">Additional genres</legend>
        <p v-if="genreError" class="text-danger small">{{ genreError }}</p>
        <div v-if="genres.length" class="d-flex flex-wrap gap-2"><label v-for="genre in genres"
          :key="genre.id"
          class="form-check form-check-inline mb-0"><input :checked="selectedGenres.includes(Number(genre.id))"
          class="form-check-input"
          type="checkbox"
          @change="toggleAdvancedGenre(genre.id)"><span class="form-check-label">{{ genre.name }}</span></label></div>
        <span v-else class="text-body-secondary small">No genres available.</span></fieldset>
    </div>
  </section>
</template>
<style lang="scss" scoped>
.book-filters__bar {
  display: grid;
  align-items: end;
  grid-template-columns:minmax(12rem, 2fr) minmax(9rem, 1fr) minmax(12rem, 1.25fr) auto;
  gap: .75rem
}

.book-filters__search {
  min-width: 0
}

.book-filters__actions {
  display: flex;
  align-items: center;
  white-space: nowrap;
  gap: .5rem;
}

.book-filters__advanced {
  padding: .75rem 1rem;
  border: 1px solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
  background: var(--bs-tertiary-bg)
}

 .book-filters__chips{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.6rem}.book-filters__chips .badge{display:inline-flex;align-items:center;padding:.4rem .55rem}.book-filters__chips .btn-close{width:.55rem;height:.55rem;padding:0}
@media(max-width: 991.98px) {
  .book-filters__bar {
    grid-template-columns:minmax(0, 1fr) minmax(0, 1fr)
  }
  .book-filters__search {
    grid-column: 1/-1
  }
  .book-filters__actions {
    justify-content: flex-start
  }
}

@media(max-width: 575.98px) {
  .book-filters__bar {
    grid-template-columns:1fr
  }
  .book-filters__search {
    grid-column: auto
  }
  .book-filters__actions {
    flex-wrap: wrap
  }
}
</style>
