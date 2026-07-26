<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AccentColorPicker from '@/components/AccentColorPicker.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import FontPicker from '@/components/FontPicker.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update the appearance settings for your account"
        />
        <AppearanceTabs />

        <div class="space-y-3">
            <Heading variant="small" title="Colore principale" description="Usato per bottoni, link e altri elementi in evidenza" />
            <AccentColorPicker />
        </div>

        <div class="space-y-3">
            <Heading variant="small" title="Font" description="Il carattere usato in tutta l'applicazione" />
            <FontPicker />
        </div>
    </div>
</template>
