<script setup>
import {onMounted, ref, watch} from "vue";
import {getReadingSessions} from "@/api/books";
import ReadingSessionForm from "@/components/ReadingSessionForm.vue";
const props = defineProps({book: {type: Object, required: true}, modelValue: Boolean, status: {type: String, default: "finished"}});
const emit = defineEmits(["update:modelValue", "confirm", "cancel"]);
const value = ref(null);
function today() { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`; }
async function open() {
  let sessions = [];
  try { sessions = await getReadingSessions(props.book); } catch { /* Unsaved books have no sessions yet. */ }
  const active = sessions.find(session => ["reading", "paused"].includes(session.status));
  value.value = active ? {...active, status: props.status, finish_date: today(), finish_date_precision: "day"} : {status: props.status, finish_date: today(), finish_date_precision: "day", formats: []};
}
function cancel() { emit("cancel"); emit("update:modelValue", false); }
function confirm(session) { emit("confirm", session); emit("update:modelValue", false); }
watch(() => props.modelValue, shown => { if (shown) open(); });
onMounted(() => { if (props.modelValue) open(); });
</script>
<template>
  <div v-if="modelValue" class="modal d-block" tabindex="-1" role="dialog" @click.self="cancel">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">{{ status === "dnf" ? "Did not finish" : "Finish reading" }}</h2><button type="button" class="btn-close" @click="cancel"></button></div><div class="modal-body"><ReadingSessionForm v-if="value" :model-value="value" @save="confirm" @cancel="cancel" /></div></div></div>
  </div>
</template>
