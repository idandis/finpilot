<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import CompanyAnalysisController from '@/actions/App/Http/Controllers/Finance/CompanyAnalysisController';
import CandlestickChart from '@/components/finance/CandlestickChart.vue';
import DcfCalculator from '@/components/finance/DcfCalculator.vue';
import IndicatorFieldGrid from '@/components/finance/IndicatorFieldGrid.vue';
import type { IndicatorField } from '@/components/finance/IndicatorFieldGrid.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import * as companyAnalysisRoutes from '@/routes/company-analyses';
import type {
    BuffettAnswer,
    CompanyAnalysis,
    MarketAnalysis,
    MarketCandle,
    ValuationVerdict,
} from '@/types';

const props = defineProps<{
    analysis: CompanyAnalysis;
    buffettQuestions: BuffettAnswer[];
    priceHistory: MarketCandle[];
    technicalAnalysis: MarketAnalysis;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Analisi aziende', href: companyAnalysisRoutes.index() },
            { title: 'Dettaglio analisi', href: companyAnalysisRoutes.index() },
        ],
    },
});

const VERDICT_META: Record<
    ValuationVerdict,
    { emoji: string; label: string; class: string }
> = {
    undervalued: {
        emoji: '🟢',
        label: 'Sottovalutata',
        class: 'text-green-600',
    },
    fair: {
        emoji: '🟡',
        label: 'Correttamente valutata',
        class: 'text-yellow-600',
    },
    expensive: { emoji: '🟠', label: 'Cara', class: 'text-orange-600' },
    very_expensive: { emoji: '🔴', label: 'Molto cara', class: 'text-red-600' },
};

const PROFITABILITY_FIELDS: IndicatorField[] = [
    {
        key: 'roe',
        label: 'ROE',
        suffix: '%',
        description:
            "Il ROE (Return on Equity) misura quanto profitto genera l'azienda per ogni euro che gli azionisti vi hanno investito. Immagina di prestare 100€ a un amico che apre un piccolo negozio: se a fine anno ti restituisce 15€ di guadagno, il tuo \"rendimento\" è del 15% — per un'azienda funziona allo stesso modo, ma il capitale di riferimento (patrimonio netto) include anche gli utili accumulati negli anni. Attenzione: un'azienda può gonfiare il ROE indebitandosi molto, perché il debito riduce il patrimonio netto al denominatore senza che il business sia davvero più efficiente — per questo va sempre letto insieme al Debito/EBITDA nella tab Solidità.",
        ranges: '<10% debole · 10-15% media · 15-20% buono · >20% eccellente (se >30-40% controlla il debito: può essere gonfiato dalla leva)',
        scoreKey: 'roe',
    },
    {
        key: 'roic',
        label: 'ROIC',
        suffix: '%',
        description:
            "Il ROIC (Return on Invested Capital) è simile al ROE ma più severo: invece di guardare solo i soldi degli azionisti, considera tutto il capitale impiegato per generare profitti — sia quello degli azionisti sia il debito. È l'indicatore preferito da molti investitori proprio perché più difficile da \"truccare\" con la leva finanziaria. La domanda chiave è: il ROIC supera il costo del capitale (tipicamente 7-10%, cioè quanto costerebbe all'azienda procurarsi quei soldi sul mercato)? Se sì, l'azienda crea valore reale; se no, lo sta distruggendo anche se sembra profittevole sulla carta.",
        ranges: '<5% debole · 5-10% media · 10-15% buono · >15% eccellente — la soglia chiave è superare il costo del capitale (~7-10%)',
        scoreKey: 'roic',
    },
    {
        key: 'operating_margin',
        label: 'Margine operativo',
        suffix: '%',
        description:
            "Dice quanto resta delle vendite dopo aver pagato i costi diretti del business (produzione, personale, marketing, affitti) ma prima di tasse e interessi sul debito. È una misura del potere di prezzo e dell'efficienza operativa, isolata da scelte di finanziamento o fiscali che variano molto da un'azienda all'altra. Confrontalo sempre con aziende dello stesso settore: il margine operativo \"normale\" di un supermercato (2-4%) è bassissimo rispetto a quello di un'azienda software (30%+), ma non significa che il supermercato sia gestito peggio — sono semplicemente modelli di business diversi.",
        ranges: '<10% basso · 10-20% media · 20-30% buono · >30% eccellente (dipende molto dal settore)',
        scoreKey: 'operating_margin',
    },
    {
        key: 'net_margin',
        label: 'Margine netto',
        suffix: '%',
        description:
            "È l'ultima riga del conto economico: quanto profitto resta davvero all'azienda (e quindi potenzialmente agli azionisti, come dividendi o riacquisti di azioni) dopo tutte le spese, incluse tasse e interessi sul debito. È l'indicatore di redditività più \"completo\", ma anche il più sensibile a eventi straordinari (una causa legale, la vendita di un ramo d'azienda, un cambio normativo) che possono farlo oscillare molto in un singolo anno senza riflettere la reale salute del business. Se lo vedi molto diverso dal margine operativo, vale la pena capire perché.",
        ranges: '<5% basso · 5-10% media · 10-20% buono · >20% eccellente (dipende molto dal settore)',
        scoreKey: 'net_margin',
    },
    {
        key: 'gross_margin',
        label: 'Gross Margin',
        suffix: '%',
        description:
            "Misura quanto resta dei ricavi dopo aver sottratto solo il costo diretto di produrre ciò che viene venduto (materie prime, manodopera diretta), prima di qualsiasi altra spesa come marketing, ricerca o management. È il primo indicatore del potere di prezzo di un'azienda: se è alto, l'azienda può assorbire aumenti dei costi o investire molto in crescita senza andare in perdita. Varia moltissimo per settore: un supermercato lavora fisiologicamente con margini lordi bassi (20-30%) perché rivende prodotti altrui con poco valore aggiunto, mentre un'azienda software può avere margini lordi dell'80-90% perché produrre una copia in più del suo prodotto costa quasi zero.",
        ranges: '<20% basso (tipico retail/distribuzione) · 20-40% media · 40-60% buono · >60% eccellente (tipico software/tech)',
        scoreKey: 'gross_margin',
    },
];

