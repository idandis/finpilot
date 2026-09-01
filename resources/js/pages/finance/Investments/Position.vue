<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CompanyAnalysisController from '@/actions/App/Http/Controllers/Finance/CompanyAnalysisController';
import InvestmentDecisionController from '@/actions/App/Http/Controllers/Finance/InvestmentDecisionController';
import InvestmentEventController from '@/actions/App/Http/Controllers/Finance/InvestmentEventController';
import InvestmentJournalEntryController from '@/actions/App/Http/Controllers/Finance/InvestmentJournalEntryController';
import InvestmentNewsController from '@/actions/App/Http/Controllers/Finance/InvestmentNewsController';
import InvestmentNoteController from '@/actions/App/Http/Controllers/Finance/InvestmentNoteController';
import InvestmentReviewController from '@/actions/App/Http/Controllers/Finance/InvestmentReviewController';
import InvestmentPositionsTables from '@/components/finance/InvestmentPositionsTables.vue';
import PortfolioHistoryChart from '@/components/finance/PortfolioHistoryChart.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as companyAnalysisRoutes from '@/routes/company-analyses';
import * as investmentRoutes from '@/routes/investments';
import * as noteRoutes from '@/routes/investments/notes';
import type {
    CompanyAnalysis,
    CompanyAnalysisOption,
    InvestmentDecision,
    InvestmentEvent,
    InvestmentEventType,
    InvestmentMotivationOption,
    InvestmentNews,
    InvestmentNote,
    InvestmentPositions,
    InvestmentReview,
    InvestmentReviewDecision,
    InvestmentTimeHorizon,
    JournalEvent,
    JournalEventType,
    PortfolioHistory,
    PositionTransaction,
    ValuationVerdict,
} from '@/types';

const props = defineProps<{
    isin: string;
    instrumentName: string;
    positions: InvestmentPositions;
    portfolioHistory: PortfolioHistory;
    transactions: PositionTransaction[];
    notes: InvestmentNote[];
    news: InvestmentNews;
    motivationOptions: InvestmentMotivationOption[];
    investment: InvestmentDecision | null;
    fundamentals: CompanyAnalysis | null;
    journal: JournalEvent[];
    companyAnalyses: CompanyAnalysisOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Investimenti', href: investmentRoutes.index() }],
    },
});

const TIME_HORIZON_LABELS: Record<InvestmentTimeHorizon, string> = {
    short: 'Breve termine (< 1 anno)',
    medium: 'Medio termine (1-3 anni)',
    long: 'Lungo termine (> 3 anni)',
};

const DECISION_LABELS: Record<InvestmentReviewDecision, string> = {
    hold: 'Mantieni',
    increase: 'Aumenta',
    reduce: 'Riduci',
    sell: 'Vendi',
    watch: 'Osserva',
};

const EVENT_TYPE_LABELS: Record<InvestmentEventType, string> = {
    earnings_quarterly: 'Trimestrale',
    earnings_annual: 'Annuale',
    guidance: 'Guidance',
    investor_day: 'Investor Day',
    acquisition: 'Acquisizione',
    management_change: 'Cambio management',
    regulatory: 'Aggiornamento normativo',
    other: 'Altro',
};

const JOURNAL_TYPE_LABELS: Record<JournalEventType, string> = {
    buy: 'Acquisto',
    increase: 'Incremento',
    reduce: 'Riduzione',
    sell: 'Vendita',
    dividend: 'Dividendo',
    note: 'Nota',
    review: 'Review',
};

const JOURNAL_TYPE_BADGE_VARIANT: Record<JournalEventType, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    buy: 'default',
    increase: 'default',
    reduce: 'destructive',
    sell: 'destructive',
    dividend: 'secondary',
    note: 'outline',
    review: 'secondary',
};

const VERDICT_META: Record<ValuationVerdict, { emoji: string; label: string; class: string }> = {
    undervalued: { emoji: '🟢', label: 'Sottovalutata', class: 'text-green-600' },
    fair: { emoji: '🟡', label: 'Correttamente valutata', class: 'text-yellow-600' },
    expensive: { emoji: '🟠', label: 'Cara', class: 'text-orange-600' },
    very_expensive: { emoji: '🔴', label: 'Molto cara', class: 'text-red-600' },
};

const FUNDAMENTALS_SUMMARY_FIELDS: Array<{ key: keyof CompanyAnalysis; label: string; suffix: string }> = [
    { key: 'revenue_growth', label: 'Crescita ricavi', suffix: '%' },
    { key: 'eps_growth', label: 'Crescita utili (EPS)', suffix: '%' },
    { key: 'operating_margin', label: 'Margine operativo', suffix: '%' },
    { key: 'net_margin', label: 'Margine netto', suffix: '%' },
    { key: 'roe', label: 'ROE', suffix: '%' },
    { key: 'roic', label: 'ROIC', suffix: '%' },
    { key: 'debt_to_ebitda', label: 'Debito/EBITDA', suffix: 'x' },
    { key: 'pe_ratio', label: 'P/E', suffix: 'x' },
];

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
const textareaClass =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive w-full min-w-0 resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

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

