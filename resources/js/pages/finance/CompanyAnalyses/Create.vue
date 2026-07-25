<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CompanyAnalysisController from '@/actions/App/Http/Controllers/Finance/CompanyAnalysisController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as companyAnalysisRoutes from '@/routes/company-analyses';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Analisi aziende', href: companyAnalysisRoutes.index() },
            { title: 'Nuova analisi', href: companyAnalysisRoutes.create() },
        ],
    },
});
</script>

<template>
    <Head title="Nuova analisi" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <Heading
            title="Analizza una nuova azienda"
            description="Inserisci nome e simbolo del titolo: potrai poi compilare indicatori e checklist"
        />

        <Form
            v-bind="CompanyAnalysisController.store.form()"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nome azienda</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Es. Apple Inc."
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="symbol">Simbolo</Label>
                <Input
                    id="symbol"
                    name="symbol"
                    required
                    placeholder="Es. AAPL"
                    class="uppercase"
                />
                <p class="text-xs text-muted-foreground">
                    Ticker semplice per titoli USA (es. AAPL). Per altri mercati usa il suffisso richiesto da FMP
                    (es. STLA.MI per Milano); un eventuale suffisso ".US" viene rimosso automaticamente.
                </p>
                <InputError :message="errors.symbol" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">Crea analisi</Button>
            </div>
        </Form>
    </div>
</template>