const GROWTH_FIELDS: IndicatorField[] = [
    {
        key: 'revenue_growth',
        label: 'Crescita ricavi',
        suffix: '%',
        description:
            "Misura quanto l'azienda sta vendendo in più (o in meno) rispetto all'anno scorso — probabilmente il dato più intuitivo di tutti. È fondamentale perché, senza crescita, è difficile che il prezzo di un'azione salga nel tempo: alla lunga il mercato tende a seguire la crescita del business sottostante. Attenzione però a come cresce: una crescita \"organica\" (più clienti, più vendite agli stessi clienti, nuovi prodotti) è più sana e ripetibile di una crescita ottenuta solo comprando altre aziende, che spesso è più costosa e rischiosa da mantenere nel tempo.",
        ranges: '<0% in calo · 0-5% debole · 5-15% buono · >15% eccellente (verifica se organica o da acquisizioni)',
        scoreKey: 'revenue_growth',
    },
    {
        key: 'eps_growth',
        label: 'Crescita EPS',
        suffix: '%',
        description:
            "Misura quanto cresce la fetta di profitto che spetta a ciascuna azione (EPS = utile per azione), non solo il profitto totale dell'azienda. Se cresce più velocemente dei ricavi può essere un ottimo segnale — l'azienda sta diventando più efficiente, i margini migliorano — ma può anche crescere artificialmente se l'azienda riduce il numero di azioni in circolazione tramite riacquisti (buyback), senza che il business sottostante sia davvero migliorato. Vale sempre la pena confrontarla con la Crescita ricavi per capire quale delle due storie è in corso.",
        ranges: '<0% in calo · 0-10% debole · 10-20% buono · >20% eccellente (verifica se sostenibile)',
        scoreKey: 'eps_growth',
    },
    {
        key: 'revenue_cagr_5y',
        label: 'CAGR ricavi',
        suffix: '%',
        description:
            'Il CAGR (Compound Annual Growth Rate, tasso di crescita annuo composto) calcola una crescita media "livellata" su più anni, invece di guardare solo l\'ultimo. È utile perché un singolo anno può ingannare: un balzo eccezionale dovuto a un evento una tantum (o al contrario un anno debole per una crisi passeggera) può far sembrare l\'azienda molto migliore o peggiore di quanto sia davvero nel lungo periodo. Nota: con il piano gratuito FMP usato da questa app, il calcolo copre in pratica circa 4 anni, non esattamente 5.',
        ranges: '<0% in calo · 0-5% debole · 5-15% buono · >15% eccellente',
        scoreKey: 'revenue_cagr_5y',
    },
    {
        key: 'eps_cagr_5y',
        label: 'CAGR EPS',
        suffix: '%',
        description:
            'La stessa logica del CAGR ricavi, applicata all\'utile per azione: una crescita media "livellata" su più anni (circa 4, per il limite del piano gratuito FMP), meno soggetta a un singolo anno anomalo. Confrontalo con il CAGR ricavi: se l\'EPS cresce molto più dei ricavi per diversi anni di fila, capisci se è merito di margini in miglioramento (buon segno) o solo di riacquisti di azioni continui (da guardare con più cautela, perché non riflette una crescita reale del business).',
        ranges: '<0% in calo · 0-10% debole · 10-20% buono · >20% eccellente',
        scoreKey: 'eps_cagr_5y',
    },
];

