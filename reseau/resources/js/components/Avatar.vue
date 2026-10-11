<script setup>
import { computed } from 'vue';
import { hue, initials } from '../lib/contacts';

const props = defineProps({ name: { type: String, required: true }, size: { type: String, default: 'md' } });
const style = computed(() => ({ backgroundColor: `hsl(${hue(props.name)} 48% 42%)` }));
</script>

<template>
    <div
        class="grid shrink-0 place-items-center rounded-full font-semibold text-white"
        :class="size === 'lg' ? 'size-14 text-lg' : 'size-11 text-sm'"
        :style="style"
        aria-hidden="true"
    >
        {{ initials(name) }}
    </div>
</template>
