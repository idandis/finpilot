<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="2" y="2" width="96" height="96" rx="26" fill="#7c3aed" />
        <circle cx="36" cy="38" r="6" fill="#ffffff" />
        <circle cx="64" cy="38" r="6" fill="#ffffff" />
        <path
            fill="none"
            stroke="#ffffff"
            stroke-width="9"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M30,70 L30,60 L50,68 L70,60 L70,70"
        />
    </svg>
</template>
