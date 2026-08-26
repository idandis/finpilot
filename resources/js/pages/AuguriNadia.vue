<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowRight, Check, Download, Gift, LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import GiftController from '@/actions/App/Http/Controllers/GiftController';

type Option = {
    emoji: string;
    label: string;
    commentEmoji: string;
    comment: string;
};

type Question = {
    emoji: string;
    text: string;
    options: Option[];
};

type ChiccaStep = {
    pct: number;
    text: string;
};

type Phase =
    | 'intro'
    | 'question'
    | 'analyzing'
    | 'comment'
    | 'chicca'
    | 'checklist'
    | 'pause'
    | 'congrats'
    | 'voucher';

const questions: Question[] = [
    {
        emoji: '💆',
        text: 'Se potessi scegliere un solo tipo di coccola in questo momento, quale sarebbe?',
        options: [
            {
                emoji: '💦',
                label: "Un getto d'acqua calda sulla schiena",
                commentEmoji: '🌊',
                comment:
                    'Le tue spalle hanno appena ringraziato ufficialmente.',
            },
            {
                emoji: '🫖',
                label: 'Una tisana calda tra le mani',
                commentEmoji: '☁️',
                comment: 'Livello relax: in aumento costante.',
            },
            {
                emoji: '🧴',
                label: 'Un olio caldo che scioglie ogni tensione',
                commentEmoji: '✨',
                comment: "I muscoli hanno votato all'unanimità: sì.",
            },
            {
                emoji: '🤍',
                label: 'Silenzio e basta',
                commentEmoji: '🤫',
                comment: 'Richiesta approvata senza discussione.',
            },
        ],
    },
    {
        emoji: '🧖',
        text: 'Scegli l’ambiente in cui vorresti sparire per un pomeriggio.',
        options: [
            {
                emoji: '🌫️',
                label: 'Bagno turco pieno di vapore',
                commentEmoji: '💧',
                comment: 'Stiamo già condensando la tua felicità.',
            },
            {
                emoji: '🔥',
                label: 'Sauna calda e silenziosa',
                commentEmoji: '🪵',
                comment: 'Il legno scricchiola in segno di approvazione.',
            },
            {
                emoji: '🛁',
                label: 'Vasca idromassaggio',
                commentEmoji: '🫧',
                comment: 'Le bolle hanno appena firmato un contratto con te.',
            },
            {
                emoji: '❄️',
                label: 'Doccia emozionale (caldo-freddo)',
                commentEmoji: '⚡',
                comment: "Coraggiosa. Il sistema apprezza l'audacia.",
            },
        ],
    },
    {
        emoji: '🛋️',
        text: 'Quale piccolo lusso ti manca di più?',
        options: [
            {
                emoji: '👘',
                label: 'Un accappatoio morbidissimo tutto il giorno',
                commentEmoji: '☁️',
                comment: 'Comfort livello: nuvola.',
            },
            {
                emoji: '🥂',
                label: 'Un aperitivo senza pensieri, calice in mano',
                commentEmoji: '🍹',
                comment: 'Brindisi ufficialmente autorizzato.',
            },
            {
                emoji: '🏞️',
                label: 'Una vista panoramica e una poltrona comoda',
                commentEmoji: '✨',
                comment: 'Il panorama si è appena candidato come terapia.',
            },
            {
                emoji: '😌',
                label: 'Non dover decidere nulla per un giorno intero',
                commentEmoji: '🧘',
                comment: 'Richiesta approvata: zero decisioni, zero pensieri.',
            },
        ],
    },
    {
        emoji: '🍃',
        text: 'Quale profumo ti rilassa di più?',
        options: [
            {
                emoji: '🌿',
                label: 'Eucalipto',
                commentEmoji: '🌬️',
                comment: 'Respiro profondo rilevato.',
            },
            {
                emoji: '🪷',
                label: 'Fiori di loto',
                commentEmoji: '🌸',
                comment: 'Serenità in aumento del 12%.',
            },
            {
                emoji: '🍊',
                label: 'Agrumi freschi',
                commentEmoji: '☀️',
                comment: 'Energia positiva in arrivo.',
            },
            {
                emoji: '🕯️',
                label: 'Candele calde e legno',
                commentEmoji: '🔥',
                comment: 'Atmosfera da coccole ufficialmente attivata.',
            },
        ],
    },
    {
        emoji: '🚪',
        text: 'Trovi una porta misteriosa. Cosa speri ci sia dietro?',
        options: [
            {
                emoji: '🛀',
                label: 'Una vasca enorme tutta per te',
                commentEmoji: '🫧',
                comment: 'Candidato perfetto individuato.',
            },
            {
                emoji: '🧖',
                label: 'Un percorso benessere infinito',
                commentEmoji: '🌊',
                comment:
                    'Il tuo corpo ha appena esalato un sospiro di sollievo.',
            },
            {
                emoji: '🥂',
                label: 'Amiche, risate e coccole insieme',
                commentEmoji: '💛',
                comment: 'Combinazione vincente rilevata.',
            },
            {
                emoji: '🤫',
                label: 'Un posto dove nessuno può disturbarti',
                commentEmoji: '📵',
                comment: 'Richiesta molto popolare, lo sappiamo.',
            },
        ],
    },
    {
        emoji: '💧',
        text: 'Se potessi scegliere un solo suono da ascoltare per ore, quale sarebbe?',
        options: [
            {
                emoji: '💧',
                label: "Gocce d'acqua che cadono piano",
                commentEmoji: '🌧️',
                comment: 'Il ritmo perfetto per staccare la spina.',
            },
            {
                emoji: '🎶',
                label: 'Musica soft in sottofondo',
                commentEmoji: '🎧',
                comment: 'Playlist "relax totale" già pronta.',
            },
            {
                emoji: '🗣️',
                label: 'Risate con le amiche',
                commentEmoji: '😂',
                comment: 'Suono ufficialmente più curativo del mondo.',
            },
            {
                emoji: '🤍',
                label: 'Nessun suono, solo pace',
                commentEmoji: '🕊️',
                comment: "Richiesta approvata all'unanimità.",
            },
        ],
    },
    {
        emoji: '🥂',
        text: 'È una giornata libera con le tue migliori amiche. Cosa fate?',
        options: [
            {
                emoji: '🛀',
                label: 'Ci rilassiamo insieme in un posto bellissimo',
                commentEmoji: '✨',
                comment: 'Combinazione: relax + affetto = perfezione.',
            },
            {
                emoji: '🍰',
                label: 'Pranzo lungo senza fretta',
                commentEmoji: '🍽️',
                comment: 'Il tempo, oggi, rallenta apposta per voi.',
            },
            {
                emoji: '💬',
                label: 'Chiacchiere infinite',
                commentEmoji: '💛',
                comment: 'Argomenti stimati: illimitati.',
            },
            {
                emoji: '😌',
                label: 'Ognuna fa quello che vuole, in silenzio',
                commentEmoji: '🤫',
                comment: 'Amicizia al livello massimo: nessuna pressione.',
            },
        ],
    },
    {
        emoji: '🌌',
        text: 'Se dovessi scegliere un superpotere, quale sarebbe?',
        options: [
            {
                emoji: '⏸️',
                label: 'Fermare il tempo',
                commentEmoji: '⏳',
                comment: 'Ore extra in arrivo. Forse.',
            },
            {
                emoji: '💆',
                label: 'Sciogliere ogni tensione con un tocco',
                commentEmoji: '🙌',
                comment: 'Le spalle di tutti ti ringrazierebbero.',
            },
            {
                emoji: '😴',
                label: 'Rilassarmi istantaneamente ovunque',
                commentEmoji: '💤',
                comment: 'Potere raro. Alcuni lo chiamano "talento".',
            },
            {
                emoji: '🔕',
                label: 'Far sparire pensieri e notifiche',
                commentEmoji: '📱',
                comment: 'Il telefono trema già.',
            },
        ],
    },
    {
        emoji: '🧘',
        text: 'Completa la frase: "In questo periodo mi servirebbe..."',
        options: [
            {
                emoji: '🧖‍♀️',
                label: 'Una giornata tutta per me e le mie amiche',
                commentEmoji: '💛',
                comment: 'Richiesta ricevuta. Ci stiamo già lavorando.',
            },
            {
                emoji: '😴',
                label: 'Otto ore di sonno',
                commentEmoji: '💤',
                comment: 'Il tuo cuscino sarebbe molto orgoglioso.',
            },
            {
                emoji: '⏸️',
                label: 'Un pulsante pausa',
                commentEmoji: '🧘',
                comment:
                    'Finalmente qualcuno chiede la funzione più importante.',
            },
            {
                emoji: '🛁',
                label: 'Immergermi e non pensare a nulla',
                commentEmoji: '🫧',
                comment: 'Ci siamo quasi.',
            },
        ],
    },
];

