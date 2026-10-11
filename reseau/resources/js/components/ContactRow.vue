<script setup>
import { computed } from 'vue';
import { fmtDist } from '../lib/contacts';
import { distTo, openDetail } from '../stores/contacts';
import Avatar from './Avatar.vue';

const props = defineProps({ contact: { type: Object, required: true } });
const dist = computed(() => distTo(props.contact));
const sub = computed(() => [props.contact.role, props.contact.domains.join(', ')].filter(Boolean).join(' · ') || props.contact.place || '');
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-left active:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:active:bg-slate-800"
        @click="openDetail(contact.id)"
    >
        <Avatar :name="contact.name" />
        <div class="min-w-0 flex-1">
            <div class="truncate font-semibold">{{ contact.name }}</div>
            <div v-if="sub" class="muted truncate">{{ sub }}</div>
        </div>
        <div v-if="dist != null" class="shrink-0 text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ fmtDist(dist) }}</div>
    </button>
</template>
