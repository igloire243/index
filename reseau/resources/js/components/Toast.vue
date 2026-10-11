<script setup>
import { session } from '../stores/session';
</script>

<template>
    <Transition name="toast">
    <div
        v-if="session.toast"
        role="status"
        aria-live="polite"
        class="fixed inset-x-4 bottom-[calc(6rem+env(safe-area-inset-bottom))] z-[100] mx-auto max-w-sm rounded-xl px-4 py-3 text-center text-sm shadow-lg"
        :class="session.toast.kind === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'"
    >
        {{ session.toast.message }}
    </div>
    </Transition>
</template>
