export const applicationRoutes = [
    {
        path: '/applications/:id',
        component: () => import(
            '../views/applications/ApplicationShow.vue'
        ),
        children: [
            {
                path: '',
                redirect: (to) => ({
                    name: 'applications.general',
                    params: { id: to.params.id },
                }),
            },
            {
                path: 'general',
                name: 'applications.general',
                component: () => import(
                    '../modules/applications/general/ApplicationGeneral.vue'
                ),
                meta: { cardModule: 'general' },
            },
            {
                path: 'documents',
                name: 'applications.documents',
                component: () => import(
                    '../modules/applications/documents/ApplicationDocuments.vue'
                ),
                meta: { cardModule: 'documents' },
            },
            {
                path: 'correspondence',
                name: 'applications.correspondence',
                component: () => import(
                    '../modules/applications/correspondence/ApplicationCorrespondence.vue'
                ),
                meta: { cardModule: 'correspondence' },
            },
            {
                path: 'estimate',
                name: 'applications.estimate',
                component: () => import(
                    '../modules/applications/estimate/ApplicationEstimate.vue'
                ),
                meta: { cardModule: 'estimate' },
            },
            {
                path: 'defects',
                name: 'applications.defects',
                component: () => import(
                    '../modules/applications/defects/ApplicationDefects.vue'
                ),
                meta: { cardModule: 'defects' },
            },
            {
                path: 'history',
                name: 'applications.history',
                component: () => import(
                    '../modules/applications/history/ApplicationHistory.vue'
                ),
                meta: { cardModule: 'history' },
            },
        ],
    },
];