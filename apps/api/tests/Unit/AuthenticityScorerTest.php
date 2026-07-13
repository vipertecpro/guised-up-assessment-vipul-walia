<?php

namespace Tests\Unit;

use App\Services\AuthenticityScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthenticityScorerTest extends TestCase
{
    public function test_personal_reflection_scores_above_heavily_promotional_text(): void
    {
        $scorer = new AuthenticityScorer;

        $personal = $scorer->score(
            'I realized today that the quiet walk home was exactly what I needed.',
        );
        $promotional = $scorer->score(
            'BUY NOW!!! LIMITED TIME https://example.com #deal #sale #offer #viral WOOOOOW',
        );

        $this->assertGreaterThan($promotional, $personal);
    }

    #[DataProvider('textSamples')]
    public function test_scores_are_deterministic_unicode_safe_and_bounded(string $text): void
    {
        $scorer = new AuthenticityScorer;
        $first = $scorer->score($text, true);

        $this->assertSame($first, $scorer->score($text, true));
        $this->assertGreaterThanOrEqual(0.0, $first);
        $this->assertLessThanOrEqual(1.0, $first);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textSamples(): array
    {
        return [
            'unicode reflection' => ['आज मैंने अपने पुराने दोस्त से बात की और बहुत अच्छा लगा।'],
            'very short' => ['Hello'],
            'repeated promotion' => ['FREE OFFER!!!!! BUY NOW BUY NOW #one #two #three #four'],
        ];
    }
}
