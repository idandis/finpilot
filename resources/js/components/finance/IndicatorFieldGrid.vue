<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CompanyAnalysis, CompanyAnalysisScores } from '@/types';

export type IndicatorField = {
    key: keyof CompanyAnalysis;
    label: string;
    suffix: string;
    description: string;
    ranges: string;
    scoreKey?: keyof CompanyAnalysisScores;
};

defineProps<{
    fields: IndicatorField[];
    scores: CompanyAnalysisScores;
    values: Record<string, number | string>;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{ explain: [field: IndicatorField]; update: [key: string, value: number | string] }>();

function scoreClass(score: number | null) {
    if (score === null) {
        return 'text-muted-foreground';
    }

    if (score >= 7) {
        return 'text-green-600';
    }

    return score >= 4 ? 'text-yellow-600' : 'text-red-600';
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div v-for="field in fields" :key="field.key" class="grid gap-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                    <Label :for="field.key">
                        {{ field.label }}<span v-if="field.suffix" class="text-muted-foreground"> ({{ field.suffix }})</span>
                    </Label>
                    <button
                        type="button"
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                        :aria-label="`Spiegazione: ${field.label}`"
                        @click="emit('explain', field)"
                    >
                        ?
                    </button>
                </div>
                <span
                    v-if="field.scoreKey && scores[field.scoreKey] !== null"
                    class="text-xs font-semibold"
                    :class="scoreClass(scores[field.scoreKey])"
                >
                    Voto {{ scores[field.scoreKey] }}/10
                </span>
            </div>
            <Input
                :id="field.key"
                :name="field.key"
                type="number"
                step="any"
                :model-value="values[field.key]"
                @update:model-value="(value) => emit('update', field.key, value)"
            />
            <p v-if="errors[field.key]" class="text-sm text-destructive">{{ errors[field.key] }}</p>
        </div>
    </div>
</template>
