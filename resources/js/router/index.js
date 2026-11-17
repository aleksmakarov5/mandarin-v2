import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/Login.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', redirect: '/dashboard' },
            // { path: 'requests', name: 'requests', component: () => import('../views/Dashboard.vue') },
            { path: 'dashboard', name: 'dashboard', component: () => import('../views/Dashboard.vue') },
            { path: 'acceptance', name: 'acceptance', component: () => import('../views/Dashboard.vue') },
            { path: 'archive', name: 'archive', component: () => import('../views/Dashboard.vue') },
            { path: 'trash', name: 'trash', component: () => import('../views/Dashboard.vue') },
            { path: 'mail', name: 'mail', component: () => import('../views/Dashboard.vue') },
            { path: 'chat', name: 'chat', component: () => import('../views/Dashboard.vue') },
            { path: 'warehouse', name: 'warehouse', component: () => import('../views/Dashboard.vue') },
            { path: 'finance', name: 'finance', component: () => import('../views/Dashboard.vue') },
            { path: 'books', name: 'books', component: () => import('../views/Dashboard.vue') },
            {
                path: 'profile',
                name: 'profile',
                component: () => import('../views/profile/Profile.vue'),
            },
            {
                path: 'admin',
                name: 'admin',
                component: () => import('../views/admin/AdminDashboard.vue'),
                meta: { requiresAdmin: true },
            },
            // РЕЕСТРЫ ДЕЛ
            {
                path: 'requests',
                name: 'requests',
                component: () => import('../views/applications/ApplicationsList.vue'),
            },
            {
                path: 'applications',
                name: 'applications',
                component: () => import('../views/applications/ApplicationsList.vue'),
            },
            {
                path: 'acceptance',
                name: 'acceptance',
                component: () => import('../views/applications/ApplicationsList.vue'),
            },
            {
                path: 'archive',
                name: 'archive',
                component: () => import('../views/applications/ApplicationsList.vue'),
            },
            {
                path: 'trash',
                name: 'trash',
                component: () => import('../views/applications/ApplicationsList.vue'),
            },
            {
                path: '/applications/:id',
                name: 'application.show',
                component: () => import('../views/applications/ApplicationShow.vue'),
                meta: { requiresAuth: true }
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/requests',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    const token = localStorage.getItem('auth_token');

    // 1. Если маршрут требует авторизации, а токена нет -> перенаправляем на логин
    if (to.matched.some((record) => record.meta.requiresAuth) && !token) {
        return next({ name: 'login' });
    }

    // 2. Если уже авторизован и пытается зайти на логин -> отправляем в систему
    if (to.matched.some((record) => record.meta.guestOnly) && token) {
        return next({ name: 'requests' });
    }

    // 3. Защита админки: доступна только системному администратору
    if (to.matched.some((record) => record.meta.requiresAdmin)) {
        if (!authStore.isAdmin) {
            return next({ name: 'requests' });
        }
    }

    next();
});

export default router;