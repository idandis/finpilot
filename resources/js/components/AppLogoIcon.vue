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
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="16"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M22,80 L22,26 L50,54 L78,26 L78,80"
        />
    </svg>
</template>