// Ogni tanto, invece del breve commento, mostriamo la finta barra di
// caricamento con testi a caso - un checkpoint diverso ogni volta per non
// ripetersi sempre uguale. Indici 0-based: domande 3, 6 e 9.
const CHICCA_STEPS: Record<number, ChiccaStep[]> = {
    2: [
        { pct: 23, text: 'Analisi del bisogno di coccole...' },
        { pct: 52, text: 'Calcolo del livello di relax necessario...' },
        { pct: 81, text: 'Verifica disponibilità vasca idromassaggio...' },
        { pct: 100, text: 'Risultato quasi pronto...' },
    ],
    5: [
        { pct: 30, text: 'Scansione dei suoni più rilassanti...' },
        { pct: 64, text: 'Calcolo del sottofondo perfetto...' },
        { pct: 100, text: 'Risultato quasi pronto...' },
    ],
    8: [
        { pct: 40, text: 'Elaborazione bisogno di coccole...' },
        { pct: 75, text: 'Calibrazione del relax perfetto...' },
        { pct: 100, text: 'Risultato quasi pronto...' },
    ],
};

const ANALYZING_MS = 700;
const COMMENT_MS = 2600;
const CHICCA_STEP_MS = 550;
const CHICCA_HOLD_MS = 1800;
const CHECKLIST_LINE_MS = 600;
const FINALE_PAUSE_MS = 2000;
const CONGRATS_TO_VOUCHER_MS = 3400;