const SOLIDITY_FIELDS: IndicatorField[] = [
    {
        key: 'debt_to_ebitda',
        label: 'Debito/EBITDA',
        suffix: 'x',
        description:
            "Risponde a una domanda molto concreta: se l'azienda dedicasse tutto il suo utile operativo (EBITDA) a ripagare i debiti, quanti anni ci vorrebbero? È uno degli indicatori di solidità finanziaria più usati perché semplice da capire e da confrontare tra aziende di settori diversi. Qui, a differenza della maggior parte degli altri indicatori, un numero più basso è sempre meglio: significa meno rischio in caso di crisi economica, tassi di interesse alti o un anno di utili deboli. Aziende con debito molto alto (>4-5x) possono trovarsi in seria difficoltà se le condizioni di mercato peggiorano improvvisamente.",
        ranges: '<1x molto solido · 1-2x sano · 2-4x accettabile · >4x rischioso (qui vale il contrario: meno è meglio)',
        scoreKey: 'debt_to_ebitda',
    },
    {
        key: 'interest_coverage',
        label: 'Interest Coverage',
        suffix: 'x',
        description:
            "Dice quante volte l'utile operativo dell'azienda riuscirebbe a coprire gli interessi che deve pagare sul suo debito in un anno. È un test di sicurezza molto pratico: se il valore è vicino a 1x, l'azienda spende quasi tutto il suo utile operativo solo per pagare gli interessi, lasciando pochissimo margine se gli affari andassero anche solo leggermente peggio. Più è alto, più l'azienda può assorbire un brutto trimestre o un aumento dei tassi senza rischiare di non riuscire a onorare i propri debiti.",
        ranges: '<2x rischioso · 2-5x accettabile · 5-10x buono · >10x molto solido',
        scoreKey: 'interest_coverage',
    },
    {
        key: 'current_ratio',
        label: 'Current Ratio',
        suffix: 'x',
        description:
            "Confronta ciò che l'azienda possiede e può trasformare in cassa entro un anno (attivo corrente: cassa, crediti verso clienti, magazzino) con ciò che deve pagare entro un anno (passivo corrente: fornitori, debiti a breve). È un test di liquidità a breve termine, diverso dal Debito/EBITDA che guarda invece alla solidità sul lungo periodo. Un valore sotto 1x è un campanello d'allarme: sulla carta l'azienda non avrebbe abbastanza risorse liquidabili per pagare tutto ciò che deve nei prossimi 12 mesi. Un valore molto alto (>3x) non è automaticamente positivo: può indicare troppa liquidità o magazzino inutilizzato invece di essere investiti nel business.",
        ranges: '<1x rischio di liquidità · 1-1.5x accettabile · 1.5-2x buono · >2x molto solido (se eccessivo, verifica che non siano asset inutilizzati)',
        scoreKey: 'current_ratio',
    },
];

const CASH_FLOW_FIELDS: IndicatorField[] = [
    {
        key: 'free_cash_flow',
        label: 'Free Cash Flow',
        suffix: '',
        description:
            "Per molti investitori è il numero più importante di tutti: rappresenta la liquidità che resta davvero nelle casse dell'azienda dopo aver pagato tutte le spese operative e gli investimenti necessari per mantenere/far crescere il business (nuovi macchinari, uffici, ecc.). A differenza dell'utile netto, che è un numero contabile e può essere influenzato da poste non monetarie (ammortamenti, svalutazioni, accantonamenti), il Free Cash Flow è cash vero: quello che l'azienda può usare per pagare dividendi, riacquistare azioni, ridurre debito o fare acquisizioni.",
        ranges: 'Non ha un range assoluto: guarda il FCF Margin o il FCF Yield qui sotto',
    },
    {
        key: 'fcf_margin',
        label: 'FCF Margin',
        suffix: '%',
        description:
            "È il Free Cash Flow diviso per i ricavi: dice quanti centesimi di liquidità reale l'azienda riesce a trasformare da ogni euro di vendite, dopo aver pagato tutto (spese operative e investimenti). È spesso più affidabile del margine netto perché più difficile da \"abbellire\" con scelte contabili — la cassa in banca c'è o non c'è. Un'azienda con FCF Margin alto e stabile ha molta più libertà (dividendi crescenti, meno debito, nuove opportunità) rispetto a una che genera utili contabili ma poca cassa reale.",
        ranges: '<5% basso · 5-10% media · 10-20% buono · >20% eccellente',
        scoreKey: 'fcf_margin',
    },
    {
        key: 'fcf_yield',
        label: 'FCF Yield',
        suffix: '%',
        description:
            "Mette in relazione il Free Cash Flow con quanto costa oggi l'intera azienda in borsa (capitalizzazione di mercato). In pratica risponde alla domanda \"se comprassi tutta l'azienda oggi, che rendimento in cassa otterrei ogni anno rispetto a quanto ho pagato?\". È concettualmente simile al rendimento di un'obbligazione o di un immobile da affitto, e molti investitori lo preferiscono al più famoso P/E proprio perché si basa su cassa reale invece che su utili contabili. Un FCF Yield alto può segnalare un'azione a buon prezzo, ma verifica sempre che non sia alto solo perché il prezzo è crollato per problemi seri del business.",
        ranges: '<3% basso · 3-6% normale · >6% interessante',
        scoreKey: 'fcf_yield',
    },
];

