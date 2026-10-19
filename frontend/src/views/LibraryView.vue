<script setup>
import {onMounted,onUnmounted,ref} from "vue";
import Navbar from "@/components/Navbar.vue"; import BookGrid from "@/components/BookGrid.vue"; import BookCollectionFilters from "@/components/BookCollectionFilters.vue";
const books=ref([]),loading=ref(true),error=ref(""),filters=ref({q:"",genres:[],sort:"recently_updated_desc"}); let controller=null;
async function fetchBooks(next=filters.value){filters.value=next;controller?.abort();controller=new AbortController();loading.value=true;error.value="";try{const params=new URLSearchParams({q:next.q,sort:next.sort});if(next.genres.length)params.set("genres",next.genres.join(","));const r=await fetch(`/api/books.php?${params}`,{signal:controller.signal});const p=await r.json().catch(()=>({}));if(!r.ok)throw Error(p.error||"Could not load the library.");books.value=Array.isArray(p)?p:[]}catch(e){if(e.name!=="AbortError")error.value=e.message}finally{if(!controller.signal.aborted)loading.value=false}}
function updateBook({book}){const i=books.value.findIndex(b=>String(b.hardcover_id)===String(book.hardcover_id));if(i>=0)books.value[i]=book;}
function refreshBooks(){fetchBooks(filters.value)}
onMounted(()=>{fetchBooks();window.addEventListener("bookshelf:books-synced",fetchBooks)});onUnmounted(()=>{controller?.abort();window.removeEventListener("bookshelf:books-synced",fetchBooks)});
</script>
<template><Navbar/><main class="container py-4"><h1 class="mb-4">My Library</h1><BookCollectionFilters @change="fetchBooks"/><p v-if="loading">Loading books...</p><p v-else-if="error" class="alert alert-danger">{{error}}</p><BookGrid v-else :books="books" @status-change="refreshBooks"/></main></template>
