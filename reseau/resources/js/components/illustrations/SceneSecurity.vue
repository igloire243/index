<template>
    <g>
        <rect class="ill-bg" x="60" y="40" width="200" height="160" rx="18" />
        <path class="ill-line" d="M60 24H150V40" />
        <rect class="ill-phone-body" x="116" y="26" width="88" height="173" rx="14" />
        <rect class="ill-screen" x="122" y="34" width="76" height="157" rx="10" />
        <g stroke="#6366f1" stroke-width="3.500" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path d="M138 78V70a4 4 0 0 1 4-4h8M170 66h8a4 4 0 0 1 4 4v8M182 114v8a4 4 0 0 1-4 4h-8M150 126h-8a4 4 0 0 1-4-4v-8" />
        </g>
        <g class="ill-float">
            <circle cx="150" cy="88" r="2.600" fill="#334155" /><circle cx="170" cy="88" r="2.600" fill="#334155" />
            <path d="M151 106q9 7 18 0" stroke="#334155" stroke-width="2.600" fill="none" stroke-linecap="round" />
            <path d="M160 92v8" stroke="#334155" stroke-width="2.200" stroke-linecap="round" />
        </g>
        <rect class="ill-text" x="138" y="144" width="44" height="5" rx="2.500" />
        <rect class="ill-text" x="146" y="154" width="28" height="4" rx="2" />
        <use class="ill-float-slow" href="#ill-check" x="147" y="164" width="26" height="26" />
        <!-- bouclier -->
        <g class="ill-float">
            <path d="M82 80l24-9 24 9v22c0 16-10 26-24 32-14-6-24-16-24-32z" fill="#6366f1" />
            <path d="M94 102l8 8 16-16" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round" />
        </g>
        <!-- cadenas -->
        <g class="ill-float-slow">
            <rect x="214" y="96" width="38" height="30" rx="6" fill="#f59e0b" />
            <path d="M222 96v-8a11 11 0 0 1 22 0v8" stroke="#d97706" stroke-width="5" fill="none" />
            <circle cx="233" cy="111" r="4" fill="#fff" />
        </g>
        <use href="#ill-plant" x="268" y="152" width="38" height="47" />
        <use href="#ill-plant" x="14" y="158" width="32" height="41" />
        <line class="ill-ground" x1="8" y1="199" x2="312" y2="199" />
    </g>
</template>
