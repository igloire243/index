<template>
    <g>
        <rect class="ill-bg" x="16" y="48" width="190" height="152" rx="16" />
        <rect class="ill-bg2" x="226" y="30" width="76" height="170" rx="14" />
        <path class="ill-line" d="M16 30H110V48" />
        <!-- maison -->
        <path d="M44 120l50-38 50 38z" fill="#6366f1" />
        <rect x="56" y="120" width="76" height="68" fill="#fff" stroke="#cbd5e1" stroke-width="1.200" />
        <rect x="88" y="150" width="22" height="38" rx="2" fill="#f59e0b" />
        <rect x="64" y="132" width="18" height="18" rx="2" fill="#bae6fd" /><rect x="114" y="132" width="12" height="18" rx="2" fill="#bae6fd" />
        <rect x="124" y="92" width="10" height="20" fill="#818cf8" />
        <!-- voiture -->
        <g>
            <path d="M138 188h58l-6-16q-3-7-10-7h-26q-7 0-10 7z" fill="#22c55e" />
            <rect x="132" y="178" width="70" height="12" rx="5" fill="#16a34a" />
            <circle cx="150" cy="190" r="6.500" fill="#1e293b" /><circle cx="186" cy="190" r="6.500" fill="#1e293b" />
            <circle cx="150" cy="190" r="2.500" fill="#cbd5e1" /><circle cx="186" cy="190" r="2.500" fill="#cbd5e1" />
            <path d="M154 172l4-7h20l4 7z" fill="#bae6fd" />
        </g>
        <!-- clé -->
        <g class="ill-float">
            <circle cx="208" cy="76" r="9" fill="none" stroke="#fbbf24" stroke-width="4" />
            <path d="M215 83l16 16M224 92l5-5M229 97l5-5" stroke="#fbbf24" stroke-width="4" stroke-linecap="round" />
        </g>
        <use href="#ill-woman" x="236" y="59" width="60" height="140" />
        <use class="ill-float-slow" href="#ill-star" x="38" y="62" width="14" height="14" />
        <circle cx="206" cy="130" r="3.500" fill="#ec4899" />
        <line class="ill-ground" x1="8" y1="199" x2="312" y2="199" />
    </g>
</template>
