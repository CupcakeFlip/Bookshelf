<script setup>
import {computed,onMounted,ref} from "vue"; import Navbar from "@/components/Navbar.vue"; import BookListRow from "@/components/BookListRow.vue";
const definitions=[{id:"want_to_read",title:"Want to Read"},{id:"reading",title:"Currently Reading"},{id:"finished",title:"Finished"},{id:"paused",title:"Paused"},{id:"dnf",title:"Did Not Finish"}]; const books=ref([]),loading=ref(true),error=ref(""); const lists=computed(()=>definitions.map(d=>({...d,recentBooks:books.value.filter(b=>b.status===d.id).sort((a,b)=>new Date(b.updated_at||0)-new Date(a.updated_at||0))})));
async function fetchBooks(){try{const r=await fetch("/api/books.php");const p=await r.json().catch(()=>({}));if(!r.ok)throw Error(p.error||"Could not load reading lists.");books.value=Array.isArray(p)?p:[]}catch(e){error.value=e.message}finally{loading.value=false}} onMounted(fetchBooks);
</script>
<template><Navbar/><main class="container py-4"><h1 class="mb-4">Lists</h1><p v-if="loading">Loading reading lists...</p><p v-else-if="error" class="alert alert-danger">{{error}}</p><div v-else class="d-grid gap-3"><BookListRow v-for="list in lists" :key="list.id" :list="list"/></div></main></template>
