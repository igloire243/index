<template>
    <g>
        <rect class="ill-bg" x="20" y="40" width="166" height="160" rx="16" />
        <rect class="ill-bg2" x="204" y="60" width="98" height="140" rx="16" />
        <path class="ill-line" d="M20 26H100V40" />
        <!-- coffre-fort -->
        <rect x="46" y="66" width="114" height="112" rx="12" fill="#4f46e5" />
        <rect x="54" y="74" width="98" height="96" rx="8" fill="#6366f1" />
        <rect x="64" y="84" width="78" height="76" rx="6" fill="#4338ca" />
        <g class="ill-float-slow">
            <circle cx="103" cy="122" r="22" fill="#e0e7ff" />
            <circle cx="103" cy="122" r="14" fill="#a5b4fc" />
            <circle cx="103" cy="122" r="4" fill="#4338ca" />
            <path d="M103 110v7M103 127v7M91 122h7M108 122h7" stroke="#4338ca" stroke-width="3" stroke-linecap="round" />
        </g>
        <rect x="56" y="178" width="14" height="8" rx="2" fill="#312e81" />
        <rect x="136" y="178" width="14" height="8" rx="2" fill="#312e81" />
        <!-- dossiers -->
        <g class="ill-float">
            <path d="M138 52h26l5 6h24v30h-55z" fill="#fbbf24" />
            <rect x="138" y="62" width="55" height="26" rx="3" fill="#fcd34d" />
        </g>
        <!-- cadenas -->
        <g class="ill-float-slow">
            <rect x="164" y="138" width="34" height="27" rx="6" fill="#f59e0b" />
            <path d="M171 138v-7a10 10 0 0 1 20 0v7" stroke="#d97706" stroke-width="5" fill="none" />
            <circle cx="181" cy="151" r="3.500" fill="#fff" />
        </g>
        <use href="#ill-woman" x="228" y="59" width="60" height="140" />
        <use href="#ill-plant" x="274" y="152" width="32" height="40" />
        <use class="ill-float" href="#ill-star" x="30" y="52" width="14" height="14" />
        <line class="ill-ground" x1="8" y1="199" x2="312" y2="199" />
    </g>
</template>
