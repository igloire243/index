<script setup>
import { onMounted } from 'vue';
import Icon from '../components/Icon.vue';
import { CHANGELOG, markSeen } from '../lib/changelog';

const fmt = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

onMounted(markSeen);
</script>

<template>
    <div class="space-y-4">
        <p class="muted">Ce qui a été ajouté à Réseau, au fil des mises à jour.</p>
        <ol class="relative space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700">
            <li v-for="e in CHANGELOG" :key="e.id" class="relative">
                <span class="absolute top-1.5 -left-[1.6rem] size-3 rounded-full bg-indigo-500" aria-hidden="true" />
                <div class="card rise space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold">{{ e.title }}</h2>
                        <time class="muted shrink-0 text-xs" :datetime="e.date">{{ fmt(e.date) }}</time>
                    </div>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(it, i) in e.items" :key="i" class="flex gap-2">
                            <Icon name="check" :size="14" class="mt-1 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span>{{ it }}</span>
                        </li>
                    </ul>
                </div>
            </li>
        </ol>
    </div>
</template>
