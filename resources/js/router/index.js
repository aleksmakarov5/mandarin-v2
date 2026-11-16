import { createRouter, createWebHistory } from 'vue-router';

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
            { path: '', redirect: '/requests' },
            { path: 'requests', name: 'requests', component: () => import('../views/Dashboard.vue') },
            { path: 'applications', name: 'applications', component: () => import('../views/Dashboard.vue') },
            { path: 'acceptance', name: 'acceptance', component: () => import('../views/Dashboard.vue') },
            { path: 'archive', name: 'archive', component: () => import('../views/Dashboard.vue') },
            { path: 'trash', name: 'trash', component: () => import('../views/Dashboard.vue') },
            { path: 'mail', name: 'mail', component: () => import('../views/Dashboard.vue') },
            { path: 'chat', name: 'chat', component: () => import('../views/Dashboard.vue') },
            { path: 'warehouse', name: 'warehouse', component: () => import('../views/Dashboard.vue') },
            { path: 'finance', name: 'finance', component: () => import('../views/Dashboard.vue') },
            { path: 'books', name: 'books', component: () => import('../views/Dashboard.vue') },
            {
                path: 'admin',
                name: 'admin',
                component: () => import('../views/admin/AdminDashboard.vue'),
                meta: { roles: ['sys_admin', 'company_admin'] },
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

export default router;