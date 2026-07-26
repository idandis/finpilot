<script setup lang="ts">
import { FONT_STACKS, useAppearance } from '@/composables/useAppearance';
import type { FontChoice } from '@/types';

const { fontFamily, updateFontFamily } = useAppearance();

const OPTIONS: { value: FontChoice; label: string }[] = [
    { value: 'sans', label: 'Predefinito' },
    { value: 'serif', label: 'Serif' },
    { value: 'mono', label: 'Monospace' },
    { value: 'rounded', label: 'Arrotondato' },
];
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="option in OPTIONS"
            :key="option.value"
            type="button"
            class="rounded-md border px-3.5 py-1.5 text-sm transition-colors"
            :class="fontFamily === option.value ? 'border-primary bg-muted' : 'border-input text-muted-foreground hover:bg-muted/60'"
            :style="{ fontFamily: FONT_STACKS[option.value] }"
            :aria-pressed="fontFamily === option.value"
            @click="updateFontFamily(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
