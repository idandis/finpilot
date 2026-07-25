<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import * as companyAnalysisRoutes from '@/routes/company-analyses';
import type { CompanyAnalysis } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Analisi aziende', href: companyAnalysisRoutes.index() }],
    },
});

defineProps<{
    analyses: CompanyAnalysis[];
}>();

function formatDate(value: string | null) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium' }).format(new Date(value));
}
</script>

<template>
    <Head title="Analisi aziende" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Analisi aziende"
                description="Indicatori finanziari e checklist di Buffett per decidere se comprare un'azienda"
            />
            <Button as-child>
                <Link :href="companyAnalysisRoutes.create()">Analizza nuova azienda</Link>
            </Button>
        </div>

        <div
            v-if="analyses.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Non hai ancora analizzato nessuna azienda.
        </div>

        <div v-else class="divide-y rounded-lg border">
            <Link
                v-for="analysis in analyses"
                :key="analysis.id"
                :href="companyAnalysisRoutes.show(analysis.id)"
                class="flex items-center justify-between gap-4 p-4 transition-colors hover:bg-muted"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ analysis.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ analysis.symbol }}</p>
                </div>
                <div class="shrink-0 text-right text-sm">
                    <p v-if="analysis.pe_ratio !== null" class="font-medium">P/E {{ analysis.pe_ratio }}</p>
                    <p v-else class="text-muted-foreground">Nessun indicatore ancora</p>
                    <p v-if="formatDate(analysis.updated_at)" class="text-xs text-muted-foreground">
                        Aggiornato il {{ formatDate(analysis.updated_at) }}
                    </p>
                </div>
            </Link>
        </div>
    </div>
</template>
