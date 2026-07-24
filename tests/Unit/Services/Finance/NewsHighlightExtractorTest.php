<?php

namespace Tests\Unit\Services\Finance;

use App\Models\InstrumentNews;
use App\Services\Finance\NewsHighlightExtractor;
use Illuminate\Support\Collection;
use Tests\TestCase;

class NewsHighlightExtractorTest extends TestCase
{
    private function article(array $overrides = []): InstrumentNews
    {
        return InstrumentNews::factory()->make(array_merge([
            'id' => 1,
        ], $overrides));
    }

    public function test_it_ranks_articles_by_absolute_sentiment_descending()
    {
        $mild = $this->article(['id' => 1, 'title' => 'Mild news', 'sentiment_polarity' => 0.10]);
        $stronglyNegative = $this->article(['id' => 2, 'title' => 'Crash fears grow', 'sentiment_polarity' => -0.80]);
        $stronglyPositive = $this->article(['id' => 3, 'title' => 'Record profits', 'sentiment_polarity' => 0.65]);

        $highlights = (new NewsHighlightExtractor)->extract(new Collection([$mild, $stronglyNegative, $stronglyPositive]));

        $this->assertSame(['Crash fears grow', 'Record profits', 'Mild news'], array_column($highlights, 'title'));
    }

    public function test_it_limits_to_the_five_most_relevant_articles()
    {
        $articles = collect(range(1, 8))->map(fn (int $i) => $this->article([
            'id' => $i,
            'sentiment_polarity' => $i / 10,
        ]));

        $highlights = (new NewsHighlightExtractor)->extract($articles);

        $this->assertCount(5, $highlights);
        // The highest sentiment values (0.8, 0.7, 0.6, 0.5, 0.4) must win.
        $this->assertSame([8, 7, 6, 5, 4], array_column($highlights, 'id'));
    }

    public function test_it_treats_a_null_sentiment_as_zero_for_ranking()
    {
        $noSentiment = $this->article(['id' => 1, 'title' => 'No sentiment data', 'sentiment_polarity' => null]);
        $positive = $this->article(['id' => 2, 'title' => 'Some good news', 'sentiment_polarity' => 0.30]);

        $highlights = (new NewsHighlightExtractor)->extract(new Collection([$noSentiment, $positive]));

        $this->assertSame('Some good news', $highlights[0]['title']);
        $this->assertNull($highlights[1]['sentiment_polarity']);
    }

    public function test_it_extracts_percentages_and_currency_amounts_from_the_title()
    {
        $article = $this->article([
            'title' => 'AMD Stock Could Surge 48% as Analysts See a $200 Billion Server CPU Market',
            'content' => 'No extra figures here.',
        ]);

        $highlights = (new NewsHighlightExtractor)->extract(new Collection([$article]));

        $this->assertSame(['48%', '$200 Billion'], $highlights[0]['figures']);
    }

    public function test_it_extracts_figures_from_the_content_when_absent_from_the_title()
    {
        $article = $this->article([
            'title' => 'Quarterly results are in',
            'content' => 'Revenue grew to €1.2 million, up from last year, beating estimates by -3.5%.',
        ]);

        $highlights = (new NewsHighlightExtractor)->extract(new Collection([$article]));

        $this->assertSame(['€1.2 million', '-3.5%'], $highlights[0]['figures']);
    }

    public function test_it_returns_no_figures_when_the_article_has_no_numbers()
    {
        $article = $this->article([
            'title' => 'Analysts remain cautious about the sector',
            'content' => 'No concrete figures were mentioned in this report.',
        ]);

        $highlights = (new NewsHighlightExtractor)->extract(new Collection([$article]));

        $this->assertSame([], $highlights[0]['figures']);
    }

    public function test_it_returns_an_empty_array_for_no_articles()
    {
        $highlights = (new NewsHighlightExtractor)->extract(new Collection);

        $this->assertSame([], $highlights);
    }
}
