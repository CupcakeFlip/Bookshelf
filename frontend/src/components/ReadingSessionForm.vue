<script setup>
import { computed, reactive, watch } from "vue";

const props = defineProps({
    modelValue: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    defaultFinishDate: { type: String, default: "" },
    defaultStartDate: { type: String, default: "" },
});
const emit = defineEmits(["save", "cancel"]);
const statuses = [
    { value: "reading", label: "Reading" },
    { value: "paused", label: "Paused" },
    { value: "finished", label: "Finished" },
    { value: "dnf", label: "Did not finish" },
];
const formats = [
    { value: "physical", label: "Physical" },
    { value: "ebook", label: "E-book" },
    { value: "audiobook", label: "Audiobook" },
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

function splitDate(date, precision) {
    if (!date || precision === "unknown") return ["", "", ""];
    const [year, month, day] = date.split("-");
    return [
        year,
        precision === "year" ? "" : String(Number(month)),
        precision === "day" ? String(Number(day)) : "",
    ];
}

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
    form.dnf_progress_value = source.dnf_progress_value ?? null;
    if (
        source.dnf_progress_type === "time" &&
        source.dnf_progress_value !== null &&
        source.dnf_progress_value !== undefined
    ) {
        form.progressHours = Math.floor(Number(source.dnf_progress_value) / 60);
        form.progressMinutes = Number(source.dnf_progress_value) % 60;
    }
    if (!value && props.defaultFinishDate)
        [form.finishYear, form.finishMonth, form.finishDay] = splitDate(
            props.defaultFinishDate,
            "day",
        );
    if (!value && props.defaultStartDate)
        [form.startYear, form.startMonth, form.startDay] = splitDate(
            props.defaultStartDate,
            "day",
        );
}

watch(() => props.modelValue, load, { immediate: true });

function dateData(prefix) {
    const year = form[`${prefix}Year`],
        month = form[`${prefix}Month`],
        day = form[`${prefix}Day`];
    if (!year) return { date: null, precision: "unknown" };
    if (!month) return { date: `${year}-01-01`, precision: "year" };
    if (!day)
        return {
            date: `${year}-${String(month).padStart(2, "0")}-01`,
            precision: "month",
        };
    return {
        date: `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`,
        precision: "day",
    };
}

function daysInMonth(prefix) {
    const year = Number(form[`${prefix}Year`]),
        month = Number(form[`${prefix}Month`]);
    return year > 0 && month > 0 ? new Date(year, month, 0).getDate() : 0;
}

const error = computed(() => {
    if (form.startYear && !/^\d{4}$/.test(String(form.startYear)))
        return "Year must contain four digits.";
    if (form.finishYear && !/^\d{4}$/.test(String(form.finishYear)))
        return "Year must contain four digits.";
    if (
        form.startDay &&
        (!form.startMonth || Number(form.startDay) > daysInMonth("start"))
    )
        return "The started date is invalid.";
    if (
        form.finishDay &&
        (!form.finishMonth || Number(form.finishDay) > daysInMonth("finish"))
    )
        return "The finished date is invalid.";
    const start = dateData("start"),
        finish = dateData("finish");
    if (
        start.precision === "day" &&
        finish.precision === "day" &&
        finish.date < start.date
    )
        return "The finished date cannot be before the started date.";
    if (
        form.status === "dnf" &&
        form.dnf_progress_type === "percentage" &&
        (Number(form.dnf_progress_value) < 0 ||
            Number(form.dnf_progress_value) > 100)
    )
        return "Percentage must be between 0 and 100.";
    if (
        form.status === "dnf" &&
        form.dnf_progress_type === "page" &&
        (!Number.isInteger(Number(form.dnf_progress_value)) ||
            Number(form.dnf_progress_value) < 0)
    )
        return "Page progress must be a non-negative integer.";
    if (
        form.status === "dnf" &&
        form.dnf_progress_type === "time" &&
        (Number(form.progressHours) < 0 ||
            Number(form.progressMinutes) < 0 ||
            Number(form.progressMinutes) > 59)
    )
        return "Enter a valid time.";
    return "";
});

function clearProgress() {
    form.dnf_progress_value = null;
    form.progressHours = "";
    form.progressMinutes = "";
}

function save() {
    if (error.value || props.saving) return;
    const start = dateData("start"),
        finish = dateData("finish");
    const progress =
        form.dnf_progress_type === "time"
            ? Number(form.progressHours || 0) * 60 +
              Number(form.progressMinutes || 0)
            : form.dnf_progress_value;
    const hasProgress =
        form.status === "dnf" &&
        progress !== "" &&
        progress !== null &&
        progress !== undefined;
    emit("save", {
        status: form.status,
        formats: [...form.formats],
        start_date: start.date,
        start_date_precision: start.precision,
        finish_date: finish.date,
        finish_date_precision: finish.precision,
        dnf_progress_type: hasProgress ? form.dnf_progress_type : null,
        dnf_progress_value: hasProgress ? progress : null,
    });
}

function toggleFormat(value) {
    form.formats = form.formats.includes(value)
        ? form.formats.filter((item) => item !== value)
        : [...form.formats, value];
}

function clearDay(prefix) {
    if (!form[`${prefix}Month`]) form[`${prefix}Day`] = "";
}
</script>
<template>
    <form class="vstack gap-3" @submit.prevent="save">
        <div>
            <label class="form-label">Status</label
            ><select v-model="form.status" class="form-select">
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
                        class="form-control"
                        inputmode="numeric"
                        maxlength="4"
                        placeholder="Year"
                    />
                </div>
                <div class="col-4">
                    <select
                        v-model="form[`${prefix}Month`]"
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
                    class="form-select"
                    @change="clearProgress"
                >
                    <option :value="null">None</option>
                    <option value="percentage">Percentage</option>
                    <option value="page">Page</option>
                    <option value="time">Time</option></select
                ><input
                    v-if="form.dnf_progress_type !== 'time'"
                    v-model="form.dnf_progress_value"
                    :placeholder="
                        form.dnf_progress_type === 'percentage'
                            ? 'Percent'
                            : 'Page'
                    "
                    class="form-control"
                    min="0"
                    type="number"
                />
                <template v-else
                    ><input
                        v-model="form.progressHours"
                        class="form-control"
                        min="0"
                        placeholder="Hours"
                        type="number" /><input
                        v-model="form.progressMinutes"
                        class="form-control"
                        max="59"
                        min="0"
                        placeholder="Minutes"
                        type="number"
                /></template>
            </div>
        </div>
        <p v-if="error" class="py-2 mb-0 alert alert-danger">{{ error }}</p>
        <div class="d-flex gap-2 justify-content-end">
            <button
                class="btn btn-outline-secondary"
                type="button"
                :disabled="saving"
                @click="emit('cancel')"
            >
                Cancel
            </button>
            <button class="btn btn-primary" type="submit" :disabled="saving" :aria-busy="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                {{ saving ? "Saving…" : "Save" }}
            </button>
        </div>
    </form>
</template>
