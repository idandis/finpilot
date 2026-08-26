<script setup lang="ts">
import { computed } from 'vue';
import type { MacroIndicatorPoint } from '@/types';

const props = withDefaults(
    defineProps<{
        points: MacroIndicatorPoint[];
        width?: number;
        height?: number;
    }>(),
    { width: 160, height: 40 },
);

const PAD = 3;

const trendColor = computed(() => {
    if (props.points.length < 2) {
        return 'var(--color-muted-foreground)';
    }

    const delta = props.points[props.points.length - 1].value - props.points[0].value;

    if (Math.abs(delta) < 1e-9) {
        return 'var(--color-muted-foreground)';
    }

    return delta > 0 ? '#16a34a' : '#dc2626';
});

function xFor(index: number) {
    const count = props.points.length;

    return count <= 1
        ? props.width / 2
        : PAD + (index / (count - 1)) * (props.width - PAD * 2);
}

function yFor(value: number) {
    const values = props.points.map((point) => point.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const fraction = (value - min) / range;

    return props.height - PAD - fraction * (props.height - PAD * 2);
}

const path = computed(() =>
    props.points
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index)} ${yFor(point.value)}`)
        .join(' '),
);

const lastPoint = computed(() => {
    if (props.points.length === 0) {
        return null;
    }

    const index = props.points.length - 1;

    return { x: xFor(index), y: yFor(props.points[index].value) };
});
</script>

<template>
    <svg
        v-if="points.length >= 2"
        :viewBox="`0 0 ${width} ${height}`"
        :width="width"
        :height="height"
        class="shrink-0"
    >
        <path :d="path" fill="none" :stroke="trendColor" stroke-width="1.5" stroke-linejoin="round" />
        <circle v-if="lastPoint" :cx="lastPoint.x" :cy="lastPoint.y" r="2" :fill="trendColor" />
    </svg>
    <div
        v-else
        class="flex items-center text-xs text-muted-foreground"
        :style="{ width: `${width}px`, height: `${height}px` }"
    >
        Storico insufficiente
    </div>
</template>
