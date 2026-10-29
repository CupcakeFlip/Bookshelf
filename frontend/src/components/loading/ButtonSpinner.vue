<!-- Made with AI -->

<!--
    Provides the small spinner used inside buttons while an action is pending.
    Keeps the indicator consistent across controls; the parent button supplies
    the action label and loading state.
-->
<script setup>
defineProps({noMargin: Boolean});
</script>

<template>
    <span :class="{ 'me-1': !noMargin }" aria-hidden="true" class="spinner-border spinner-border-sm"></span>
</template>
