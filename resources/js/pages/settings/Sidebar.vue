<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Switch } from '@/components/ui/switch';
import { useSidebarModules } from '@/composables/useSidebarModules';
import { mainNavItems } from '@/lib/sidebar-nav';
import { edit } from '@/routes/sidebar';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sidebar settings',
                href: edit(),
            },
        ],
    },
});

const { isModuleHidden, setModuleHidden } = useSidebarModules();

function toggleModule(key: string | undefined, enabled: boolean) {
    if (!key) {
        return;
    }

    setModuleHidden(key, !enabled);
}
</script>

<template>
    <Head title="Sidebar settings" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Sidebar"
            description="Scegli quali sezioni e pagine mostrare nel menu laterale"
        />

        <div class="space-y-4">
            <div
                v-for="group in mainNavItems"
                :key="group.key"
                class="rounded-lg border"
            >
                <div class="flex items-center justify-between gap-4 p-3">
                    <div class="flex items-center gap-2">
                        <component
                            :is="group.icon"
                            class="size-4 text-muted-foreground"
                        />
                        <span class="text-sm font-medium">{{
                            group.title
                        }}</span>
                    </div>
                    <Switch
                        :model-value="!isModuleHidden(group.key)"
                        @update:model-value="
                            (checked) => toggleModule(group.key, !!checked)
                        "
                    />
                </div>

                <div v-if="group.items?.length" class="space-y-1 border-t p-2">
                    <div
                        v-for="item in group.items"
                        :key="item.key"
                        class="flex items-center justify-between gap-4 rounded-md px-2 py-1.5 transition-opacity"
                        :class="isModuleHidden(group.key) ? 'opacity-50' : ''"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="item.icon"
                                class="size-4 text-muted-foreground"
                            />
                            <span class="text-sm">{{ item.title }}</span>
                        </div>
                        <Switch
                            :model-value="!isModuleHidden(item.key)"
                            @update:model-value="
                                (checked) => toggleModule(item.key, !!checked)
                            "
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
