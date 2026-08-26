<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { KeyRound, Sparkles } from '@lucide/vue';
import { ref } from 'vue';

type Phase = 'code' | 'message';

const phase = ref<Phase>('code');
const code = ref('');
const isShaking = ref(false);

// La data indizio ("23 luglio 2020") potrebbe essere digitata in diversi
// modi ragionevoli - li accettiamo tutti, ignorando spazi/punteggiatura e
// maiuscole/minuscole.
const ACCEPTED_CODES = new Set([
    '23072020',
    '2372020',
    '23luglio2020',
    '2307',
    '23luglio',
]);

function normalize(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function submitCode() {
    if (ACCEPTED_CODES.has(normalize(code.value))) {
        phase.value = 'message';

        return;
    }

    // "Se sbaglia non succede nulla" - nessun messaggio d'errore, solo un
    // piccolo scuotimento per far capire che il tentativo è stato ricevuto.
    isShaking.value = true;
    window.setTimeout(() => {
        isShaking.value = false;
    }, 400);
}
</script>

<template>
    <Head title="Per Nadia" />

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
            <!-- Codice segreto -->
            <div
                v-if="phase === 'code'"
                class="fade-in-up w-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl shadow-black/30 backdrop-blur"
                :class="isShaking ? 'shake' : ''"
            >
                <KeyRound class="mx-auto size-8 text-fuchsia-300" />
                <h1 class="mt-4 text-xl font-semibold tracking-tight">
                    C'è un messaggio nascosto qui dentro.
                </h1>
                <p class="mt-3 text-white/70">
                    Per sbloccarlo serve un codice. Hai tentativi infiniti,
                    quindi tanto vale continuare a provare.
                </p>
                <p class="mt-3 text-sm text-white/40">
                    Indizio: è una data speciale che dimentichiamo
                    sistematicamente. 😅
                </p>

                <form
                    class="mt-6 flex flex-col items-center gap-3"
                    @submit.prevent="submitCode"
                >
                    <input
                        v-model="code"
                        type="text"
                        autocomplete="off"
                        placeholder="Codice segreto"
                        class="w-full max-w-xs rounded-full border border-white/20 bg-white/10 px-5 py-3 text-center text-white placeholder-white/30 outline-none focus:border-fuchsia-300/50"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-fuchsia-500 to-indigo-500 px-6 py-3 text-sm font-medium text-white shadow-lg shadow-fuchsia-950/40 transition-transform hover:scale-105"
                    >
                        Sblocca
                    </button>
                </form>
            </div>

            <!-- Messaggio -->
            <div
                v-else
                class="fade-in-up w-full rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl shadow-black/30 backdrop-blur"
            >
                <Sparkles class="mx-auto size-8 text-fuchsia-300" />
                <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                    Buon compleanno, Amore mio! 🎉♥️
                </h1>
                <div class="mt-5 space-y-3 text-center text-white/80">
                    <p>
                        Volevo solo ringraziarti perché esisti. E bellissimo
                        essere innamorata di te!
                    </p>
                    <p>
                        Ti auguro un anno pieno di cose belle e di momenti di
                        calma vera, di risate, di sorrisi e di ogni altra cosa
                        che ti fa sentire felice. Io sono qui per capire cosa ti
                        fa sentire felice e fallo per te e con te.
                    </p>
                </div>
                <p>Ti amo con tutto il cuore ♥️</p>
                <p class="mt-2 text-sm text-white/50">
                    (ti prego non dire:  "io di più"  nemmeno se oggi è il tuo
                    compleanno) <br />
                </p>
                <p class="text-sm font-semibold text-white/70">Yana</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

.shake {
    animation: shake 0.4s ease;
}

@keyframes shake {
    10%,
    90% {
        transform: translateX(-2px);
    }
    20%,
    80% {
        transform: translateX(4px);
    }
    30%,
    50%,
    70% {
        transform: translateX(-6px);
    }
    40%,
    60% {
        transform: translateX(6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fade-in-up,
    .star,
    .shake {
        animation: none;
    }
}
</style>
