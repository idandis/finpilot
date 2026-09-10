<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <!--
        Su telefono la barra si riduce a un filo d'aria sopra al contenuto:
        le briciole di pane ripetevano il titolo che la pagina scrive già di
        suo, e la freccia della sidebar ora sta nel menu in basso. Su schermo
        largo la barra torna intera, dove il percorso serve a orientarsi e lo
        spazio non manca.
    -->
    <header
        class="flex h-[calc(1rem_+_env(safe-area-inset-top))] shrink-0 items-center gap-2 px-6 transition-[width,height] ease-linear md:h-16 md:border-b md:border-sidebar-border/70 md:px-4 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="hidden items-center gap-2 md:flex">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumbs v-if="breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
