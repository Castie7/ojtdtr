import { createRouter, createWebHistory, type RouteRecordRaw, type RouteLocationNormalized, type NavigationGuardNext } from 'vue-router';

// Fix 1: Ensure these files actually exist in your folder!
import OjtDashboard from '../components/OjtDashboard.vue';
import LoginView from '../components/LoginView.vue';
import PrintableDtrView from '../components/PrintableDtrView.vue';

// Fix 2: Define the Meta types so TS doesn't complain
declare module 'vue-router' {
    interface RouteMeta {
        requiresAuth?: boolean;
        requiresGuest?: boolean;
    }
}

const routes: Array<RouteRecordRaw> = [
    {
        path: '/',
        name: 'Login',
        component: LoginView,
        meta: { requiresGuest: true }
    },
    {
        path: '/dashboard',
        name: 'Dashboard',
        component: OjtDashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/print-dtr',
        name: 'PrintDtr',
        component: PrintableDtrView,
        meta: { requiresAuth: true }
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to: RouteLocationNormalized, from: RouteLocationNormalized, savedPosition: any) {
        return { top: 0, behavior: 'smooth' };
    }
});

// Fix 3: Explicitly type the Guard arguments
router.beforeEach((to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) => {
    const isAuthenticated = localStorage.getItem('token');

    // Check the meta fields
    if (to.meta.requiresAuth && !isAuthenticated) {
        next('/');
    } else if (to.meta.requiresGuest && isAuthenticated) {
        next('/dashboard');
    } else {
        next();
    }
});

export default router;