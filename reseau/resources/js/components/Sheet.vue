<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({ modelValue: Boolean, title: String });
const emit = defineEmits(['update:modelValue']);
const dlg = ref(null);

// <dialog> natif : focus piégé, touche Échap et fond assombri gérés par le navigateur.
watch(
    () => props.modelValue,
    async (open) => {
        await nextTick();
        if (!dlg.value) return;
        if (open && !dlg.value.open) dlg.value.showModal();
        if (!open && dlg.value.open) dlg.value.close();
    },
    { immediate: true, flush: 'post' },
);

const onClose = () => emit('update:modelValue', false);
const onBackdrop = (e) => {
    if (e.target === dlg.value) dlg.value.close();
};
</script>

<template>
    <dialog
        ref="dlg"
        class="m-auto mb-0 max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-0 text-slate-900 shadow-2xl sm:mb-auto sm:rounded-3xl dark:bg-slate-900 dark:text-slate-100"
        :aria-label="title"
        @close="onClose"
        @click="onBackdrop"
    >
        <div v-if="modelValue" class="p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]">
            <h2 v-if="title" class="mb-4 text-lg font-semibold">{{ title }}</h2>
            <slot />
        </div>
    </dialog>
</template>
