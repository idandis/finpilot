<script setup lang="ts">
import { Ban } from '@lucide/vue';
import { ACCENT_COLORS, useAppearance } from '@/composables/useAppearance';
import type { AccentColor } from '@/types';

const { accentColor, updateAccentColor } = useAppearance();

const OPTIONS: { value: AccentColor; label: string }[] = [
    { value: 'default', label: 'Predefinito' },
    { value: 'blue', label: 'Blu' },
    { value: 'green', label: 'Verde' },
    { value: 'teal', label: 'Verde acqua' },
    { value: 'purple', label: 'Viola' },
    { value: 'rose', label: 'Rosa' },
    { value: 'orange', label: 'Arancione' },
];
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <button
            v-for="option in OPTIONS"
            :key="option.value"
            type="button"
            class="flex size-8 items-center justify-center rounded-full border transition-shadow"
            :class="accentColor === option.value ? 'ring-2 ring-ring ring-offset-2 ring-offset-background' : 'border-input'"
            :style="option.value !== 'default' ? { backgroundColor: ACCENT_COLORS[option.value].swatch } : {}"
            :title="option.label"
            :aria-label="option.label"
            :aria-pressed="accentColor === option.value"
            @click="updateAccentColor(option.value)"
        >
            <Ban v-if="option.value === 'default'" class="size-4 text-muted-foreground" />
        </button>
    </div>
</template>