const VALUATION_FIELDS: IndicatorField[] = [
    {
        key: 'pe_ratio',
        label: 'P/E',
        suffix: '',
        description:
            "Il P/E (Price/Earnings, prezzo/utili) è probabilmente l'indicatore di valutazione più conosciuto: dice quante volte l'utile annuo dell'azienda stai pagando per comprare l'azione. Un P/E di 20 significa, semplificando, che ci vorrebbero 20 anni di utili attuali per \"ripagare\" il prezzo pagato oggi (se l'utile restasse costante, cosa che raramente accade). Un P/E basso non è automaticamente un affare: può riflettere problemi reali dell'azienda o aspettative di crescita basse; un P/E alto può essere del tutto giustificato se il mercato si aspetta una crescita molto forte — per questo va sempre letto insieme alla crescita (vedi PEG) e confrontato con aziende simili, non da solo.",
        ranges: '~10 economica (o in difficoltà) · ~20 media · ~30 cara ma giustificabile con crescita alta · ~50 molto cara',
        scoreKey: 'pe_ratio',
    },
    {
        key: 'peg_ratio',
        label: 'PEG',
        suffix: '',
        description:
            "Prende il P/E e lo divide per il tasso di crescita atteso degli utili, per rispondere a una domanda più raffinata del semplice P/E: sto pagando un prezzo alto ma giustificato da una crescita altrettanto alta, o sto pagando un prezzo alto per una crescita mediocre? Un PEG intorno a 1 suggerisce che il prezzo è ragionevolmente in linea con le aspettative di crescita; un PEG molto sotto 1 può indicare un'occasione (o una crescita attesa troppo ottimistica che potrebbe non realizzarsi); un PEG molto sopra 2 suggerisce che stai pagando un premio importante per quella crescita.",
        ranges: '<1 interessante · ≈1 valutazione equilibrata · >2 valutazione molto impegnativa',
        scoreKey: 'peg_ratio',
    },
    {
        key: 'ev_to_ebitda',
        label: 'EV/EBITDA',
        suffix: 'x',
        description:
            "Confronta il valore complessivo dell'azienda (Enterprise Value: capitalizzazione di mercato più debito netto, cioè quanto costerebbe davvero rilevare l'intera azienda, debiti inclusi) con il suo utile operativo lordo (EBITDA). È uno dei multipli preferiti per confrontare aziende con strutture di debito molto diverse tra loro, cosa che il P/E non riesce a fare bene perché ignora completamente il debito. Molto usato anche nelle acquisizioni aziendali come punto di riferimento per capire quanto \"vale\" un'azienda indipendentemente da come è finanziata.",
        ranges: '<10x economica · 10-15x normale · >20x valutazione elevata',
        scoreKey: 'ev_to_ebitda',
    },
    {
        key: 'ev_to_fcf',
        label: 'EV/FCF',
        suffix: 'x',
        description:
            "È la versione \"cash-based\" dell'EV/EBITDA: invece di guardare l'utile operativo contabile, confronta il valore complessivo dell'azienda con la liquidità reale che genera (Free Cash Flow). È spesso considerato più affidabile dell'EV/EBITDA perché l'EBITDA può nascondere investimenti pesanti in macchinari o infrastrutture che consumano cassa reale ma non appaiono in quel numero — due aziende con lo stesso EBITDA possono avere una salute finanziaria molto diversa se una richiede investimenti continui e pesanti e l'altra no.",
        ranges: '<15x economica · 15-25x normale · 25-35x cara · >50x molto cara',
        scoreKey: 'ev_to_fcf',
    },
    {
        key: 'price_to_sales',
        label: 'Price/Sales',
        suffix: 'x',
        description:
            "Confronta il prezzo dell'azione con i ricavi per azione, ignorando completamente se l'azienda è in utile o in perdita. È particolarmente utile per aziende giovani o in forte crescita che reinvestono tutto (o quasi) nel business e quindi non hanno ancora utili significativi da mostrare, dove il P/E semplicemente non è calcolabile o è fuorviante. Il rovescio della medaglia: un P/S basso non garantisce che l'azienda diventerà mai profittevole, quindi va sempre affiancato da un'occhiata ai margini (operativo, netto) per capire se c'è un percorso credibile verso la redditività.",
        ranges: '<1x economica · 1-3x normale · 3-6x cara · >10x molto cara (dipende molto dal settore/marginalità)',
        scoreKey: 'price_to_sales',
    },
    {
        key: 'fair_value',
        label: 'Fair Value',
        suffix: '',
        description:
            "È la tua stima di quanto dovrebbe valere davvero un'azione, indipendentemente da quanto la sta scambiando oggi il mercato — calcolabile qui sotto con il metodo DCF (Discounted Cash Flow), oppure inserito manualmente se preferisci un'altra fonte. Confrontando questo numero con il prezzo di mercato attuale, il sistema calcola automaticamente il verdetto di valutazione (sottovalutata/corretta/cara) e i prezzi-soglia suggeriti in fondo alla tab. Ricorda sempre che è una stima, sensibile alle ipotesi che inserisci (crescita, margini, tasso di sconto): piccole variazioni negli input possono cambiare significativamente il risultato, quindi trattalo come un punto di partenza per riflettere, non come un numero esatto.",
        ranges: '',
    },
];

