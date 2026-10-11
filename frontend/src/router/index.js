import {createRouter, createWebHistory} from "vue-router";
import Frontpage from "@/views/Frontpage.vue";
import ListsView from "@/views/ListsView.vue";
import LibraryView from "@/views/LibraryView.vue";
import StatsView from "@/views/StatsView.vue";

// Define the client-side pages without full browser reloads.
const router = createRouter({
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
    ],
});

export default router;
