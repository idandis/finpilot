<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, MessageSquarePlus, Sparkles } from '@lucide/vue';
import { nextTick, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import * as aiChatRoutes from '@/routes/ai-chat';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
};

const props = defineProps<{
    conversationId: number | null;
    messages: ChatMessage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI', href: aiChatRoutes.index() }],
    },
});

const messages = reactive<ChatMessage[]>([...props.messages]);
const conversationId = ref<number | null>(props.conversationId);
const input = ref('');
const isSending = ref(false);
const scrollAnchor = ref<HTMLElement | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
const isScrolledToBottom = ref(true);

function startNewChat() {
    messages.splice(0, messages.length);
    conversationId.value = null;
    input.value = '';
}

function escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderMarkdown(content: string): string {
    let html = escapeHtml(content);
    // Handle LaTeX equations first (block and inline)
    html = html.replace(/\\\[([\s\S]*?)\\\]/g, '<div class="my-3 bg-muted/30 p-3 rounded overflow-x-auto text-center font-mono text-sm">\\[$1\\]</div>');
    html = html.replace(/\$\$([\s\S]*?)\$\$/g, '<div class="my-3 bg-muted/30 p-3 rounded overflow-x-auto text-center font-mono text-sm">$$\$1$$</div>');
    html = html.replace(/\$([^\$\n]+?)\$/g, '<code class="bg-muted/50 px-1.5 py-0.5 rounded text-sm">$1</code>');
    // Handle headings
    html = html.replace(/^### (.*?)$/gm, '<h3 class="font-semibold text-base mt-2 mb-1">$1</h3>');
    html = html.replace(/^## (.*?)$/gm, '<h2 class="font-bold text-lg mt-3 mb-2">$1</h2>');
    html = html.replace(/^# (.*?)$/gm, '<h1 class="font-bold text-xl mt-4 mb-2">$1</h1>');
    // Handle bold and italic
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold">$1</strong>');
    html = html.replace(/__(.*?)__/g, '<strong class="font-semibold">$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em class="italic">$1</em>');
    html = html.replace(/_(.*?)_/g, '<em class="italic">$1</em>');
    // Handle inline code (excluding LaTeX)
    html = html.replace(/`([^\$]*?)`/g, '<code class="bg-muted/50 px-1.5 py-0.5 rounded text-sm">$1</code>');
    // Handle line breaks last
    html = html.replace(/\n/g, '<br/>');
    return html;
}

function handleScroll(e: Event) {
    const element = e.target as HTMLElement;
    isScrolledToBottom.value = element.scrollHeight - element.scrollTop - element.clientHeight < 10;
}

function scrollToTop() {
    messagesContainer.value?.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToBottom() {
    nextTick(() => scrollAnchor.value?.scrollIntoView({ behavior: 'smooth' }));
}

const suggestions = [
    'Posso comprare una BMW da 25.000€?',
    'Dove sto spendendo troppo?',
    'Quanto posso investire questo mese?',
    'Perché questo mese ho speso di più?',
];

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function send(text?: string) {
    const content = (text ?? input.value).trim();

    if (! content || isSending.value) {
        return;
    }

    messages.push({ role: 'user', content });
    input.value = '';
    isSending.value = true;
    scrollToBottom();

    try {
        const response = await fetch(aiChatRoutes.store.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body: JSON.stringify({ message: content, conversation_id: conversationId.value }),
        });

        const data = await response.json();

        if (! response.ok) {
            throw new Error(data.error ?? 'Errore sconosciuto');
        }

        conversationId.value = data.conversation_id;
        messages.push(data.message);
    } catch {
        toast.error('Non sono riuscito a rispondere in questo momento. Riprova tra poco.');
    } finally {
        isSending.value = false;
        scrollToBottom();
    }
}
</script>

<template>
    <Head title="AI" />

    <div class="mx-auto flex w-full max-w-[48rem] flex-col space-y-6 p-4 pb-32 md:pb-4">
        <div class="flex items-start justify-between gap-4">
            <Heading title="AI" description="Fai domande sui tuoi dati: finanze, investimenti, task, pasti e lista della spesa." />
            <div class="flex items-center gap-2">
                <Button v-if="messages.length > 0 && !isScrolledToBottom" variant="ghost" size="icon" @click="scrollToBottom" class="md:hidden">
                    <ArrowDown class="size-4" />
                </Button>
                <Button v-if="messages.length > 0" variant="outline" size="sm" @click="startNewChat">
                    <MessageSquarePlus />
                    Nuova chat
                </Button>
            </div>
        </div>

        <div v-if="messages.length === 0" class="flex flex-col items-center gap-6 rounded-xl bg-muted/40 px-6 py-12 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                <Sparkles class="size-6" />
            </div>
            <p class="text-lg font-medium">Cosa vuoi sapere?</p>
            <div class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="suggestion in suggestions"
                    :key="suggestion"
                    type="button"
                    class="rounded-full border bg-background px-3 py-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                    @click="send(suggestion)"
                >
                    {{ suggestion }}
                </button>
            </div>
        </div>

        <div v-else ref="messagesContainer" class="flex flex-col gap-4 overflow-y-auto" @scroll="handleScroll">
            <div
                v-for="(message, index) in messages"
                :key="index"
                class="flex"
                :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
            >
                <div
                    class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm"
                    :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                >
                    <p v-if="message.role === 'user'" class="whitespace-pre-wrap">{{ message.content }}</p>
                    <div v-else class="prose prose-sm prose-invert max-w-none" v-html="renderMarkdown(message.content)" />
                </div>
            </div>
            <p v-if="isSending" class="text-sm text-muted-foreground">Sto pensando…</p>
            <div ref="scrollAnchor" />
        </div>

        <form
            class="fixed bottom-20 left-1/2 z-20 w-full max-w-[calc(48rem-2rem)] -translate-x-1/2 flex items-center gap-2 rounded-full border bg-background/95 p-1.5 shadow-lg backdrop-blur-md md:sticky md:bottom-0"
            @submit.prevent="send()"
        >
            <input
                v-model="input"
                type="text"
                placeholder="Chiedi qualcosa sui tuoi dati…"
                class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                :disabled="isSending"
            />
            <Button type="submit" size="icon" class="shrink-0 rounded-full" :disabled="isSending || ! input.trim()">
                <ArrowUp />
            </Button>
        </form>
    </div>
</template>
