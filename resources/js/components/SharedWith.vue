<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { LogOut, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { avatarStyle } from '@/lib/avatar-color';
import type { SharedPerson } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The avatars of everyone working on something shared (a task board, a
 * shopping list), and the panel behind them: the owner invites by email and
 * removes people, everyone else can see who's in and leave.
 */
const props = withDefaults(defineProps<{
    title: string;
    people: SharedPerson[];
    isOwner: boolean;
    currentUserId: number;
    /** Where the invite form posts, e.g. Controller.storeMember.form(id). */
    inviteForm: RouteFormDefinition<'post'>;
    /** One line telling the owner what an invited person will be able to do. */
    permissionHint: string;
    leaveLabel: string;
    /** Dimensione degli avatar nel pulsante, non del pannello. */
    size?: 'sm' | 'md';
}>(), { size: 'sm' });

const avatarSizeClass = computed(() => props.size === 'md' ? 'size-10 text-sm' : 'size-8 text-xs');

const emit = defineEmits<{
    remove: [person: SharedPerson];
    leave: [person: SharedPerson];
}>();

const isOpen = ref(false);

const currentPerson = computed<SharedPerson | null>(
    () =>
        props.people.find((person) => person.id === props.currentUserId) ??
        null,
);
</script>

<template>
    <button
        type="button"
        class="flex items-center -space-x-2 rounded-full p-0.5 transition hover:bg-muted"
        :title="isOwner ? 'Condividi' : 'Chi ci lavora'"
        @click.stop.prevent="isOpen = true"
    >
        <span
            v-for="person in people"
            :key="person.id"
            class="flex items-center justify-center rounded-full font-semibold text-white ring-2 ring-background"
            :class="avatarSizeClass"
            :style="avatarStyle(person.name)"
        >
            {{ getInitials(person.name) }}
        </span>
        <span
            class="flex items-center justify-center rounded-full border border-dashed border-muted-foreground/40 text-muted-foreground ring-2 ring-background"
            :class="avatarSizeClass"
        >
            <Plus class="size-3.5" />
        </span>
    </button>

    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <Form
                v-if="isOwner"
                v-bind="inviteForm"
                reset-on-success
                class="grid grid-cols-1 gap-2"
                v-slot="{ errors, processing }"
            >
                <Label for="member-email">Email della persona</Label>
                <div class="flex gap-2">
                    <Input
                        id="member-email"
                        name="email"
                        type="email"
                        placeholder="nome@esempio.it"
                        required
                        autofocus
                        class="flex-1"
                    />
                    <Button type="submit" :disabled="processing">Invita</Button>
                </div>
                <InputError :message="errors.email" />
                <p class="text-xs text-muted-foreground">
                    {{ permissionHint }}
                </p>
            </Form>

            <div class="space-y-1">
                <p class="text-sm font-medium">Chi ci lavora</p>
                <div
                    v-for="person in people"
                    :key="person.id"
                    class="flex items-center gap-2 rounded-lg px-1 py-1.5"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                        :style="avatarStyle(person.name)"
                    >
                        {{ getInitials(person.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ person.name }}
                            <span
                                v-if="person.id === currentUserId"
                                class="text-muted-foreground"
                                >(tu)</span
                            >
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ person.email }}
                        </p>
                    </div>
                    <Badge
                        v-if="person.is_owner"
                        variant="secondary"
                        class="shrink-0"
                        >Proprietario</Badge
                    >
                    <Button
                        v-else-if="isOwner"
                        variant="ghost"
                        size="icon-sm"
                        class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        title="Rimuovi"
                        @click="emit('remove', person)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>

            <Button
                v-if="!isOwner && currentPerson"
                variant="outline"
                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="emit('leave', currentPerson)"
            >
                <LogOut />
                {{ leaveLabel }}
            </Button>
        </DialogContent>
    </Dialog>
</template>