const ALL_INDICATOR_FIELDS = [
    ...PROFITABILITY_FIELDS,
    ...GROWTH_FIELDS,
    ...SOLIDITY_FIELDS,
    ...CASH_FLOW_FIELDS,
    ...VALUATION_FIELDS,
];

type Explanation = { label: string; description: string; ranges: string };

const TECHNICAL_EXPLANATIONS: Record<
    'sma20' | 'rsi14' | 'support' | 'resistance',
    Explanation
> = {
    sma20: {
        label: 'Media mobile (20gg)',
        description:
            "La media mobile a 20 giorni è la media del prezzo di chiusura degli ultimi 20 giorni di contrattazione (circa un mese di borsa). Livella le oscillazioni giornaliere per mostrare la direzione di fondo del prezzo: se il prezzo attuale è sopra la media, il trend recente è tendenzialmente al rialzo; se è sotto, tendenzialmente al ribasso. È uno strumento di analisi tecnica basato solo sul prezzo passato, utile per capire il clima di breve periodo — non dice nulla sulla qualità dell'azienda, che va valutata con gli indicatori fondamentali nelle altre tab.",
        ranges: 'Prezzo sopra la media → trend recente al rialzo · Prezzo sotto la media → trend recente al ribasso',
    },
    rsi14: {
        label: 'RSI (14)',
        description:
            "L'RSI (Relative Strength Index) a 14 giorni misura la velocità e l'intensità dei movimenti di prezzo recenti, su una scala da 0 a 100, per capire se un titolo è stato comprato o venduto troppo velocemente nel breve periodo. Sopra 70 si parla di ipercomprato (il prezzo è salito molto in fretta, possibile pausa o correzione in arrivo); sotto 30 di ipervenduto (il prezzo è sceso molto in fretta, possibile rimbalzo). Non è un segnale di acquisto/vendita automatico: un titolo ipercomprato può restare tale a lungo se il trend di fondo è molto forte.",
        ranges: '<30 ipervenduto · 30-70 neutro · >70 ipercomprato',
    },
    support: {
        label: 'Supporto più vicino',
        description:
            'Il supporto è il livello di prezzo più vicino, sotto quello attuale, dove il titolo ha già "rimbalzato" verso l\'alto in passato (un minimo locale non superato al ribasso dai prezzi vicini). L\'idea è che, se il prezzo torna a scendere fino a lì, potrebbe incontrare di nuovo interesse all\'acquisto. È una lettura puramente grafica basata sullo storico recente, non una garanzia: i supporti possono essere "rotti" in qualunque momento, specie in presenza di brutte notizie sull\'azienda.',
        ranges: '',
    },
    resistance: {
        label: 'Resistenza più vicina',
        description:
            'La resistenza è il livello di prezzo più vicino, sopra quello attuale, dove il titolo ha già incontrato vendite che ne hanno fermato la salita in passato (un massimo locale non superato dai prezzi vicini). Se il prezzo sale fino a lì, potrebbe rallentare o invertire. Come il supporto, è una lettura grafica indicativa: superare una resistenza con decisione (breakout) è spesso interpretato come un segnale di forza.',
        ranges: '',
    },
};

function smaSignalText(signal: MarketAnalysis['sma20_signal']) {
    if (signal === 'above') {
        return 'Prezzo sopra la media: possibile trend rialzista';
    }

    if (signal === 'below') {
        return 'Prezzo sotto la media: possibile trend ribassista';
    }

    return 'Storico insufficiente';
}

function rsiSignalText(signal: MarketAnalysis['rsi14_signal']) {
    switch (signal) {
        case 'overbought':
            return 'Ipercomprato';
        case 'oversold':
            return 'Ipervenduto';
        case 'neutral':
            return 'Neutro';
        default:
            return 'Storico insufficiente';
    }
}

function rsiSignalClass(signal: MarketAnalysis['rsi14_signal']) {
    return signal === 'overbought' || signal === 'oversold'
        ? 'text-amber-600'
        : 'text-muted-foreground';
}

// The shadcn Input wrapper's `default-value` (via VueUse's useVModel) only
// seeds its internal value once, at first mount - it never re-syncs if the
// prop changes later (e.g. after "Aggiorna da FMP" or a save brings back
// fresh `analysis` data). Binding these fields as properly controlled
// v-model inputs, reset by this watcher, avoids that trap entirely instead
// of relying on forcing a remount.
const indicatorValues = reactive<Record<string, number | string>>({});

function syncIndicatorValues() {
    for (const field of ALL_INDICATOR_FIELDS) {
        indicatorValues[field.key] =
            (props.analysis[field.key] as number | null) ?? '';
    }
}

syncIndicatorValues();
watch(() => props.analysis, syncIndicatorValues);

