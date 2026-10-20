import { createRouter, createWebHistory } from "vue-router";
import Frontpage from "@/views/Frontpage.vue";
const ListsView = () => import("@/views/ListsView.vue");
const LibraryView = () => import("@/views/LibraryView.vue");
const StatsView = () => import("@/views/StatsView.vue");
const BookDetailsView = () => import("@/views/BookDetailsView.vue");
const ListDetailView = () => import("@/views/ListDetailView.vue");

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
            path: "/lists/:status",
            name: "list-detail",
            component: ListDetailView,
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
