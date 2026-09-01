<script setup lang="ts">
import { Palette } from '@lucide/vue';
import { computed } from 'vue';

const model = defineModel<string>({ required: true });

const presets = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4',
];

// Tutto quello che non è in tavolozza arriva dal selettore di sistema.
const isCustom = computed(() => !presets.includes(model.value.toLowerCase()));
</script>

<template>
    <div class="space-y-2">
        <div class="grid grid-cols-5 gap-2">
            <button
                v-for="color in presets"
                :key="color"
                type="button"
                class="h-8 rounded border-2 transition-transform"
                :class="model.toLowerCase() === color ? 'scale-110 border-foreground' : 'border-transparent'"
                :style="{ backgroundColor: color }"
                :title="color"
                @click="model = color"
            />

            <label
                class="relative flex h-8 cursor-pointer items-center justify-center rounded border-2 transition-transform"
                :class="isCustom ? 'scale-110 border-foreground' : 'border-dashed border-muted-foreground/60'"
                :style="isCustom ? { backgroundColor: model } : undefined"
                title="Colore personalizzato"
            >
                <Palette v-if="!isCustom" class="size-4 text-muted-foreground" />
                <input
                    v-model="model"
                    type="color"
                    class="absolute inset-0 size-full cursor-pointer opacity-0"
                    aria-label="Colore personalizzato"
                />
            </label>
        </div>

        <p class="text-xs text-muted-foreground">
            Colore scelto <span class="tabular-nums">{{ model.toUpperCase() }}</span>
        </p>
    </div>
</template>