const explanationField = ref<Explanation | null>(null);

function formatDate(value: string | null) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('it-IT', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPrice(value: number | null) {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: props.analysis.indicators_currency ?? 'USD',
    }).format(value);
}

function formatPercent(value: number | null) {
    if (value === null) {
        return '—';
    }

    return `${value > 0 ? '+' : ''}${new Intl.NumberFormat('it-IT', { maximumFractionDigits: 2 }).format(value)}%`;
}

function destroyAnalysis() {
    if (
        confirm(
            `Eliminare l'analisi di "${props.analysis.name}"? L'operazione non può essere annullata.`,
        )
    ) {
        router.delete(companyAnalysisRoutes.destroy(props.analysis.id).url);
    }
}
</script>

<template>
    <Head :title="analysis.name" />

    <div class="mx-auto flex w-full max-w-[64rem] flex-col space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="analysis.name" :description="analysis.symbol" />
            <Button
                variant="destructive"
                class="shrink-0"
                @click="destroyAnalysis"
                >Elimina analisi</Button
            >
        </div>

        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-xs text-muted-foreground">
                    {{
                        analysis.price_history_fetched_at
                            ? `Ultimo aggiornamento grafico: ${formatDate(analysis.price_history_fetched_at)}`
                            : 'Grafico mai caricato: aggiornalo per scaricare lo storico prezzi da EODHD.'
                    }}
                </p>
                <Form
                    v-bind="
                        CompanyAnalysisController.refreshPriceHistory.form(
                            analysis.id,
                        )
                    "
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        class="shrink-0"
                        :disabled="processing"
                    >
                        {{ processing ? 'Aggiornamento…' : 'Aggiorna grafico' }}
                    </Button>
                </Form>
            </div>
            <CandlestickChart :candles="priceHistory" />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
        >
            <p class="text-xs text-muted-foreground">
                {{
                    analysis.indicators_fetched_at
                        ? `Ultimo aggiornamento automatico: ${formatDate(analysis.indicators_fetched_at)}`
                        : 'Mai aggiornato automaticamente: inserisci i valori a mano o prova ad aggiornarli da FMP.'
                }}
            </p>
            <Form
                v-bind="
                    CompanyAnalysisController.refreshIndicators.form(
                        analysis.id,
                    )
                "
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    class="shrink-0"
                    :disabled="processing"
                >
                    {{ processing ? 'Aggiornamento…' : 'Aggiorna da FMP' }}
                </Button>
            </Form>
        </div>

        <Tabs
            default-value="analisi-tecnica"
            orientation="vertical"
            class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10"
        >
            <TabsList
                class="flex h-auto w-full flex-none flex-col items-stretch justify-start gap-1 rounded-none bg-transparent p-0 lg:w-56"
            >
                <TabsTrigger
                    value="analisi-tecnica"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Analisi tecnica</TabsTrigger
                >
                <TabsTrigger
                    value="redditivita"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Redditività</TabsTrigger
                >
                <TabsTrigger
                    value="crescita"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Crescita</TabsTrigger
                >
                <TabsTrigger
                    value="solidita"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Solidità</TabsTrigger
                >
                <TabsTrigger
                    value="cash-flow"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Cash Flow</TabsTrigger
                >
                <TabsTrigger
                    value="valutazione"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Valutazione</TabsTrigger
                >
                <TabsTrigger
                    value="buffett"
                    class="h-auto w-full flex-none justify-start rounded-md px-3 py-2 text-left text-sm font-medium data-[state=active]:bg-muted data-[state=active]:shadow-none"
                    >Buffett</TabsTrigger
                >
            </TabsList>

            <div class="min-w-0 flex-1">
                <TabsContent value="analisi-tecnica">
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="grid gap-2">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs text-muted-foreground">
                                    Media mobile (20gg)
                                </p>
                                <button
                                    type="button"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Spiegazione: Media mobile (20gg)"
                                    @click="
                                        explanationField =
                                            TECHNICAL_EXPLANATIONS.sma20
                                    "
                                >
                                    ?
                                </button>
                            </div>
                            <p class="font-medium">
                                {{
                                    technicalAnalysis.sma20 !== null
                                        ? formatPrice(technicalAnalysis.sma20)
                                        : '—'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    smaSignalText(
                                        technicalAnalysis.sma20_signal,
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs text-muted-foreground">
                                    RSI (14)
                                </p>
                                <button
                                    type="button"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Spiegazione: RSI (14)"
                                    @click="
                                        explanationField =
                                            TECHNICAL_EXPLANATIONS.rsi14
                                    "
                                >
                                    ?
                                </button>
                            </div>
                            <p class="font-medium">
                                {{ technicalAnalysis.rsi14 ?? '—' }}
                            </p>
                            <p
                                class="text-xs"
                                :class="
                                    rsiSignalClass(
                                        technicalAnalysis.rsi14_signal,
                                    )
                                "
                            >
                                {{
                                    rsiSignalText(
                                        technicalAnalysis.rsi14_signal,
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs text-muted-foreground">
                                    Supporto più vicino
                                </p>
                                <button
                                    type="button"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Spiegazione: Supporto più vicino"
                                    @click="
                                        explanationField =
                                            TECHNICAL_EXPLANATIONS.support
                                    "
                                >
                                    ?
                                </button>
                            </div>
                            <p class="font-medium">
                                {{
                                    technicalAnalysis.support !== null
                                        ? formatPrice(technicalAnalysis.support)
                                        : '—'
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs text-muted-foreground">
                                    Resistenza più vicina
                                </p>
                                <button
                                    type="button"
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Spiegazione: Resistenza più vicina"
                                    @click="
                                        explanationField =
                                            TECHNICAL_EXPLANATIONS.resistance
                                    "
                                >
                                    ?
                                </button>
                            </div>
                            <p class="font-medium">
                                {{
                                    technicalAnalysis.resistance !== null
                                        ? formatPrice(
                                              technicalAnalysis.resistance,
                                          )
                                        : '—'
                                }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-6 text-xs text-muted-foreground">
                        Indicatori calcolati automaticamente sullo storico
                        prezzi caricato nel grafico qui sopra: non costituiscono
                        un consiglio di investimento.
                    </p>
                </TabsContent>

                <TabsContent value="redditivita">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <IndicatorFieldGrid
                            :fields="PROFITABILITY_FIELDS"
                            :scores="analysis.scores"
                            :values="indicatorValues"
                            :errors="errors"
                            @explain="(field) => (explanationField = field)"
                            @update="
                                (key, value) => (indicatorValues[key] = value)
                            "
                        />

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva redditività</Button
                            >
                        </div>
                    </Form>
                </TabsContent>

                <TabsContent value="crescita">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <IndicatorFieldGrid
                            :fields="GROWTH_FIELDS"
                            :scores="analysis.scores"
                            :values="indicatorValues"
                            :errors="errors"
                            @explain="(field) => (explanationField = field)"
                            @update="
                                (key, value) => (indicatorValues[key] = value)
                            "
                        />

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva crescita</Button
                            >
                        </div>
                    </Form>
                </TabsContent>

                <TabsContent value="solidita">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <IndicatorFieldGrid
                            :fields="SOLIDITY_FIELDS"
                            :scores="analysis.scores"
                            :values="indicatorValues"
                            :errors="errors"
                            @explain="(field) => (explanationField = field)"
                            @update="
                                (key, value) => (indicatorValues[key] = value)
                            "
                        />

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva solidità</Button
                            >
                        </div>
                    </Form>
                </TabsContent>

                <TabsContent value="cash-flow">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <IndicatorFieldGrid
                            :fields="CASH_FLOW_FIELDS"
                            :scores="analysis.scores"
                            :values="indicatorValues"
                            :errors="errors"
                            @explain="(field) => (explanationField = field)"
                            @update="
                                (key, value) => (indicatorValues[key] = value)
                            "
                        />

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva cash flow</Button
                            >
                        </div>
                    </Form>
                </TabsContent>

                <TabsContent value="valutazione">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <IndicatorFieldGrid
                            :fields="VALUATION_FIELDS"
                            :scores="analysis.scores"
                            :values="indicatorValues"
                            :errors="errors"
                            @explain="(field) => (explanationField = field)"
                            @update="
                                (key, value) => (indicatorValues[key] = value)
                            "
                        />

                        <DcfCalculator
                            :market-cap="analysis.market_cap"
                            :current-price="analysis.current_price"
                            :currency="analysis.indicators_currency"
                            @apply="
                                (value) => (indicatorValues.fair_value = value)
                            "
                        />

                        <div class="grid gap-2">
                            <Label for="historical_comparison"
                                >Confronto con la media storica</Label
                            >
                            <textarea
                                id="historical_comparison"
                                name="historical_comparison"
                                :value="analysis.historical_comparison ?? ''"
                                rows="3"
                                placeholder="Es. P/E medio ultimi 10 anni: 30, oggi: 45..."
                                class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">
                                L'azienda oggi è più cara del suo passato? Se il
                                business non è cambiato ma la valutazione sì,
                                chiediti se il premio è giustificato.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="competitor_comparison"
                                >Confronto con i concorrenti</Label
                            >
                            <textarea
                                id="competitor_comparison"
                                name="competitor_comparison"
                                :value="analysis.competitor_comparison ?? ''"
                                rows="3"
                                placeholder="Es. rispetto ad Alphabet, Amazon, Oracle..."
                                class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">
                                Se quota molto più cara di tutti i concorrenti
                                diretti, capisci il motivo prima di comprare.
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva valutazione</Button
                            >
                        </div>
                    </Form>

                    <div class="mt-8 rounded-lg border p-6 text-center">
                        <p
                            class="text-xs tracking-wide text-muted-foreground uppercase"
                        >
                            Valutazione Finale
                        </p>
                        <p
                            v-if="analysis.valuation.verdict"
                            class="mt-2 text-3xl font-bold"
                            :class="
                                VERDICT_META[analysis.valuation.verdict].class
                            "
                        >
                            {{ VERDICT_META[analysis.valuation.verdict].emoji }}
                            {{ VERDICT_META[analysis.valuation.verdict].label }}
                        </p>
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            Inserisci il Fair Value (e, se possibile, il prezzo
                            attuale) per vedere la valutazione.
                        </p>
                    </div>

                    <div
                        v-if="analysis.fair_value !== null"
                        class="mt-6 rounded-lg border p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold">
                            Decisione Operativa
                        </h3>
                        <dl
                            class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4"
                        >
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Prezzo attuale
                                </dt>
                                <dd class="font-medium">
                                    {{ formatPrice(analysis.current_price) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Fair Value
                                </dt>
                                <dd class="font-medium">
                                    {{ formatPrice(analysis.fair_value) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Scostamento
                                </dt>
                                <dd class="font-medium">
                                    {{
                                        formatPercent(
                                            analysis.valuation
                                                .deviation_percent,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Valutazione
                                </dt>
                                <dd
                                    v-if="analysis.valuation.verdict"
                                    class="font-medium"
                                    :class="
                                        VERDICT_META[analysis.valuation.verdict]
                                            .class
                                    "
                                >
                                    {{
                                        VERDICT_META[analysis.valuation.verdict]
                                            .emoji
                                    }}
                                    {{
                                        VERDICT_META[analysis.valuation.verdict]
                                            .label
                                    }}
                                </dd>
                                <dd
                                    v-else
                                    class="font-medium text-muted-foreground"
                                >
                                    —
                                </dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-muted-foreground">
                                    Azione consigliata
                                </dt>
                                <dd
                                    v-if="analysis.valuation.verdict"
                                    class="font-medium"
                                    :class="
                                        VERDICT_META[analysis.valuation.verdict]
                                            .class
                                    "
                                >
                                    {{
                                        VERDICT_META[analysis.valuation.verdict]
                                            .emoji
                                    }}
                                    {{ analysis.valuation.recommended_action }}
                                </dd>
                                <dd
                                    v-else
                                    class="font-medium text-muted-foreground"
                                >
                                    —
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Prezzo per iniziare ad acquistare
                                </dt>
                                <dd class="font-medium">
                                    &lt;
                                    {{
                                        formatPrice(
                                            analysis.valuation.entry_price,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Prezzo ideale per accumulare con decisione
                                </dt>
                                <dd class="font-medium">
                                    &lt;
                                    {{
                                        formatPrice(
                                            analysis.valuation.accumulate_price,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Soglie indicative (margine di sicurezza 5%/15% sul
                            Fair Value), non una raccomandazione di
                            investimento.
                        </p>
                    </div>
                </TabsContent>

                <TabsContent value="buffett">
                    <Form
                        :key="analysis.updated_at"
                        v-bind="
                            CompanyAnalysisController.update.form(analysis.id)
                        "
                        class="space-y-6"
                        v-slot="{ processing }"
                    >
                        <div
                            v-for="(question, index) in buffettQuestions"
                            :key="question.key"
                            class="space-y-2 rounded-lg border p-4"
                        >
                            <input
                                type="hidden"
                                :name="`buffett_answers[${index}][key]`"
                                :value="question.key"
                            />

                            <div class="flex items-start justify-between gap-4">
                                <p class="font-medium">{{ question.label }}</p>
                                <div class="flex shrink-0 items-center gap-4">
                                    <label
                                        class="flex items-center gap-1.5 text-sm"
                                    >
                                        <input
                                            type="radio"
                                            :name="`buffett_answers[${index}][answer]`"
                                            value="1"
                                            :checked="question.answer === true"
                                        />
                                        Sì
                                    </label>
                                    <label
                                        class="flex items-center gap-1.5 text-sm"
                                    >
                                        <input
                                            type="radio"
                                            :name="`buffett_answers[${index}][answer]`"
                                            value="0"
                                            :checked="question.answer === false"
                                        />
                                        No
                                    </label>
                                </div>
                            </div>

                            <textarea
                                :name="`buffett_answers[${index}][notes]`"
                                :value="question.notes ?? ''"
                                rows="2"
                                placeholder="Note (opzionale)"
                                class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:ring-destructive/40"
                            ></textarea>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing"
                                >Salva risposte</Button
                            >
                        </div>
                    </Form>
                </TabsContent>
            </div>
        </Tabs>

        <Sheet
            :open="explanationField !== null"
            @update:open="
                (open) => {
                    if (!open) explanationField = null;
                }
            "
        >
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle>{{ explanationField?.label }}</SheetTitle>
                    <SheetDescription>{{
                        explanationField?.description
                    }}</SheetDescription>
                </SheetHeader>
                <p
                    v-if="explanationField?.ranges"
                    class="px-4 text-sm text-muted-foreground italic"
                >
                    {{ explanationField.ranges }}
                </p>
            </SheetContent>
        </Sheet>
    </div>
</template>
