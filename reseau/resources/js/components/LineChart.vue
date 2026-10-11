<script setup>
import { computed } from 'vue';
import { daysBetween, fmtShort } from '../lib/dates';

const props = defineProps({
    points: { type: Array, required: true }, // [{ date: 'AAAA-MM-JJ', value: number }]
    target: { type: Number, default: null },
    unit: { type: String, default: '' },
    label: { type: String, default: 'Courbe' },
});

const W = 320;
const H = 160;
const pad = { l: 38, r: 10, t: 12, b: 24 };

const model = computed(() => {
    const pts = props.points;
    if (!pts.length) return null;
    const values = pts.map((p) => p.value).concat(props.target != null ? [props.target] : []);
    let min = Math.min(...values);
    let max = Math.max(...values);
    const margin = (max - min) * 0.15 || 1;
    min -= margin;
    max += margin;

    const span = Math.max(daysBetween(pts[0].date, pts[pts.length - 1].date), 1);
    const x = (date) => pad.l + ((daysBetween(pts[0].date, date) / span) * (W - pad.l - pad.r) || 0);
    const y = (v) => pad.t + (1 - (v - min) / (max - min)) * (H - pad.t - pad.b);

    const coords = pts.map((p) => ({ ...p, cx: pts.length === 1 ? (W + pad.l - pad.r) / 2 : x(p.date), cy: y(p.value) }));
    const ticks = [0, 1, 2].map((i) => {
        const v = min + ((max - min) * (2 - i)) / 2;
        return { v: v.toFixed(1), y: y(v) };
    });
    return {
        coords,
        line: coords.map((c) => `${c.cx.toFixed(1)},${c.cy.toFixed(1)}`).join(' '),
        ticks,
        targetY: props.target != null ? y(props.target) : null,
        first: pts[0].date,
        last: pts[pts.length - 1].date,
    };
});
</script>

<template>
    <svg v-if="model" :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" :aria-label="label">
        <g class="text-slate-400 dark:text-slate-600">
            <line v-for="t in model.ticks" :key="t.v" :x1="pad.l" :x2="W - pad.r" :y1="t.y" :y2="t.y" stroke="currentColor" stroke-width="0.5" />
        </g>
        <text v-for="t in model.ticks" :key="'l' + t.v" :x="pad.l - 4" :y="t.y + 3" text-anchor="end" font-size="9" class="fill-slate-500 dark:fill-slate-400">{{ t.v }}</text>

        <g v-if="model.targetY != null">
            <line :x1="pad.l" :x2="W - pad.r" :y1="model.targetY" :y2="model.targetY" stroke-dasharray="4 3" stroke-width="1.2" class="stroke-emerald-500" />
            <text :x="W - pad.r" :y="model.targetY - 3" text-anchor="end" font-size="9" class="fill-emerald-600 dark:fill-emerald-400">objectif {{ target }} {{ unit }}</text>
        </g>

        <polyline v-if="model.coords.length > 1" :points="model.line" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" class="stroke-indigo-500" />
        <circle v-for="c in model.coords" :key="c.date" :cx="c.cx" :cy="c.cy" r="3" class="fill-indigo-500">
            <title>{{ fmtShort(c.date) }} : {{ c.value }} {{ unit }}</title>
        </circle>

        <text :x="pad.l" :y="H - 6" font-size="9" class="fill-slate-500 dark:fill-slate-400">{{ fmtShort(model.first) }}</text>
        <text v-if="model.last !== model.first" :x="W - pad.r" :y="H - 6" text-anchor="end" font-size="9" class="fill-slate-500 dark:fill-slate-400">{{ fmtShort(model.last) }}</text>
    </svg>
</template>
