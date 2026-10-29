<!-- Made with AI -->

<!--
    Edits a reading attempt with partial dates, formats, and optional DNF progress.
    Validates a local draft and emits API fields without changing the parent record.
    The details page or status dialog handles saving and request errors.
-->
<script setup>
import {computed, reactive, useId, watch} from "vue";

const props = defineProps({
    modelValue: {type: Object, default: null},
    saving: {type: Boolean, default: false},
    defaultFinishDate: {type: String, default: ""},
    defaultStartDate: {type: String, default: ""},
});
// save carries API fields; cancel leaves the parent record untouched.
const emit = defineEmits(["save", "cancel"]);
const statuses = [
    {value: "reading", label: "Reading"},
    {value: "paused", label: "Paused"},
    {value: "finished", label: "Finished"},
    {value: "dnf", label: "Did not finish"},
];
const formats = [
    {value: "physical", label: "Physical"},
    {value: "ebook", label: "E-book"},
    {value: "audiobook", label: "Audiobook"},
];
const months = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
];
const empty = () => ({
    status: "finished",
    startYear: "",
    startMonth: "",
    startDay: "",
    finishYear: "",
    finishMonth: "",
    finishDay: "",
    formats: [],
    dnf_progress_type: null,
    dnf_progress_value: null,
    progressHours: "",
    progressMinutes: "",
});
const form = reactive(empty());
const progressErrorId = useId();
let previousProgressType = null;
const hasValue = (value) => value !== "" && value !== null && value !== undefined;
// The API stores listening progress in minutes; the form edits hours and minutes.
const progress = computed(() => {
    if (form.dnf_progress_type !== "time") {
        return form.dnf_progress_value;
    }
    if (!hasValue(form.progressHours) && !hasValue(form.progressMinutes)) {
        return null;
    }
    return Number(form.progressHours || 0) * 60 + Number(form.progressMinutes || 0);
});

/**
 * Splits a stored date into only the fields known at its recorded precision.
 * @param {string|null} date Stored YYYY-MM-DD date.
 * @param {string} precision day, month, year, or unknown.
 * @returns {string[]} Year, month, and day form values.
 */
function splitDate(date, precision) {
    if (!date || precision === "unknown") {
        return ["", "", ""];
    }
    const [year, month, day] = date.split("-");
    return [
        year,
        precision === "year" ? "" : String(Number(month)),
        precision === "day" ? String(Number(day)) : "",
    ];
}

/**
 * Copies a session into editable state so typing never mutates the parent record.
 * @param {Object|null} value Existing session, or null for a new attempt.
 * @returns {void}
 */
function load(value) {
    Object.assign(form, empty());
    const source = value ?? {};
    form.status = source.status ?? "finished";
    [form.startYear, form.startMonth, form.startDay] = splitDate(
        source.start_date,
        source.start_date_precision,
    );
    [form.finishYear, form.finishMonth, form.finishDay] = splitDate(
        source.finish_date,
        source.finish_date_precision,
    );
    form.formats = [...(source.formats ?? [])];
    form.dnf_progress_type = source.dnf_progress_type ?? null;
    previousProgressType = form.dnf_progress_type;
    form.dnf_progress_value = source.dnf_progress_value ?? null;
    if (
        source.dnf_progress_type === "time" &&
        source.dnf_progress_value !== null &&
        source.dnf_progress_value !== undefined
    ) {
        form.progressHours = Math.floor(Number(source.dnf_progress_value) / 60);
        form.progressMinutes = Number(source.dnf_progress_value) % 60;
    }
    if (!value && props.defaultFinishDate) {
        [form.finishYear, form.finishMonth, form.finishDay] = splitDate(
            props.defaultFinishDate,
            "day",
        );
    }
    if (!value && props.defaultStartDate) {
        [form.startYear, form.startMonth, form.startDay] = splitDate(
            props.defaultStartDate,
            "day",
        );
    }
}

watch(() => props.modelValue, load, {immediate: true});

/**
 * Converts partial form fields to the API date and precision pair.
 * @param {"start"|"finish"} prefix Date field group.
 * @returns {{date: string|null, precision: string}} Stored date representation.
 */
function dateData(prefix) {
    const year = form[`${prefix}Year`],
        month = form[`${prefix}Month`],
        day = form[`${prefix}Day`];
    if (!year) {
        return {date: null, precision: "unknown"};
    }
    if (!month) {
        return {date: `${year}-01-01`, precision: "year"};
    }
    if (!day) {
        return {
            date: `${year}-${String(month).padStart(2, "0")}-01`,
            precision: "month",
        };
    }
    return {
        date: `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`,
        precision: "day",
    };
}

