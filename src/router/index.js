import {createRouter, createWebHistory} from 'vue-router';
import Frontpage from "@/views/Frontpage.vue";
import Whatever from "@/views/Whatever.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
      {
          path: "/",
          name: "frontpage",
          component: Frontpage,
      },
      {
          path: "/whatever",
          name: "whatever",
          component: Whatever,
      },
  ]
})

export default router;