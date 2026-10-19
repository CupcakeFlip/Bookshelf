import { createRouter, createWebHistory } from "vue-router";
import Frontpage from "@/views/Frontpage.vue";
import ListsView from "@/views/ListsView.vue";
import LibraryView from "@/views/LibraryView.vue";
import StatsView from "@/views/StatsView.vue";
import BookDetailsView from "@/views/BookDetailsView.vue";

// Define the client-side pages without full browser reloads.
// Each route maps directly to a view; the PHP API is called by those views/components.
const router = createRouter({
  // History mode keeps routes readable; the web server must serve index.html
  // for direct navigation to one of these client-side paths.
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/",
      name: "frontpage",
      component: Frontpage,
    },
    {
      path: "/lists",
      name: "lists",
      component: ListsView,
    },
    {
      path: "/stats",
      name: "stats",
      component: StatsView,
    },
    {
      path: "/library",
      name: "library",
      component: LibraryView,
    },
    {
      path: "/books/:hardcoverId",
      name: "book-details",
      component: BookDetailsView,
    },
  ],
});

export default router;