/**
 * Finds the day choices for the selected month, including leap years.
 * @param {"start"|"finish"} prefix Date field group.
 * @returns {number} Number of days, or zero before a month and year are selected.
 */
function daysInMonth(prefix) {
    const year = Number(form[`${prefix}Year`]),
        month = Number(form[`${prefix}Month`]);
    return year > 0 && month > 0 ? new Date(year, month, 0).getDate() : 0;
}

const error = computed(() => {
    if (form.startYear && !/^\d{4}$/.test(String(form.startYear))) {
        return "Year must contain four digits.";
    }
    if (form.finishYear && !/^\d{4}$/.test(String(form.finishYear))) {
        return "Year must contain four digits.";
    }
    if (
        form.startDay &&
        (!form.startMonth || Number(form.startDay) > daysInMonth("start"))
    ) {
        return "The started date is invalid.";
    }
    if (
        form.finishDay &&
        (!form.finishMonth || Number(form.finishDay) > daysInMonth("finish"))
    ) {
        return "The finished date is invalid.";
    }
    const start = dateData("start"),
        finish = dateData("finish");
    if (
        start.precision === "day" &&
        finish.precision === "day" &&
        finish.date < start.date
    ) {
        return "The finished date cannot be before the started date.";
    }
    return "";
});

const progressError = computed(() => {
    if (form.status !== "dnf") {
        return "";
    }
    const type = form.dnf_progress_type;
    if (!type && hasValue(progress.value)) {
        return "Choose a progress type for the entered value.";
    }
    if (type && !hasValue(progress.value)) {
        return "Enter a progress value or select None.";
    }
    if (!type) {
        return "";
    }
    if (!Number.isFinite(Number(progress.value)) || Number(progress.value) < 0) {
        return "Progress must be a non-negative number.";
    }
    if (type === "percentage" && Number(progress.value) > 100) {
        return "Percentage must be between 0 and 100.";
    }
    if (type === "page" && !Number.isInteger(Number(progress.value))) {
        return "Page progress must be a non-negative integer.";
    }
    if (type === "time" && (
        !Number.isInteger(Number(form.progressHours || 0)) || Number(form.progressHours) < 0 ||
        !Number.isInteger(Number(form.progressMinutes || 0)) || Number(form.progressMinutes) < 0 ||
        Number(form.progressMinutes) > 59
    )) {
        return "Enter a valid time in whole hours and minutes.";
    }
    if (type === "page" && !form.formats.some((format) => ["physical", "ebook"].includes(format))) {
        return "Page progress requires a physical or e-book format.";
    }
    if (type === "time" && form.formats.length && !form.formats.includes("audiobook")) {
        return "Time progress requires an audiobook format.";
    }
    return "";
});

function changeProgressType() {
    // Retain a value entered before its unit; changing an existing unit clears it.
    if (previousProgressType !== null || form.dnf_progress_type === "time") {
        form.dnf_progress_value = null;
        form.progressHours = "";
        form.progressMinutes = "";
    }
    previousProgressType = form.dnf_progress_type;
}

/**
 * Emits API fields only; the parent owns persistence and error display.
 * @returns {void}
 */
function save() {
    if (error.value || progressError.value || props.saving) {
        return;
    }
    const start = dateData("start"),
        finish = dateData("finish");
    const hasProgress =
        form.status === "dnf" &&
        hasValue(progress.value);
    emit("save", {
        status: form.status,
        formats: [...form.formats],
        start_date: start.date,
        start_date_precision: start.precision,
        finish_date: finish.date,
        finish_date_precision: finish.precision,
        dnf_progress_type: hasProgress ? form.dnf_progress_type : null,
        dnf_progress_value: hasProgress ? Number(progress.value) : null,
    });
}

function toggleFormat(value) {
    form.formats = form.formats.includes(value)
        ? form.formats.filter((item) => item !== value)
        : [...form.formats, value];
}

function clearDay(prefix) {
    if (!form[`${prefix}Month`]) {
        form[`${prefix}Day`] = "";
    }
}