function formatIndicator(value: number | null, suffix: string) {
    return value === null ? '—' : `${new Intl.NumberFormat('it-IT', { maximumFractionDigits: 2 }).format(value)}${suffix}`;
}

function deleteNote(noteId: number) {
    if (confirm('Eliminare questa nota?')) {
        router.delete(noteRoutes.destroy(noteId).url, {
            preserveScroll: true,
        });
    }
}

function deleteReview(reviewId: number) {
    if (confirm('Eliminare questa review?')) {
        router.delete(InvestmentReviewController.destroy(reviewId).url, {
            preserveScroll: true,
        });
    }
}

function deleteEvent(eventId: number) {
    if (confirm('Eliminare questo evento?')) {
        router.delete(InvestmentEventController.destroy(eventId).url, {
            preserveScroll: true,
        });
    }
}

function deleteJournalEntry(journalEntryId: number) {
    if (confirm('Eliminare questa nota dal journal?')) {
        router.delete(InvestmentJournalEntryController.destroy(journalEntryId).url, {
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

const activeTab = ref('overview');
const isAddEventOpen = ref(false);
const isAddReviewOpen = ref(false);
const isAddJournalOpen = ref(false);

const tabs = [
    { value: 'overview', label: 'Overview' },
    { value: 'thesis', label: 'Thesis' },
    { value: 'fundamentals', label: 'Fundamentals' },
    { value: 'events', label: 'Eventi' },
    { value: 'reviews', label: 'Reviews' },
    { value: 'journal', label: 'Journal' },
];

const motivationNoteEdit = ref(props.investment?.motivation_note ?? '');
const thesisEdit = ref(props.investment?.thesis ?? '');
const sellConditionsEdit = ref(props.investment?.sell_conditions ?? '');
const nextReviewNoteEdit = ref(props.investment?.next_review_note ?? '');

watch(
    () => props.investment,
    (investment) => {
        motivationNoteEdit.value = investment?.motivation_note ?? '';
        thesisEdit.value = investment?.thesis ?? '';
        sellConditionsEdit.value = investment?.sell_conditions ?? '';
        nextReviewNoteEdit.value = investment?.next_review_note ?? '';
    },
);

type DetailPanel =
    | { kind: 'note'; note: InvestmentNote }
    | { kind: 'event'; event: InvestmentEvent }
    | { kind: 'review'; review: InvestmentReview }
    | { kind: 'journal'; entry: JournalEvent };

const activeDetail = ref<DetailPanel | null>(null);

function closeDetail() {
    activeDetail.value = null;
}

function deleteNoteAndClose(noteId: number) {
    deleteNote(noteId);
    closeDetail();
}

function deleteEventAndClose(eventId: number) {
    deleteEvent(eventId);
    closeDetail();
}

function deleteReviewAndClose(reviewId: number) {
    deleteReview(reviewId);
    closeDetail();
}

function deleteJournalEntryAndClose(journalEntryId: number) {
    deleteJournalEntry(journalEntryId);
    closeDetail();
}
</script>

<template>
    <Head :title="instrumentName" />

    <div class="mx-auto flex w-full max-w-5xl flex-col space-y-8 p-4">
        <Heading :title="instrumentName" :description="isin" />

        <Tabs v-model="activeTab" class="flex flex-col gap-6">
            <!-- Pillole scorrevoli, come i mesi del budget mensile -->
            <TabsList
                class="-mx-4 flex h-auto w-full flex-none flex-row items-center justify-start gap-2 overflow-x-auto rounded-none bg-transparent px-4 pb-1"
            >
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.value"
                    :value="tab.value"
                    class="h-auto shrink-0 whitespace-nowrap rounded-full bg-muted/50 px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground data-[state=active]:bg-primary data-[state=active]:font-medium data-[state=active]:text-primary-foreground data-[state=active]:shadow-none"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <div class="min-w-0 space-y-8">
                <TabsContent value="overview" class="space-y-8">
                    <div
                        v-if="!investment"
                        class="flex items-center justify-between gap-4 rounded-lg border border-dashed p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Non hai ancora compilato la scheda decisionale per questo investimento.
                        </p>
                        <Button variant="outline" size="sm" @click="activeTab = 'thesis'">
                            Compila la scheda
                        </Button>
                    </div>

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
                                    <TableHead class="text-right">Prezzo unitario</TableHead>
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
                                    <TableCell class="text-right tabular-nums">
                                        {{ transaction.unit_price ? formatCurrency(transaction.unit_price) : '—' }}
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

                        <TabsContent value="note" class="pt-4">
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
                                    :class="textareaClass"
                                />
                                <InputError :message="errors.body" />
                                <Button type="submit" size="sm" :disabled="processing">Aggiungi nota</Button>
                            </Form>

                            <div v-if="notes.length === 0" class="text-sm text-muted-foreground">
                                Nessuna nota per questa posizione.
                            </div>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="note in notes"
                                    :key="note.id"
                                    class="cursor-pointer rounded-lg bg-muted dark:bg-muted/40 p-3 transition-colors hover:bg-muted/70"
                                    @click="activeDetail = { kind: 'note', note }"
                                >
                                    <p class="line-clamp-3 text-sm whitespace-pre-wrap">{{ note.body }}</p>
                                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                        <span>{{ formatDateTime(note.created_at) }}</span>
                                        <button
                                            type="button"
                                            class="underline hover:text-foreground"
                                            @click.stop="deleteNote(note.id)"
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
                                        Le notizie saranno disponibili dopo il primo aggiornamento del prezzo di
                                        questo strumento.
                                    </template>
                                    <template v-else-if="news.fetchedAt">
                                        Ultimo aggiornamento: {{ formatDateTime(news.fetchedAt) }}. Il caricamento
                                        consuma parte del budget giornaliero condiviso con l'aggiornamento dei
                                        prezzi.
                                    </template>
                                    <template v-else>
                                        Notizie non ancora caricate. Il caricamento consuma parte del budget
                                        giornaliero condiviso con l'aggiornamento dei prezzi.
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
                                        {{
                                            processing
                                                ? 'Caricamento…'
                                                : news.fetchedAt
                                                  ? 'Aggiorna notizie'
                                                  : 'Carica notizie'
                                        }}
                                    </Button>
                                </Form>
                            </div>

                            <div v-if="news.highlights.length > 0" class="mb-6">
                                <h4 class="mb-3 text-sm font-medium">Punti salienti</h4>
                                <p class="mb-3 text-xs text-muted-foreground">
                                    Le notizie più rilevanti (per intensità del sentiment) tra quelle caricate, con
                                    i numeri estratti automaticamente dal titolo e dal testo.
                                </p>
                                <ul class="space-y-2">
                                    <li
                                        v-for="highlight in news.highlights"
                                        :key="highlight.id"
                                        class="flex flex-wrap items-center gap-2 rounded-lg bg-muted dark:bg-muted/40 p-3 text-sm"
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
                                    <select v-model="newsYearFilter" :class="selectClass" class="w-auto">
                                        <option value="">Tutti gli anni</option>
                                        <option v-for="year in newsYears" :key="year" :value="year">{{ year }}</option>
                                    </select>
                                    <select v-model="newsMonthFilter" :class="selectClass" class="w-auto">
                                        <option value="">Tutti i mesi</option>
                                        <option v-for="(month, index) in MONTHS" :key="month" :value="index + 1">
                                            {{ month }}
                                        </option>
                                    </select>
                                    <select v-model="newsDayFilter" :class="selectClass" class="w-auto">
                                        <option value="">Tutti i giorni</option>
                                        <option v-for="day in 31" :key="day" :value="day">{{ day }}</option>
                                    </select>
                                    <button
                                        v-if="newsYearFilter || newsMonthFilter || newsDayFilter"
                                        type="button"
                                        class="text-sm text-muted-foreground underline hover:text-foreground"
                                        @click="
                                            newsYearFilter = '';
                                            newsMonthFilter = '';
                                            newsDayFilter = '';
                                        "
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
                                    <li
                                        v-for="article in paginatedNewsArticles"
                                        :key="article.id"
                                        class="rounded-lg bg-muted dark:bg-muted/40 p-4"
                                    >
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
                                            v-if="
                                                sentiment(article.sentiment_polarity) ||
                                                (article.tags && article.tags.length > 0)
                                            "
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
                </TabsContent>

                <TabsContent value="thesis" class="space-y-4">
                    <template v-if="!investment">
                        <p class="text-sm text-muted-foreground">
                            Prima di continuare a seguire questo investimento, compila la scheda decisionale: solo
                            le informazioni che non possono essere recuperate automaticamente.
                        </p>
                        <Form
                            v-bind="InvestmentDecisionController.store.form(isin)"
                            v-slot="{ errors, processing }"
                            class="space-y-4"
                        >
                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Motivazione dell'acquisto</h3>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="option in motivationOptions"
                                        :key="option.key"
                                        class="flex items-start gap-2 rounded-lg bg-background/50 px-3 py-2 text-sm"
                                    >
                                        <input type="checkbox" name="motivation_reasons[]" :value="option.key" class="mt-0.5 size-4 shrink-0 rounded border-input" />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <div>
                                    <Label for="motivation_note">Nota sulla motivazione (opzionale)</Label>
                                    <textarea id="motivation_note" name="motivation_note" rows="2" :class="textareaClass" class="mt-2" />
                                </div>
                            </section>

                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Tesi e condizioni di uscita</h3>
                                <div>
                                    <Label for="thesis">Tesi di investimento</Label>
                                    <textarea id="thesis" name="thesis" required rows="4" :class="textareaClass" class="mt-2" />
                                    <InputError :message="errors.thesis" />
                                </div>
                                <div>
                                    <Label for="sell_conditions">Condizioni che ti farebbero cambiare idea</Label>
                                    <textarea id="sell_conditions" name="sell_conditions" rows="3" :class="textareaClass" class="mt-2" />
                                </div>
                            </section>

                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Orizzonte e convinzione</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="time_horizon">Orizzonte temporale</Label>
                                        <select id="time_horizon" name="time_horizon" required :class="selectClass" class="mt-2">
                                            <option value="" disabled selected>Seleziona…</option>
                                            <option
                                                v-for="(label, key) in TIME_HORIZON_LABELS"
                                                :key="key"
                                                :value="key"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.time_horizon" />
                                    </div>
                                    <div>
                                        <Label for="initial_confidence">Convinzione iniziale (1-10)</Label>
                                        <select id="initial_confidence" name="initial_confidence" required :class="selectClass" class="mt-2">
                                            <option value="" disabled selected>Seleziona…</option>
                                            <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                                        </select>
                                        <InputError :message="errors.initial_confidence" />
                                    </div>
                                </div>
                            </section>

                            <Button type="submit" :disabled="processing">Salva scheda decisionale</Button>
                        </Form>
                    </template>

                    <template v-else>
                        <Form
                            :key="investment.updated_at"
                            v-bind="InvestmentDecisionController.update.form(investment.id)"
                            v-slot="{ errors, processing }"
                            class="space-y-4"
                        >
                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Motivazione dell'acquisto</h3>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="option in motivationOptions"
                                        :key="option.key"
                                        class="flex items-start gap-2 rounded-lg bg-background/50 px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="motivation_reasons[]"
                                            :value="option.key"
                                            :checked="investment.motivation_reasons.includes(option.key)"
                                            class="mt-0.5 size-4 shrink-0 rounded border-input"
                                        />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                                <div>
                                    <Label for="motivation_note_edit">Nota sulla motivazione (opzionale)</Label>
                                    <textarea
                                        id="motivation_note_edit"
                                        v-model="motivationNoteEdit"
                                        name="motivation_note"
                                        rows="2"
                                        :class="textareaClass"
                                        class="mt-2"
                                    />
                                </div>
                            </section>

                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Tesi e condizioni di uscita</h3>
                                <div>
                                    <Label for="thesis_edit">Tesi di investimento</Label>
                                    <textarea
                                        id="thesis_edit"
                                        v-model="thesisEdit"
                                        name="thesis"
                                        required
                                        rows="4"
                                        :class="textareaClass"
                                        class="mt-2"
                                    />
                                    <InputError :message="errors.thesis" />
                                </div>
                                <div>
                                    <Label for="sell_conditions_edit">Condizioni che ti farebbero cambiare idea</Label>
                                    <textarea
                                        id="sell_conditions_edit"
                                        v-model="sellConditionsEdit"
                                        name="sell_conditions"
                                        rows="3"
                                        :class="textareaClass"
                                        class="mt-2"
                                    />
                                </div>
                            </section>

                            <section class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                                <h3 class="font-semibold">Orizzonte e convinzione</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="time_horizon_edit">Orizzonte temporale</Label>
                                        <select id="time_horizon_edit" name="time_horizon" required :class="selectClass" class="mt-2">
                                            <option
                                                v-for="(label, key) in TIME_HORIZON_LABELS"
                                                :key="key"
                                                :value="key"
                                                :selected="investment.time_horizon === key"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label for="current_confidence_edit">Convinzione attuale (1-10)</Label>
                                        <select id="current_confidence_edit" name="current_confidence" required :class="selectClass" class="mt-2">
                                            <option
                                                v-for="n in 10"
                                                :key="n"
                                                :value="n"
                                                :selected="investment.current_confidence === n"
                                            >
                                                {{ n }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Convinzione iniziale: {{ investment.initial_confidence }}/10 · Creata il
                                    {{ formatDate(investment.created_at) }}
                                </p>
                            </section>

                            <Button type="submit" :disabled="processing">Salva modifiche</Button>
                        </Form>
                    </template>
                </TabsContent>

                <TabsContent value="fundamentals">
                    <div
                        v-if="!investment"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Completa prima la scheda nella tab "Thesis" per iniziare a tracciare i fondamentali.
                    </div>

                    <div v-else-if="!fundamentals" class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Collega questo investimento a un'analisi aziendale per vedere qui ricavi, margini,
                            ROE/ROIC, debito, fair value e valutazione, aggiornabili automaticamente da FMP.
                        </p>

                        <div v-if="companyAnalyses.length > 0" class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                            <h3 class="font-semibold">Collega un'analisi esistente</h3>
                            <Form
                                v-bind="InvestmentDecisionController.linkAnalysis.form(investment.id)"
                                v-slot="{ processing }"
                                class="flex flex-wrap items-end gap-2"
                            >
                                <div class="min-w-48 flex-1">
                                    <Label for="company_analysis_id">Analisi</Label>
                                    <select id="company_analysis_id" name="company_analysis_id" :class="selectClass" class="mt-2">
                                        <option v-for="analysis in companyAnalyses" :key="analysis.id" :value="analysis.id">
                                            {{ analysis.name }} ({{ analysis.symbol }})
                                        </option>
                                    </select>
                                </div>
                                <Button type="submit" variant="outline" :disabled="processing">Collega</Button>
                            </Form>
                        </div>

                        <div class="space-y-3 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                            <h3 class="font-semibold">Crea una nuova analisi</h3>
                            <Form
                                v-bind="InvestmentDecisionController.linkAnalysis.form(investment.id)"
                                v-slot="{ errors, processing }"
                                class="space-y-2"
                            >
                                <Input name="name" placeholder="Nome azienda" required />
                                <InputError :message="errors.name" />
                                <Input name="symbol" placeholder="Ticker (es. AAPL)" required />
                                <InputError :message="errors.symbol" />
                                <Button type="submit" :disabled="processing">Crea e collega</Button>
                            </Form>
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                            <p class="text-sm text-muted-foreground">
                                {{
                                    fundamentals.indicators_fetched_at
                                        ? `Aggiornato: ${formatDateTime(fundamentals.indicators_fetched_at)}`
                                        : 'Fondamentali non ancora aggiornati.'
                                }}
                            </p>
                            <div class="flex items-center gap-3">
                                <Form
                                    v-bind="CompanyAnalysisController.refreshIndicators.form(fundamentals.id)"
                                    v-slot="{ processing }"
                                >
                                    <Button type="submit" variant="outline" size="sm" :disabled="processing">
                                        {{ processing ? 'Aggiornamento…' : 'Aggiorna da FMP' }}
                                    </Button>
                                </Form>
                                <Link
                                    :href="companyAnalysisRoutes.show(fundamentals.id).url"
                                    class="text-sm underline hover:text-foreground"
                                >
                                    Vedi analisi completa
                                </Link>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div v-for="field in FUNDAMENTALS_SUMMARY_FIELDS" :key="field.key" class="rounded-xl bg-muted/60 p-3 dark:bg-muted/50">
                                <p class="text-xs text-muted-foreground">{{ field.label }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">
                                    {{ formatIndicator(fundamentals[field.key] as number | null, field.suffix) }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-muted/60 p-3 dark:bg-muted/50">
                                <p class="text-xs text-muted-foreground">Prezzo attuale</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">
                                    {{ fundamentals.current_price !== null ? formatCurrency(fundamentals.current_price) : '—' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-muted/60 p-3 dark:bg-muted/50">
                                <p class="text-xs text-muted-foreground">Fair value</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">
                                    {{ fundamentals.fair_value !== null ? formatCurrency(fundamentals.fair_value) : '—' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="fundamentals.valuation.verdict" class="rounded-2xl bg-muted/60 p-4 dark:bg-muted/50">
                            <p class="text-sm font-medium" :class="VERDICT_META[fundamentals.valuation.verdict].class">
                                {{ VERDICT_META[fundamentals.valuation.verdict].emoji }}
                                {{ VERDICT_META[fundamentals.valuation.verdict].label }}
                                <span v-if="fundamentals.valuation.deviation_percent !== null" class="font-normal text-muted-foreground">
                                    ({{ fundamentals.valuation.deviation_percent > 0 ? '+' : '' }}{{ fundamentals.valuation.deviation_percent }}% dal fair value)
                                </span>
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">{{ fundamentals.valuation.recommended_action }}</p>
                        </div>
                        <p v-else class="rounded-2xl bg-muted/60 p-4 dark:bg-muted/50 text-sm text-muted-foreground">
                            Imposta un fair value nell'analisi collegata per vedere qui il verdetto di valutazione.
                        </p>
                    </div>
                </TabsContent>

                <TabsContent value="events">
                    <div
                        v-if="!investment"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Completa prima la scheda nella tab "Thesis" per registrare gli eventi.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm text-muted-foreground">
                                Fatti oggettivi pubblicati dall'azienda (trimestrali, guidance, Investor Day,
                                acquisizioni, cambio management...). Registra qui i dati, senza indicare una
                                decisione: quella va nel tab "Reviews".
                            </p>
                            <Button size="sm" class="shrink-0" @click="isAddEventOpen = true">
                                <Plus class="mr-1 size-4" />
                                Evento
                            </Button>
                        </div>

                        <div v-if="investment.events.length === 0" class="text-sm text-muted-foreground">
                            Nessun evento registrato.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="event in investment.events"
                                :key="event.id"
                                class="cursor-pointer rounded-lg bg-muted dark:bg-muted/40 p-3 transition-colors hover:bg-muted/70"
                                @click="activeDetail = { kind: 'event', event }"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary">{{ EVENT_TYPE_LABELS[event.event_type] }}</Badge>
                                        <span class="text-sm font-medium">{{ event.title }}</span>
                                    </div>
                                    <span class="shrink-0 text-xs text-muted-foreground">{{ formatDate(event.event_date) }}</span>
                                </div>
                                <dl v-if="event.metrics.length > 0" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <div v-for="(metric, index) in event.metrics" :key="index">
                                        <dt class="text-xs text-muted-foreground">{{ metric.label }}</dt>
                                        <dd class="text-sm font-medium">{{ metric.value }}</dd>
                                    </div>
                                </dl>
                                <p v-if="event.summary" class="mt-2 line-clamp-2 text-sm whitespace-pre-wrap">{{ event.summary }}</p>
                                <button
                                    type="button"
                                    class="mt-2 text-xs underline hover:text-foreground"
                                    @click.stop="deleteEvent(event.id)"
                                >
                                    Elimina
                                </button>
                            </li>
                        </ul>
                    </div>
                </TabsContent>

                <TabsContent value="reviews">
                    <div
                        v-if="!investment"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Completa prima la scheda nella tab "Thesis" per registrare le review.
                    </div>

                    <div v-else class="space-y-10">
                        <div class="rounded-lg bg-muted dark:bg-muted/40 p-4">
                            <h4 class="mb-3 text-sm font-medium">Prossima review</h4>
                            <Form
                                :key="investment.updated_at"
                                v-bind="InvestmentDecisionController.update.form(investment.id)"
                                v-slot="{ errors, processing }"
                                class="space-y-3"
                            >
                                <div>
                                    <Label for="next_review_date">Data prevista</Label>
                                    <Input
                                        id="next_review_date"
                                        type="date"
                                        name="next_review_date"
                                        :default-value="investment.next_review_date ?? undefined"
                                        class="mt-2"
                                    />
                                    <InputError :message="errors.next_review_date" />
                                </div>
                                <div>
                                    <Label for="next_review_note">Cosa tenere d'occhio nel frattempo</Label>
                                    <textarea
                                        id="next_review_note"
                                        v-model="nextReviewNoteEdit"
                                        name="next_review_note"
                                        rows="2"
                                        :class="textareaClass"
                                        class="mt-2"
                                    />
                                </div>
                                <Button type="submit" variant="outline" size="sm" :disabled="processing">
                                    Salva prossima review
                                </Button>
                            </Form>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-medium">Review personali</h3>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Le tue decisioni: cosa hai deciso, come è cambiata la convinzione e se la
                                        tesi è ancora valida. Puoi collegare una review all'evento che l'ha generata.
                                    </p>
                                </div>
                                <Button size="sm" class="shrink-0" @click="isAddReviewOpen = true">
                                    <Plus class="mr-1 size-4" />
                                    Review
                                </Button>
                            </div>

                            <div v-if="investment.reviews.length === 0" class="text-sm text-muted-foreground">
                                Nessuna review registrata.
                            </div>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="review in investment.reviews"
                                    :key="review.id"
                                    class="cursor-pointer rounded-lg bg-muted dark:bg-muted/40 p-3 transition-colors hover:bg-muted/70"
                                    @click="activeDetail = { kind: 'review', review }"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">{{ DECISION_LABELS[review.decision] }}</span>
                                        <span class="text-xs text-muted-foreground">{{ formatDate(review.review_date) }}</span>
                                    </div>
                                    <p v-if="review.investment_event_title" class="mt-1 text-xs text-muted-foreground">
                                        Review generata da: {{ review.investment_event_title }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Convinzione: {{ review.score_before ?? '—' }} → {{ review.score_after }}
                                        <template v-if="review.thesis_still_valid !== null">
                                            · Tesi valida: {{ review.thesis_still_valid ? 'Sì' : 'No' }}
                                        </template>
                                    </p>
                                    <p v-if="review.note" class="mt-2 line-clamp-2 text-sm whitespace-pre-wrap">{{ review.note }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 text-xs underline hover:text-foreground"
                                        @click.stop="deleteReview(review.id)"
                                    >
                                        Elimina
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="journal">
                    <div
                        v-if="!investment"
                        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Completa prima la scheda nella tab "Thesis" per iniziare il journal.
                    </div>

                    <div v-else class="space-y-6">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-sm font-medium">Journal</h3>
                            <Button size="sm" class="shrink-0" @click="isAddJournalOpen = true">
                                <Plus class="mr-1 size-4" />
                                Nota
                            </Button>
                        </div>

                        <div v-if="journal.length === 0" class="text-sm text-muted-foreground">
                            Nessun evento nel journal.
                        </div>
                        <ul v-else class="space-y-3">
                            <li
                                v-for="(event, index) in journal"
                                :key="index"
                                class="cursor-pointer rounded-lg bg-muted dark:bg-muted/40 p-3 transition-colors hover:bg-muted/70"
                                @click="activeDetail = { kind: 'journal', entry: event }"
                            >
                                <div class="flex items-center justify-between">
                                    <Badge :variant="JOURNAL_TYPE_BADGE_VARIANT[event.type]">
                                        {{ JOURNAL_TYPE_LABELS[event.type] }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">{{ formatDate(event.date) }}</span>
                                </div>
                                <p v-if="event.amount !== null" class="mt-1 text-sm font-medium">
                                    {{ formatCurrency(event.amount) }}
                                    <span v-if="event.quantity" class="font-normal text-muted-foreground">
                                        · {{ formatQuantity(String(event.quantity)) }} quote
                                    </span>
                                </p>
                                <p v-if="event.type === 'review'" class="mt-1 text-xs text-muted-foreground">
                                    {{ DECISION_LABELS[event.decision as InvestmentReviewDecision] }} · Convinzione
                                    {{ event.score_before ?? '—' }} → {{ event.score_after }}
                                </p>
                                <p v-if="event.description" class="mt-2 line-clamp-2 text-sm whitespace-pre-wrap">{{ event.description }}</p>
                                <ul v-if="event.notes.length > 0" class="mt-2 space-y-1 border-l-2 pl-3">
                                    <li v-for="(note, noteIndex) in event.notes" :key="noteIndex" class="text-xs text-muted-foreground">
                                        {{ note }}
                                    </li>
                                </ul>
                                <button
                                    v-if="event.journal_entry_id"
                                    type="button"
                                    class="mt-2 text-xs underline hover:text-foreground"
                                    @click.stop="deleteJournalEntry(event.journal_entry_id)"
                                >
                                    Elimina
                                </button>
                            </li>
                        </ul>
                    </div>
                </TabsContent>
            </div>
        </Tabs>

        <!-- Nuova review -->
        <Sheet v-if="investment" v-model:open="isAddReviewOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Nuova review</SheetTitle>
                    <SheetDescription>{{ instrumentName }}</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="InvestmentReviewController.store.form(investment.id)"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="space-y-3 px-4 pb-6"
                    @success="isAddReviewOpen = false"
                >
                    <div v-if="investment.events.length > 0">
                        <Label for="investment_event_id">Evento collegato (opzionale)</Label>
                        <select id="investment_event_id" name="investment_event_id" :class="selectClass" class="mt-2">
                            <option value="">Nessuno</option>
                            <option v-for="event in investment.events" :key="event.id" :value="event.id">
                                {{ event.title }} — {{ formatDate(event.event_date) }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="review_date">Data</Label>
                            <Input id="review_date" type="date" name="review_date" required class="mt-2" />
                            <InputError :message="errors.review_date" />
                        </div>
                        <div>
                            <Label for="decision">Decisione</Label>
                            <select id="decision" name="decision" required :class="selectClass" class="mt-2">
                                <option v-for="(label, key) in DECISION_LABELS" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="score_after">Nuovo punteggio di convinzione (1-10)</Label>
                            <select id="score_after" name="score_after" required :class="selectClass" class="mt-2">
                                <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </div>
                        <div>
                            <Label for="thesis_still_valid">La tesi è ancora valida?</Label>
                            <select id="thesis_still_valid" name="thesis_still_valid" :class="selectClass" class="mt-2">
                                <option value="">Non specificato</option>
                                <option value="1">Sì</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label for="review_note">Motivazione</Label>
                        <textarea id="review_note" name="note" rows="2" :class="textareaClass" class="mt-2" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="processing">Aggiungi review</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <!-- Nuova voce di journal -->
        <Sheet v-if="investment" v-model:open="isAddJournalOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Nuova nota nel journal</SheetTitle>
                    <SheetDescription>{{ instrumentName }}</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="InvestmentJournalEntryController.store.form(investment.id)"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="space-y-3 px-4 pb-6"
                    @success="isAddJournalOpen = false"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="occurred_at">Data</Label>
                            <Input id="occurred_at" type="date" name="occurred_at" required class="mt-2" />
                            <InputError :message="errors.occurred_at" />
                        </div>
                        <div>
                            <Label for="transaction_id">Collega a una transazione (opzionale)</Label>
                            <select id="transaction_id" name="transaction_id" :class="selectClass" class="mt-2">
                                <option value="">Nessuna</option>
                                <option v-for="transaction in transactions" :key="transaction.id" :value="transaction.id">
                                    {{ formatDate(transaction.transaction_date) }} — {{ transaction.description }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label for="journal_note">Nota</Label>
                        <textarea id="journal_note" name="note" required rows="2" :class="textareaClass" class="mt-2" />
                        <InputError :message="errors.note" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="processing">Aggiungi al journal</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <!-- Nuovo evento: il form vive in un pannello, la pagina resta l'elenco -->
        <Sheet v-if="investment" v-model:open="isAddEventOpen">
            <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Nuovo evento</SheetTitle>
                    <SheetDescription>{{ instrumentName }}</SheetDescription>
                </SheetHeader>

                <Form
                    v-bind="InvestmentEventController.store.form(investment.id)"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="space-y-3 px-4 pb-6"
                    @success="isAddEventOpen = false"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="event_type">Tipo di evento</Label>
                            <select id="event_type" name="event_type" required :class="selectClass" class="mt-2">
                                <option v-for="(label, key) in EVENT_TYPE_LABELS" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="event_date">Data</Label>
                            <Input id="event_date" type="date" name="event_date" required class="mt-2" />
                            <InputError :message="errors.event_date" />
                        </div>
                    </div>
                    <div>
                        <Label for="title">Titolo</Label>
                        <Input
                            id="title"
                            name="title"
                            required
                            placeholder="Es. Q1 FY2027 — Risultati trimestrali"
                            class="mt-2"
                        />
                        <InputError :message="errors.title" />
                    </div>
                    <div>
                        <Label for="metrics_raw">Metriche (una per riga, formato "Etichetta: valore")</Label>
                        <textarea
                            id="metrics_raw"
                            name="metrics_raw"
                            rows="4"
                            placeholder="Ricavi: $65.6B (+18% YoY)&#10;EPS: $3.30 vs $3.10 atteso&#10;Crescita Azure: +33%&#10;Guidance Q2: $68-69B"
                            :class="textareaClass"
                            class="mt-2"
                        />
                        <InputError :message="errors.metrics_raw" />
                    </div>
                    <div>
                        <Label for="summary">Sintesi</Label>
                        <textarea id="summary" name="summary" rows="2" :class="textareaClass" class="mt-2" />
                    </div>
                    <Button type="submit" class="w-full" :disabled="processing">Aggiungi evento</Button>
                </Form>
            </SheetContent>
        </Sheet>

        <Sheet :open="activeDetail !== null" @update:open="(open) => { if (!open) closeDetail(); }">
            <SheetContent class="w-full gap-0 overflow-y-auto sm:max-w-lg">
                <template v-if="activeDetail?.kind === 'note'">
                    <SheetHeader>
                        <SheetTitle>Nota</SheetTitle>
                        <SheetDescription>{{ formatDateTime(activeDetail.note.created_at) }}</SheetDescription>
                    </SheetHeader>
                    <div class="space-y-4 px-4 pb-4">
                        <p class="text-sm whitespace-pre-wrap">{{ activeDetail.note.body }}</p>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="deleteNoteAndClose(activeDetail.note.id)"
                        >
                            Elimina nota
                        </Button>
                    </div>
                </template>

                <template v-else-if="activeDetail?.kind === 'event'">
                    <SheetHeader>
                        <SheetTitle>{{ activeDetail.event.title }}</SheetTitle>
                        <SheetDescription>
                            {{ EVENT_TYPE_LABELS[activeDetail.event.event_type] }} ·
                            {{ formatDate(activeDetail.event.event_date) }}
                        </SheetDescription>
                    </SheetHeader>
                    <div class="space-y-4 px-4 pb-4">
                        <dl v-if="activeDetail.event.metrics.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div v-for="(metric, index) in activeDetail.event.metrics" :key="index">
                                <dt class="text-xs text-muted-foreground">{{ metric.label }}</dt>
                                <dd class="text-sm font-medium">{{ metric.value }}</dd>
                            </div>
                        </dl>
                        <p v-if="activeDetail.event.summary" class="text-sm whitespace-pre-wrap">
                            {{ activeDetail.event.summary }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="deleteEventAndClose(activeDetail.event.id)"
                        >
                            Elimina evento
                        </Button>
                    </div>
                </template>

                <template v-else-if="activeDetail?.kind === 'review'">
                    <SheetHeader>
                        <SheetTitle>{{ DECISION_LABELS[activeDetail.review.decision] }}</SheetTitle>
                        <SheetDescription>{{ formatDate(activeDetail.review.review_date) }}</SheetDescription>
                    </SheetHeader>
                    <div class="space-y-4 px-4 pb-4">
                        <p v-if="activeDetail.review.investment_event_title" class="text-sm text-muted-foreground">
                            Review generata da: {{ activeDetail.review.investment_event_title }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Convinzione: {{ activeDetail.review.score_before ?? '—' }} →
                            {{ activeDetail.review.score_after }}
                            <template v-if="activeDetail.review.thesis_still_valid !== null">
                                · Tesi valida: {{ activeDetail.review.thesis_still_valid ? 'Sì' : 'No' }}
                            </template>
                        </p>
                        <p v-if="activeDetail.review.note" class="text-sm whitespace-pre-wrap">
                            {{ activeDetail.review.note }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="deleteReviewAndClose(activeDetail.review.id)"
                        >
                            Elimina review
                        </Button>
                    </div>
                </template>

                <template v-else-if="activeDetail?.kind === 'journal'">
                    <SheetHeader>
                        <SheetTitle>{{ JOURNAL_TYPE_LABELS[activeDetail.entry.type] }}</SheetTitle>
                        <SheetDescription>{{ formatDate(activeDetail.entry.date) }}</SheetDescription>
                    </SheetHeader>
                    <div class="space-y-4 px-4 pb-4">
                        <p v-if="activeDetail.entry.amount !== null" class="text-sm font-medium">
                            {{ formatCurrency(activeDetail.entry.amount) }}
                            <span v-if="activeDetail.entry.quantity" class="font-normal text-muted-foreground">
                                · {{ formatQuantity(String(activeDetail.entry.quantity)) }} quote
                            </span>
                        </p>
                        <p v-if="activeDetail.entry.type === 'review'" class="text-sm text-muted-foreground">
                            {{ DECISION_LABELS[activeDetail.entry.decision as InvestmentReviewDecision] }} ·
                            Convinzione {{ activeDetail.entry.score_before ?? '—' }} →
                            {{ activeDetail.entry.score_after }}
                        </p>
                        <p v-if="activeDetail.entry.description" class="text-sm whitespace-pre-wrap">
                            {{ activeDetail.entry.description }}
                        </p>
                        <ul v-if="activeDetail.entry.notes.length > 0" class="space-y-1 border-l-2 pl-3">
                            <li
                                v-for="(note, noteIndex) in activeDetail.entry.notes"
                                :key="noteIndex"
                                class="text-xs text-muted-foreground"
                            >
                                {{ note }}
                            </li>
                        </ul>
                        <Button
                            v-if="activeDetail.entry.journal_entry_id"
                            variant="outline"
                            size="sm"
                            @click="deleteJournalEntryAndClose(activeDetail.entry.journal_entry_id)"
                        >
                            Elimina dal journal
                        </Button>
                    </div>
                </template>
            </SheetContent>
        </Sheet>
    </div>
</template>