const checklistLines = [
    '✔ Risposte analizzate',
    '✔ Confronto con 8 miliardi di esseri umani...',
    '✔ Eliminazione delle idee regalo banali...',
    '✔ Ricerca esperienza perfetta...',
    '✔ Trovata.',
];

// Sostituisci con il path dell'immagine del voucher reale quando pronto
// (es. '/gifts/voucher-nadia.jpg') per mostrarlo al posto della card testuale.
const voucherImageUrl: string | null = null;

const currentIndex = ref(0);
const phase = ref<Phase>('intro');
const selectedOption = ref<Option | null>(null);
const chiccaStepIndex = ref(0);
const checklistVisibleCount = ref(0);

const currentQuestion = computed(() => questions[currentIndex.value]);
const currentChiccaSteps = computed(
    () => CHICCA_STEPS[currentIndex.value] ?? [],
);
const currentChiccaStep = computed(
    () => currentChiccaSteps.value[chiccaStepIndex.value],
);
const progressPercent = computed(() =>
    Math.round(((currentIndex.value + 1) / questions.length) * 100),
);

function asciiBar(pct: number): string {
    const filled = Math.round(pct / 10);

    return '█'.repeat(filled) + '░'.repeat(10 - filled);
}

const timers: number[] = [];

function after(ms: number, fn: () => void) {
    timers.push(window.setTimeout(fn, ms));
}

function startQuiz() {
    phase.value = 'question';
}

function selectOption(option: Option) {
    if (phase.value !== 'question') {
        return;
    }

    selectedOption.value = option;
    phase.value = 'analyzing';

    after(ANALYZING_MS, () => {
        if (CHICCA_STEPS[currentIndex.value]) {
            runChicca();
        } else {
            phase.value = 'comment';
            after(COMMENT_MS, advance);
        }
    });
}