function clearFinishDate() {
    form.finishYear = "";
    form.finishMonth = "";
    form.finishDay = "";
}
</script>
<template>
    <form class="reading-session-form vstack gap-3" @submit.prevent="save">
        <div>
            <label class="form-label">Status</label
            ><select v-model="form.status" aria-label="Reading status" class="form-select">
            <option
                v-for="item in statuses"
                :key="item.value"
                :value="item.value"
            >
                {{ item.label }}
            </option>
        </select>
        </div>
        <div v-for="prefix in ['start', 'finish']" :key="prefix">
            <label class="form-label">{{
                    prefix === "start" ? "Started" : "Finished"
                }}</label>
            <div class="row g-2">
                <div class="col-4">
                    <input
                        v-model="form[`${prefix}Year`]"
                        :aria-label="`${prefix === 'start' ? 'Started' : 'Finished'} year`"
                        class="form-control"
                        inputmode="numeric"
                        maxlength="4"
                        placeholder="Year"
                    />
                </div>
                <div class="col-4">
                    <select
                        v-model="form[`${prefix}Month`]"
                        :aria-label="`${prefix === 'start' ? 'Started' : 'Finished'} month`"
                        class="form-select"
                        @change="clearDay(prefix)"
                    >
                        <option value="">Month</option>
                        <option
                            v-for="(month, index) in months"
                            :key="month"
                            :value="index + 1"
                        >
                            {{ month }}
                        </option>
                    </select>
                </div>
                <div class="col-4">
                    <select
                        v-model="form[`${prefix}Day`]"
                        :aria-label="`${prefix === 'start' ? 'Started' : 'Finished'} day`"
                        :disabled="!form[`${prefix}Month`]"
                        class="form-select"
                    >
                        <option value="">Day</option>
                        <option
                            v-for="day in daysInMonth(prefix)"
                            :key="day"
                            :value="day"
                        >
                            {{ day }}
                        </option>
                    </select>
                </div>
            </div>
            <button
                v-if="prefix === 'finish' && ['finished', 'dnf'].includes(form.status)"
                :disabled="saving"
                class="btn btn-sm btn-outline-secondary mt-2"
                type="button"
                @click="clearFinishDate"
            >
                I don't know the finish date
            </button>
        </div>
        <div>
            <label class="form-label">Format</label>
            <div class="d-flex flex-wrap gap-2">
                <button
                    v-for="item in formats"
                    :key="item.value"
                    :class="
                        form.formats.includes(item.value)
                            ? 'btn-primary'
                            : 'btn-outline-primary'
                    "
                    class="btn btn-sm"
                    type="button"
                    @click="toggleFormat(item.value)"
                >
                    {{ item.label }}
                </button>
            </div>
        </div>
        <div v-if="form.status === 'dnf'">
            <label class="form-label">Progress (optional)</label>
            <div class="input-group">
                <select
                    v-model="form.dnf_progress_type"
                    :aria-describedby="progressError ? progressErrorId : undefined"
                    :aria-invalid="!!progressError"
                    aria-label="DNF progress type"
                    class="form-select"
                    @change="changeProgressType"
                >
                    <option :value="null">None</option>
                    <option value="percentage">Percentage</option>
                    <option value="page">Page</option>
                    <option value="time">Time</option>
                </select
                ><input
                v-if="form.dnf_progress_type !== 'time'"
                v-model="form.dnf_progress_value"
                :aria-describedby="progressError ? progressErrorId : undefined"
                :aria-invalid="!!progressError"
                :placeholder="
                        form.dnf_progress_type === 'percentage'
                            ? 'Percent'
                            : 'Page'
                    "
                aria-label="DNF progress value"
                class="form-control"
                min="0"
                step="any"
                type="number"
            />
                <template v-else
                ><input
                    v-model="form.progressHours"
                    :aria-describedby="progressError ? progressErrorId : undefined"
                    :aria-invalid="!!progressError"
                    aria-label="DNF progress hours"
                    class="form-control"
                    min="0"
                    placeholder="Hours"
                    type="number"/><input
                    v-model="form.progressMinutes"
                    :aria-describedby="progressError ? progressErrorId : undefined"
                    :aria-invalid="!!progressError"
                    aria-label="DNF progress minutes"
                    class="form-control"
                    max="59"
                    min="0"
                    placeholder="Minutes"
                    type="number"
                /></template>
            </div>
            <p v-if="progressError" :id="progressErrorId" class="text-danger mt-2 mb-0" role="alert">{{
                    progressError
                }}</p>
        </div>
        <p v-if="error" class="py-2 mb-0 alert alert-danger">{{ error }}</p>
        <div class="d-flex gap-2 justify-content-end">
            <button
                :disabled="saving"
                class="btn btn-outline-secondary"
                type="button"
                @click="emit('cancel')"
            >
                Cancel
            </button>
            <button :aria-busy="saving" :disabled="saving" class="btn btn-primary" type="submit">
                <span v-if="saving" aria-hidden="true" class="spinner-border spinner-border-sm me-1"></span>
                {{ saving ? "Saving…" : "Save" }}
            </button>
        </div>
    </form>
</template>

<style scoped>
@media (max-width: 767.98px), (pointer: coarse) {
    .reading-session-form .btn,
    .reading-session-form .form-control,
    .reading-session-form .form-select {
        min-width: 44px;
        min-height: 44px;
    }
}
</style>
