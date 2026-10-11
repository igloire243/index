<script setup>
import { computed, onMounted } from 'vue';
import ContactRow from '../components/ContactRow.vue';
import EmptyState from '../components/EmptyState.vue';
import { allDomains, inDomain } from '../lib/contacts';
import { contacts, distTo, loadContacts } from '../stores/contacts';
import { toast } from '../stores/session';

onMounted(() => loadContacts().catch((e) => toast(e.message, 'error')));

const groups = computed(() =>
    allDomains(contacts.list).map((d) => ({
        ...d,
        contacts: contacts.list
            .filter((c) => inDomain(c, d.key))
            .sort((a, b) => {
                const da = distTo(a);
                const db = distTo(b);
                if (da != null && db != null) return da - db;
                if (da != null) return -1;
                if (db != null) return 1;
                return a.name.localeCompare(b.name, 'fr');
            }),
    })),
);
</script>

<template>
    <div class="space-y-2">
        <p class="muted">Pour chaque domaine, la liste de ceux que tu peux appeler (les plus proches d’abord si ta position est connue).</p>
        <EmptyState v-if="contacts.loaded && !groups.length" illustration="contacts" title="Aucun domaine pour l’instant">
            <p>Ajoute des domaines à tes contacts (juridique, santé, transport…) pour retrouver qui appeler.</p>
        </EmptyState>
        <details v-for="g in groups" :key="g.key" class="group rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold [&::-webkit-details-marker]:hidden">
                {{ g.label }}
                <span class="muted font-normal">{{ g.count }} contact{{ g.count > 1 ? 's' : '' }}</span>
            </summary>
            <ul class="space-y-2 px-3 pb-3">
                <li v-for="c in g.contacts" :key="c.id"><ContactRow :contact="c" /></li>
            </ul>
        </details>
    </div>
</template>