function runChicca() {
    phase.value = 'chicca';
    chiccaStepIndex.value = 0;

    const steps = currentChiccaSteps.value;
    const stepThrough = (i: number) => {
        chiccaStepIndex.value = i;

        if (i < steps.length - 1) {
            after(CHICCA_STEP_MS, () => stepThrough(i + 1));
        } else {
            after(CHICCA_HOLD_MS, advance);
        }
    };

    stepThrough(0);
}

function advance() {
    if (currentIndex.value < questions.length - 1) {
        currentIndex.value += 1;
        selectedOption.value = null;
        phase.value = 'question';
    } else {
        startFinale();
    }
}

function startFinale() {
    phase.value = 'checklist';
    checklistVisibleCount.value = 0;

    const revealNext = (i: number) => {
        checklistVisibleCount.value = i + 1;

        if (i < checklistLines.length - 1) {
            after(CHECKLIST_LINE_MS, () => revealNext(i + 1));
        } else {
            after(CHECKLIST_LINE_MS + 200, () => {
                phase.value = 'pause';

                after(FINALE_PAUSE_MS, () => {
                    phase.value = 'congrats';

                    after(CONGRATS_TO_VOUCHER_MS, () => {
                        phase.value = 'voucher';
                    });
                });
            });
        }
    };

    revealNext(0);
}

onBeforeUnmount(() => {
    timers.forEach((id) => window.clearTimeout(id));
});
</script>

