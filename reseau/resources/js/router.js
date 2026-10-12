import { createRouter, createWebHistory } from 'vue-router';
import { session } from './stores/session';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { title: 'Connexion' } },
        { path: '/', redirect: '/contacts' },
        {
            path: '/contacts',
            component: () => import('./views/ContactsLayout.vue'),
            meta: { title: 'Contacts', fab: true },
            children: [
                { path: '', name: 'contacts', component: () => import('./views/ContactsView.vue') },
                { path: 'carte', name: 'carte', component: () => import('./views/MapView.vue') },
                { path: 'domaines', name: 'domaines', component: () => import('./views/DomainsView.vue') },
            ],
        },
        { path: '/carte', redirect: '/contacts/carte' },
        { path: '/domaines', redirect: '/contacts/domaines' },
        {
            path: '/coach',
            component: () => import('./views/CoachLayout.vue'),
            meta: { title: 'Coach' },
            children: [
                { path: '', name: 'coach', component: () => import('./views/CoachView.vue') },
                { path: 'projets', name: 'coach-projets', component: () => import('./views/ProjectsView.vue') },
                { path: 'projets/:id(\\d+)', name: 'coach-projet', component: () => import('./views/ProjectView.vue') },
                { path: 'cv', name: 'coach-cv', component: () => import('./views/CvView.vue') },
            ],
        },
        {
            path: '/sante',
            component: () => import('./views/health/HealthLayout.vue'),
            meta: { title: 'Santé' },
            children: [
                { path: '', name: 'sante', component: () => import('./views/health/TodayView.vue') },
                { path: 'planning', name: 'sante-planning', component: () => import('./views/health/PlanningView.vue') },
                { path: 'repas', name: 'sante-repas', component: () => import('./views/health/MealsView.vue') },
                { path: 'suivi', name: 'sante-suivi', component: () => import('./views/health/ProgressView.vue') },
                { path: 'programmes', name: 'sante-programmes', component: () => import('./views/health/ProgramsView.vue') },
            ],
        },
        {
            path: '/finances',
            component: () => import('./views/finance/FinanceLayout.vue'),
            meta: { title: 'Finances' },
            children: [
                { path: '', name: 'finances', component: () => import('./views/finance/OverviewView.vue') },
                { path: 'fonds', name: 'finances-fonds', component: () => import('./views/finance/AccountsView.vue') },
                { path: 'creances', name: 'finances-creances', component: () => import('./views/finance/DebtsView.vue'), props: { direction: 'owed_to_me' } },
                { path: 'dettes', name: 'finances-dettes', component: () => import('./views/finance/DebtsView.vue'), props: { direction: 'i_owe' } },
                { path: 'biens', name: 'finances-biens', component: () => import('./views/finance/AssetsView.vue') },
            ],
        },
        {
            path: '/coffre',
            component: () => import('./views/vault/VaultLayout.vue'),
            meta: { title: 'Coffre' },
            children: [
                { path: '', name: 'coffre', component: () => import('./views/vault/DossiersView.vue') },
                { path: 'documents', name: 'coffre-documents', component: () => import('./views/vault/DocumentsView.vue') },
                { path: 'mots-de-passe', name: 'coffre-secrets', component: () => import('./views/vault/SecretsView.vue') },
                { path: ':id(\\d+)', name: 'coffre-dossier', component: () => import('./views/vault/DossierView.vue') },
            ],
        },
        { path: '/reglages', name: 'reglages', component: () => import('./views/SettingsView.vue'), meta: { title: 'Réglages' } },
        { path: '/comment-ca-va', name: 'comment-ca-va', component: () => import('./views/StatusView.vue'), meta: { title: 'Comment ça va ?' } },
        { path: '/nouveautes', name: 'nouveautes', component: () => import('./views/ChangelogView.vue'), meta: { title: 'Nouveautés' } },
        { path: '/recherche', name: 'recherche', component: () => import('./views/SearchView.vue'), meta: { title: 'Recherche' } },
        // Lien de partage public : accessible sans compte, le jeton dans l'URL est la seule preuve d'accès.
        { path: '/partage/:token', name: 'partage', component: () => import('./views/ShareView.vue'), meta: { title: 'Document partagé', public: true } },
        { path: '/:pathMatch(.*)*', redirect: '/contacts' },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    if (!session.user && to.name !== 'login' && !to.meta.public) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }
    if (session.user && to.name === 'login') return '/contacts';
});
