<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InvestmentNewsController from '@/actions/App/Http/Controllers/Finance/InvestmentNewsController';
import InvestmentNoteController from '@/actions/App/Http/Controllers/Finance/InvestmentNoteController';
import InvestmentPositionsTables from '@/components/finance/InvestmentPositionsTables.vue';
import PortfolioHistoryChart from '@/components/finance/PortfolioHistoryChart.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as investmentRoutes from '@/routes/investments';
import * as noteRoutes from '@/routes/investments/notes';
import type {
    InvestmentNews,
    InvestmentNote,
    InvestmentPositions,
    PortfolioHistory,
    PositionTransaction,
} from '@/types';

const props = defineProps<{
    isin: string;
    instrumentName: string;
    positions: InvestmentPositions;
    portfolioHistory: PortfolioHistory;
    transactions: PositionTransaction[];
    notes: InvestmentNote[];
    news: InvestmentNews;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Investimenti', href: investmentRoutes.index() }],
    },
});

function formatCurrency(value: string | number) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value));
}

function formatQuantity(value: string) {
    return new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 8,
    }).format(Number(value));
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString('it-IT');
}

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('it-IT', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function deleteNote(noteId: number) {
    if (confirm('Eliminare questa nota?')) {
        router.delete(noteRoutes.destroy(noteId).url, {
            preserveScroll: true,
        });
    }
}

function sentiment(polarity: number | null) {
    if (polarity === null) {
        return null;
    }

    if (polarity > 0.15) {
        return { label: 'Positiva', class: 'text-green-600' };
    }

    if (polarity < -0.15) {
        return { label: 'Negativa', class: 'text-red-600' };
    }

    return { label: 'Neutra', class: 'text-muted-foreground' };
}

function truncate(text: string | null, length = 400) {
    if (!text) {
        return null;
    }

    return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
}

const MONTHS = [
    'Gennaio',
    'Febbraio',
    'Marzo',
    'Aprile',
    'Maggio',
    'Giugno',
    'Luglio',
    'Agosto',
    'Settembre',
    'Ottobre',
    'Novembre',
    'Dicembre',
];

const newsYearFilter = ref('');
const newsMonthFilter = ref('');
const newsDayFilter = ref('');

const newsYears = computed(() =>
    Array.from(
        new Set(props.news.articles.map((article) => new Date(article.published_at).getFullYear())),
    ).sort((a, b) => b - a),
);

const filteredNewsArticles = computed(() =>
    props.news.articles.filter((article) => {
        const date = new Date(article.published_at);

        if (newsYearFilter.value && date.getFullYear() !== Number(newsYearFilter.value)) {
            return false;
        }

        if (newsMonthFilter.value && date.getMonth() + 1 !== Number(newsMonthFilter.value)) {
            return false;
        }

        if (newsDayFilter.value && date.getDate() !== Number(newsDayFilter.value)) {
            return false;
        }

        return true;
    }),
);

const NEWS_PAGE_SIZE = 5;
const newsPage = ref(1);

const newsPageCount = computed(() =>
    Math.max(1, Math.ceil(filteredNewsArticles.value.length / NEWS_PAGE_SIZE)),
);

const paginatedNewsArticles = computed(() => {
    const start = (newsPage.value - 1) * NEWS_PAGE_SIZE;

    return filteredNewsArticles.value.slice(start, start + NEWS_PAGE_SIZE);
});

watch([newsYearFilter, newsMonthFilter, newsDayFilter], () => {
    newsPage.value = 1;
});
</script>

<template>
    <Head :title="instrumentName" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-8 p-4">
        <Heading :title="instrumentName" :description="isin" />

        <div>
            <h3 class="mb-4 text-sm font-medium">Andamento</h3>
            <PortfolioHistoryChart :history="portfolioHistory" />
        </div>

        <InvestmentPositionsTables :positions="positions" :linkable="false" />

        <div>
            <h3 class="mb-4 text-sm font-medium">Storico transazioni</h3>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Data</TableHead>
                        <TableHead>Descrizione</TableHead>
                        <TableHead class="text-right">Quantità</TableHead>
                        <TableHead class="text-right">Importo</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="transaction in transactions" :key="transaction.id">
                        <TableCell>{{ formatDate(transaction.transaction_date) }}</TableCell>
                        <TableCell class="max-w-xs truncate">{{ transaction.description }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ transaction.quantity ? formatQuantity(transaction.quantity) : '—' }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium"
                            :class="transaction.direction === 'expense' ? 'text-red-600' : 'text-green-600'"
                        >
                            {{ transaction.direction === 'expense' ? '−' : '+'
                            }}{{ formatCurrency(transaction.amount) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Tabs default-value="note">
            <TabsList>
                <TabsTrigger value="note">Note</TabsTrigger>
                <TabsTrigger value="news">News</TabsTrigger>
            </TabsList>

            <TabsContent value="note" class="max-w-lg pt-4">
                <Form
                    v-bind="InvestmentNoteController.store.form(isin)"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="mb-4 space-y-2"
                >
                    <textarea
                        name="body"
                        required
                        rows="3"
                        placeholder="Aggiungi una nota su questa posizione..."
                        class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full min-w-0 resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm"
                    />
                    <InputError :message="errors.body" />
                    <Button type="submit" size="sm" :disabled="processing">Aggiungi nota</Button>
                </Form>

                <div v-if="notes.length === 0" class="text-sm text-muted-foreground">
                    Nessuna nota per questa posizione.
                </div>
                <ul v-else class="space-y-3">
                    <li v-for="note in notes" :key="note.id" class="rounded-lg border p-3">
                        <p class="text-sm whitespace-pre-wrap">{{ note.body }}</p>
                        <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span>{{ formatDateTime(note.created_at) }}</span>
                            <button
                                type="button"
                                class="underline hover:text-foreground"
                                @click="deleteNote(note.id)"
                            >
                                Elimina
                            </button>
                        </div>
                    </li>
                </ul>
            </TabsContent>

            <TabsContent value="news" class="pt-4">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <p class="text-sm text-muted-foreground">
                        <template v-if="!news.resolvable">
                            Le notizie saranno disponibili dopo il primo aggiornamento del prezzo di questo strumento.
                        </template>
                        <template v-else-if="news.fetchedAt">
                            Ultimo aggiornamento: {{ formatDateTime(news.fetchedAt) }}. Il caricamento consuma
                            parte del budget giornaliero condiviso con l'aggiornamento dei prezzi.
                        </template>
                        <template v-else>
                            Notizie non ancora caricate. Il caricamento consuma parte del budget giornaliero
                            condiviso con l'aggiornamento dei prezzi.
                        </template>
                    </p>
                    <Form
                        v-if="news.resolvable"
                        v-bind="InvestmentNewsController.refresh.form(isin)"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            class="shrink-0"
                        >
                            {{ processing ? 'Caricamento…' : (news.fetchedAt ? 'Aggiorna notizie' : 'Carica notizie') }}
                        </Button>
                    </Form>
                </div>

                <div v-if="news.highlights.length > 0" class="mb-6">
                    <h4 class="mb-3 text-sm font-medium">Punti salienti</h4>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Le notizie più rilevanti (per intensità del sentiment) tra quelle caricate, con i
                        numeri estratti automaticamente dal titolo e dal testo.
                    </p>
                    <ul class="space-y-2">
                        <li
                            v-for="highlight in news.highlights"
                            :key="highlight.id"
                            class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
                        >
                            <span
                                v-if="sentiment(highlight.sentiment_polarity)"
                                class="text-xs font-medium"
                                :class="sentiment(highlight.sentiment_polarity)?.class"
                            >
                                {{ sentiment(highlight.sentiment_polarity)?.label }}
                            </span>
                            <a
                                v-if="highlight.url"
                                :href="highlight.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium hover:underline"
                            >
                                {{ highlight.title }}
                            </a>
                            <span v-else class="font-medium">{{ highlight.title }}</span>
                            <Badge v-for="figure in highlight.figures" :key="figure" variant="secondary">
                                {{ figure }}
                            </Badge>
                            <span class="ml-auto shrink-0 text-xs text-muted-foreground">
                                {{ formatDateTime(highlight.published_at) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="news.articles.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Nessuna notizia trovata per questo strumento.
                </div>

                <template v-else>
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <select
                            v-model="newsYearFilter"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Tutti gli anni</option>
                            <option v-for="year in newsYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                        <select
                            v-model="newsMonthFilter"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Tutti i mesi</option>
                            <option v-for="(month, index) in MONTHS" :key="month" :value="index + 1">
                                {{ month }}
                            </option>
                        </select>
                        <select
                            v-model="newsDayFilter"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Tutti i giorni</option>
                            <option v-for="day in 31" :key="day" :value="day">{{ day }}</option>
                        </select>
                        <button
                            v-if="newsYearFilter || newsMonthFilter || newsDayFilter"
                            type="button"
                            class="text-sm text-muted-foreground underline hover:text-foreground"
                            @click="newsYearFilter = ''; newsMonthFilter = ''; newsDayFilter = ''"
                        >
                            Rimuovi filtri
                        </button>
                    </div>

                    <div
                        v-if="filteredNewsArticles.length === 0"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Nessuna notizia trovata per i filtri selezionati.
                    </div>

                    <ul v-else class="space-y-4">
                        <li v-for="article in paginatedNewsArticles" :key="article.id" class="rounded-lg border p-4">
                        <div class="flex items-start justify-between gap-4">
                            <a
                                v-if="article.url"
                                :href="article.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium hover:underline"
                            >
                                {{ article.title }}
                            </a>
                            <span v-else class="font-medium">{{ article.title }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ formatDateTime(article.published_at) }}
                            </span>
                        </div>

                        <div
                            v-if="sentiment(article.sentiment_polarity) || (article.tags && article.tags.length > 0)"
                            class="mt-2 flex flex-wrap items-center gap-2"
                        >
                            <span
                                v-if="sentiment(article.sentiment_polarity)"
                                class="text-xs font-medium"
                                :class="sentiment(article.sentiment_polarity)?.class"
                            >
                                {{ sentiment(article.sentiment_polarity)?.label }}
                            </span>
                            <Badge v-for="tag in article.tags ?? []" :key="tag" variant="secondary">
                                {{ tag }}
                            </Badge>
                        </div>

                        <p v-if="truncate(article.content)" class="mt-2 text-sm text-muted-foreground">
                            {{ truncate(article.content) }}
                        </p>
                    </li>
                    </ul>

                    <div v-if="newsPageCount > 1" class="mt-4 flex items-center justify-center gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="newsPage === 1"
                            @click="newsPage--"
                        >
                            Precedente
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            Pagina {{ newsPage }} di {{ newsPageCount }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="newsPage === newsPageCount"
                            @click="newsPage++"
                        >
                            Successiva
                        </Button>
                    </div>
                </template>
            </TabsContent>
        </Tabs>
    </div>
</template>
