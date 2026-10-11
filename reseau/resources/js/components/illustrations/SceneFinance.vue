<template>
    <g>
        <rect class="ill-bg" x="20" y="40" width="166" height="160" rx="16" />
        <rect class="ill-bg2" x="204" y="60" width="98" height="140" rx="16" />
        <path class="ill-line" d="M20 26H100V40" />
        <!-- graphique -->
        <rect class="ill-card" x="36" y="56" width="104" height="76" rx="10" />
        <rect x="50" y="98" width="12" height="24" rx="2" fill="#a5b4fc" /><rect x="68" y="88" width="12" height="34" rx="2" fill="#818cf8" /><rect x="86" y="78" width="12" height="44" rx="2" fill="#6366f1" /><rect x="104" y="68" width="12" height="54" rx="2" fill="#4f46e5" />
        <!-- tirelire -->
        <g class="ill-float">
            <ellipse cx="156" cy="156" rx="30" ry="22" fill="#f472b6" />
            <circle cx="180" cy="148" r="9" fill="#f472b6" /><circle cx="184" cy="146" r="1.800" fill="#1e293b" />
            <path d="M178 140l-3-9 8 5z" fill="#ec4899" />
            <rect x="136" y="171" width="8" height="12" rx="3" fill="#ec4899" /><rect x="162" y="171" width="8" height="12" rx="3" fill="#ec4899" />
            <rect x="148" y="134" width="16" height="3.500" rx="1.700" fill="#be185d" />
            <path d="M126 152q-9-2-9 8" stroke="#ec4899" stroke-width="3" fill="none" stroke-linecap="round" />
        </g>
        <!-- pièces -->
        <g class="ill-float-slow">
            <ellipse cx="154" cy="104" rx="10" ry="4" fill="#f59e0b" /><rect x="144" y="100" width="20" height="4" fill="#fbbf24" /><ellipse cx="154" cy="100" rx="10" ry="4" fill="#fcd34d" />
        </g>
        <g>
            <ellipse cx="62" cy="186" rx="13" ry="4.500" fill="#d97706" /><rect x="49" y="178" width="26" height="8" fill="#f59e0b" /><ellipse cx="62" cy="178" rx="13" ry="4.500" fill="#fbbf24" />
            <ellipse cx="62" cy="172" rx="13" ry="4.500" fill="#d97706" /><rect x="49" y="164" width="26" height="8" fill="#f59e0b" /><ellipse cx="62" cy="164" rx="13" ry="4.500" fill="#fcd34d" />
            <ellipse cx="92" cy="190" rx="13" ry="4.500" fill="#d97706" /><rect x="79" y="182" width="26" height="8" fill="#f59e0b" /><ellipse cx="92" cy="182" rx="13" ry="4.500" fill="#fbbf24" />
        </g>
        <use href="#ill-man" x="226" y="59" width="60" height="140" />
        <use href="#ill-plant" x="274" y="152" width="32" height="40" />
        <use class="ill-float" href="#ill-star" x="190" y="68" width="14" height="14" />
        <circle cx="30" cy="70" r="3.500" fill="#22c55e" />
        <line class="ill-ground" x1="8" y1="199" x2="312" y2="199" />
    </g>
</template>