<template>
    <Head title="Per Nadia 🌙" />

    <div
        class="relative min-h-screen overflow-hidden bg-gradient-to-b from-slate-950 via-indigo-950 to-slate-950 px-4 py-10 text-white"
    >
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <span
                v-for="n in 28"
                :key="n"
                class="star absolute rounded-full bg-white"
                :style="{
                    top: `${(n * 37) % 100}%`,
                    left: `${(n * 53) % 100}%`,
                    width: `${1 + (n % 3)}px`,
                    height: `${1 + (n % 3)}px`,
                    animationDelay: `${(n % 10) * 0.4}s`,
                }"
            />
        </div>

        <div
            class="relative mx-auto flex min-h-[80vh] max-w-xl flex-col items-center justify-center"
        >
            <!-- Intro -->
            <div
                v-if="phase === 'intro'"
                class="fade-in-up w-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl shadow-black/30 backdrop-blur"
            >
                <p class="text-lg font-medium text-white/90">Ciao Nadia 💜</p>
                <h1 class="mt-3 text-xl font-semibold tracking-tight">
                    C'è un regalo di compleanno che ti aspetta. 🎁
                </h1>
                <p class="mt-4 text-white/70">
                    Ma dirtelo e basta ci sembrava decisamente troppo semplice.
                </p>
                <p class="mt-3 text-white/70">
                    Quindi abbiamo incaricato un algoritmo (fidato, promesso) di
                    provare a scoprirlo da solo, facendoti 9 domande
                    apparentemente a caso.
                </p>
                <p class="mt-3 text-white/70">
                    Nessuna domanda parlerà esplicitamente del regalo.
                </p>
                <p class="mt-3 text-sm text-white/40">Forse. 👀</p>
                <p class="mt-4 text-white/90">
                    Rispondi d'istinto. Il resto lo farà l'algoritmo.
                </p>

                <button
                    type="button"
                    class="mt-8 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-fuchsia-500 to-indigo-500 px-6 py-3 text-sm font-medium text-white shadow-lg shadow-fuchsia-950/40 transition-transform hover:scale-105"
                    @click="startQuiz"
                >
                    Inizia
                    <ArrowRight class="size-4" />
                </button>
            </div>

            <!-- Domande -->
            <div
                v-else-if="
                    phase !== 'checklist' &&
                    phase !== 'pause' &&
                    phase !== 'congrats' &&
                    phase !== 'voucher'
                "
                class="w-full"
            >
                <div class="mb-6">
                    <div
                        class="mb-2 flex items-center justify-between text-xs text-white/50"
                    >
                        <span
                            >Domanda {{ currentIndex + 1 }} di
                            {{ questions.length }}</span
                        >
                        <span>{{ progressPercent }}%</span>
                    </div>
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-fuchsia-400 to-indigo-400 transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                </div>

                <Transition name="fade" mode="out-in">
                    <div
                        v-if="phase === 'question'"
                        key="question"
                        class="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/30 backdrop-blur"
                    >
                        <div class="mb-4 text-center text-4xl">
                            {{ currentQuestion.emoji }}
                        </div>
                        <h2
                            class="mb-6 text-center text-lg font-medium text-white/90"
                        >
                            {{ currentQuestion.text }}
                        </h2>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button
                                v-for="option in currentQuestion.options"
                                :key="option.label"
                                type="button"
                                class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm text-white/90 transition-colors hover:border-fuchsia-300/40 hover:bg-white/10"
                                @click="selectOption(option)"
                            >
                                <span class="text-lg">{{ option.emoji }}</span>
                                <span>{{ option.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-else-if="phase === 'analyzing'"
                        key="analyzing"
                        class="flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-10 shadow-2xl shadow-black/30 backdrop-blur"
                    >
                        <LoaderCircle
                            class="size-6 animate-spin text-fuchsia-300"
                        />
                        <p
                            class="flex items-center gap-1 text-sm text-white/60"
                        >
                            Analisi in corso
                            <span class="dot" />
                            <span class="dot" style="animation-delay: 0.2s" />
                            <span class="dot" style="animation-delay: 0.4s" />
                        </p>
                    </div>

                    <div
                        v-else-if="phase === 'comment'"
                        key="comment"
                        class="flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-10 text-center shadow-2xl shadow-black/30 backdrop-blur"
                    >
                        <span class="text-3xl">{{
                            selectedOption?.commentEmoji
                        }}</span>
                        <p class="text-base text-white/90">
                            {{ selectedOption?.comment }}
                        </p>
                    </div>

                    <div
                        v-else-if="phase === 'chicca'"
                        key="chicca"
                        class="rounded-2xl border border-white/10 bg-black/50 p-8 font-mono shadow-2xl shadow-black/30 backdrop-blur"
                    >
                        <div
                            class="mb-3 text-lg tracking-widest text-emerald-300"
                        >
                            {{ asciiBar(currentChiccaStep?.pct ?? 0) }}
                            {{ currentChiccaStep?.pct ?? 0 }}%
                            <span class="cursor-blink">▌</span>
                        </div>
                        <p class="text-sm text-emerald-100/70">
                            {{ currentChiccaStep?.text }}
                        </p>
                    </div>
                </Transition>
            </div>

            <!-- Checklist finale -->
            <div
                v-else-if="phase === 'checklist'"
                class="w-full rounded-2xl border border-white/10 bg-white/5 p-8 font-mono text-sm shadow-2xl shadow-black/30 backdrop-blur"
            >
                <p
                    v-for="(line, i) in checklistLines"
                    v-show="i < checklistVisibleCount"
                    :key="line"
                    class="fade-in-up py-1 text-emerald-200"
                >
                    {{ line }}
                </p>
            </div>

            <!-- Pausa silenziosa -->
            <div v-else-if="phase === 'pause'" class="h-24" />

            <!-- Congratulazioni -->
            <div v-else-if="phase === 'congrats'" class="w-full text-center">
                <h1 class="fade-in-up text-3xl font-semibold tracking-tight">
                    Congratulazioni.
                </h1>
                <p
                    class="fade-in-up mt-6 text-white/70"
                    style="animation-delay: 1.1s"
                >
                    Dopo un'attenta analisi, il nostro algoritmo ha concluso che
                    il regalo perfetto non è un oggetto.
                </p>
                <p
                    class="fade-in-up mt-2 text-lg text-white/95"
                    style="animation-delay: 2.2s"
                >
                    È un giorno in cui il mondo può aspettare.
                </p>
            </div>

            <!-- Voucher -->
            <div
                v-else-if="phase === 'voucher'"
                class="fade-in-up w-full text-center"
            >
                <img
                    v-if="voucherImageUrl"
                    :src="voucherImageUrl"
                    alt="Voucher"
                    class="mx-auto rounded-2xl shadow-2xl shadow-black/40"
                />
                <div v-else class="ticket mx-auto max-w-sm text-left">
                    <div
                        class="relative overflow-hidden rounded-2xl border border-fuchsia-400/20 bg-gradient-to-br from-indigo-950 via-slate-900 to-fuchsia-950 p-6 text-white shadow-2xl shadow-black/50"
                    >
                        <span
                            v-for="n in 14"
                            :key="n"
                            class="star absolute rounded-full bg-white"
                            :style="{
                                top: `${(n * 29) % 100}%`,
                                left: `${(n * 41) % 100}%`,
                                width: `${1 + (n % 2)}px`,
                                height: `${1 + (n % 2)}px`,
                                animationDelay: `${(n % 7) * 0.5}s`,
                            }"
                        />

                        <span
                            class="absolute top-5 -right-10 w-40 rotate-45 bg-emerald-500 py-1 text-center text-[9px] leading-tight font-bold tracking-wide text-white shadow"
                        >
                            SENZA LIMITI<br />DI TEMPO
                        </span>

                        <div
                            class="relative flex items-center gap-2 text-xs font-semibold tracking-widest text-fuchsia-300 uppercase"
                        >
                            <Gift class="size-4" />
                            Buono regalo · QC Terme
                        </div>

                        <p class="relative mt-3 text-2xl font-bold">
                            Ingresso SPA
                        </p>
                        <p class="relative text-sm text-white/50">
                            San Pellegrino Terme
                        </p>

                        <p class="relative mt-4 text-sm text-white/70">
                            Missione relax: ufficialmente approvata. ❤️😊 Niente
                            sveglie, niente orologi, niente scuse.
                        </p>
                        <p class="relative mt-2 text-sm text-white/70">
                            🍽️ Cena inclusa, dopo le terme.
                        </p>

                        <div
                            class="relative my-5 border-t border-dashed border-white/20"
                        />

                        <div
                            class="relative flex items-center justify-between text-xs text-white/40"
                        >
                            <span>N° 0000-NADIA</span>
                            <span
                                class="flex items-center gap-1 font-medium text-emerald-400"
                            >
                                <Check class="size-3.5" />
                                Valido oggi, domani, quando vuoi tu
                            </span>
                        </div>

                        <p class="relative mt-4 text-xs text-white/50">
                            Da parte di
                            <span class="font-semibold text-white/80"
                                >Yana &amp; Lula</span
                            >
                        </p>
                    </div>
                </div>

                <a
                    :href="GiftController.auguriNadiaVoucher().url"
                    class="mt-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-white/20"
                >
                    <Download class="size-4" />
                    Scarica PDF
                </a>

                <p class="mt-8 text-sm text-white/40">Buon compleanno 🎂</p>
                <p class="mt-2 text-sm text-white/60">
                    Ti vogliamo bene e non vediamo l'ora 💜
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ticket {
    transform: rotate(-1.5deg);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.35s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.fade-in-up {
    animation: fade-in-up 0.8s ease-out both;
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dot {
    display: inline-block;
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    background: currentColor;
    animation: dot-bounce 1.2s ease-in-out infinite;
}

@keyframes dot-bounce {
    0%,
    80%,
    100% {
        opacity: 0.2;
        transform: translateY(0);
    }
    40% {
        opacity: 1;
        transform: translateY(-2px);
    }
}

.cursor-blink {
    animation: blink 1s step-end infinite;
}

@keyframes blink {
    0%,
    50% {
        opacity: 1;
    }
    50.01%,
    100% {
        opacity: 0;
    }
}

.star {
    animation: twinkle 3.5s ease-in-out infinite;
}

@keyframes twinkle {
    0%,
    100% {
        opacity: 0.15;
    }
    50% {
        opacity: 0.9;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fade-in-up,
    .star,
    .cursor-blink,
    .dot {
        animation: none;
    }
}
</style>
