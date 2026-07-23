<script setup lang="ts">
import { CreditCard, Wifi } from '@lucide/vue';
import { computed } from 'vue';
import { CARD_ICONS } from '@/lib/card-icons';
import type { CardIconName } from '@/lib/card-icons';
import type { Card } from '@/types';

const props = defineProps<{
    card: Card;
}>();

function shade(hex: string, percent: number) {
    const num = parseInt(hex.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const r = Math.min(255, Math.max(0, (num >> 16) + amt));
    const g = Math.min(255, Math.max(0, ((num >> 8) & 0x00ff) + amt));
    const b = Math.min(255, Math.max(0, (num & 0x0000ff) + amt));

    return `#${(0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1)}`;
}

const baseColor = computed(
    () => props.card.color || props.card.financial_account?.color || '#1e293b',
);

const gradient = computed(
    () =>
        `linear-gradient(135deg, ${baseColor.value} 0%, ${shade(baseColor.value, -35)} 100%)`,
);

const iconComponent = computed(() => {
    if (!props.card.icon) {
        return null;
    }

    return CARD_ICONS[props.card.icon as CardIconName] ?? null;
});
</script>

<template>
    <div
        class="relative flex aspect-[1.586/1] w-full flex-col justify-between overflow-hidden rounded-2xl p-5 text-white shadow-lg transition-opacity"
        :class="{ 'opacity-50 grayscale': !card.is_active }"
        :style="{ backgroundImage: gradient }"
    >
        <div class="flex items-start justify-between">
            <div
                class="h-7 w-9 rounded-md bg-gradient-to-br from-yellow-200 via-yellow-300 to-yellow-500/80"
            />
            <Wifi class="size-5 rotate-90 opacity-90" />
        </div>

        <div class="font-mono text-lg tracking-widest text-white/90 sm:text-xl">
            •••• •••• •••• {{ card.last_four_digits ?? '••••' }}
        </div>

        <div class="flex items-end justify-between gap-2">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ card.name }}</p>
                <p class="truncate text-xs text-white/70">
                    {{
                        card.financial_account?.bank_name ??
                        card.financial_account?.name
                    }}
                </p>
            </div>
            <div
                class="flex shrink-0 items-center gap-1 text-sm font-bold tracking-wide uppercase italic"
            >
                <span v-if="card.circuit">{{ card.circuit }}</span>
                <component :is="iconComponent" v-else-if="iconComponent" class="size-4" />
                <CreditCard v-else class="size-4" />
            </div>
        </div>

        <div
            v-if="!card.is_active"
            class="absolute top-3 right-3 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase"
        >
            Disattivata
        </div>
    </div>
</template>
