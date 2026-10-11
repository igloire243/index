<template>
    <g>
        <rect class="ill-bg" x="22" y="34" width="156" height="166" rx="16" />
        <rect class="ill-bg2" x="204" y="64" width="96" height="136" rx="16" />
        <path class="ill-line" d="M22 20H120V34" />
        <g transform="rotate(-7 100 90)">
            <rect class="ill-card" x="46" y="52" width="112" height="58" rx="9" />
            <circle cx="68" cy="75" r="11" fill="#a5b4fc" />
            <rect class="ill-text" x="87" y="66" width="52" height="4" rx="2" />
            <rect class="ill-text" x="87" y="75" width="36" height="4" rx="2" />
            <rect class="ill-text" x="87" y="90" width="58" height="3.500" rx="1.700" />
        </g>
        <g transform="rotate(5 100 140)">
            <rect class="ill-card" x="42" y="108" width="112" height="58" rx="9" />
            <circle cx="64" cy="131" r="11" fill="#fcd34d" />
            <rect class="ill-text" x="83" y="122" width="48" height="4" rx="2" />
            <rect class="ill-text" x="83" y="131" width="34" height="4" rx="2" />
            <rect class="ill-text" x="83" y="146" width="56" height="3.500" rx="1.700" />
        </g>
        <g class="ill-float">
            <circle cx="150" cy="150" r="14" fill="none" stroke="#6366f1" stroke-width="4" />
            <line x1="160" y1="160" x2="174" y2="174" stroke="#6366f1" stroke-width="5" stroke-linecap="round" />
        </g>
        <use href="#ill-man" x="216" y="60" width="60" height="140" />
        <use href="#ill-plant" x="268" y="152" width="38" height="47" />
        <use class="ill-float-slow" href="#ill-heart" x="182" y="46" width="16" height="16" />
        <circle cx="30" cy="60" r="3.500" fill="#f59e0b" />
        <circle cx="196" cy="100" r="3" fill="#22c55e" />
        <line class="ill-ground" x1="8" y1="199" x2="312" y2="199" />
    </g>
</template>
