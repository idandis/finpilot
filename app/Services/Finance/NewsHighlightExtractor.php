<?php

namespace App\Services\Finance;

use App\Models\InstrumentNews;
use Illuminate\Support\Collection;

/**
 * A rule-based "highlights" summary for a position's News tab: no LLM, no
 * external call - just pattern-matching over titles/content already cached
 * in the database. "Most relevant" is approximated as "most emotionally
 * charged" (largest absolute sentiment score), since that's the only signal
 * the news feed gives us for how market-moving an article might be; ties
 * fall back to whatever order the caller already sorted articles in
 * (normally most-recent-first, since PHP/Laravel sorts are stable).
 */
class NewsHighlightExtractor
{
    private const HIGHLIGHT_LIMIT = 5;

    private const FIGURES_PER_HIGHLIGHT = 5;

    /**
     * Percentages ("48%", "-4.57%"), currency amounts with an optional
     * scale word ("$200 Billion", "€1.2 million"), and bare scaled numbers
     * without a currency symbol ("200 billion barrels"). Deliberately
     * approximate - this is pattern-matching, not real NLP, so it will
     * miss/mismatch some numbers in unusual phrasing.
     */
    private const FIGURE_PATTERN = '/(?:[$€£]\s?\d[\d,.]*(?:\s?(?:trillion|billion|million|bn|mln|bln))?|\d[\d,.]*\s?(?:trillion|billion|million)|[+-]?\d+(?:\.\d+)?%)/iu';

    /**
     * @param  Collection<int, InstrumentNews>  $articles
     * @return array<int, array{id: int, title: string, url: string|null, published_at: string, sentiment_polarity: float|null, figures: array<int, string>}>
     */
    public function extract(Collection $articles): array
    {
        return $articles
            ->sortByDesc(fn (InstrumentNews $article) => abs((float) ($article->sentiment_polarity ?? 0)))
            ->take(self::HIGHLIGHT_LIMIT)
            ->map(fn (InstrumentNews $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'url' => $article->url,
                'published_at' => $article->published_at->toIso8601String(),
                'sentiment_polarity' => $article->sentiment_polarity !== null ? (float) $article->sentiment_polarity : null,
                'figures' => $this->extractFigures("{$article->title} {$article->content}"),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractFigures(string $text): array
    {
        preg_match_all(self::FIGURE_PATTERN, $text, $matches);

        return collect($matches[0])
            ->map(fn (string $figure) => trim($figure))
            ->unique()
            ->take(self::FIGURES_PER_HIGHLIGHT)
            ->values()
            ->all();
    }
}
