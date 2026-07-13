<?php

namespace App\Services;

final class AuthenticityScorer
{
    /**
     * Score the available text signals without making visual or truthfulness claims.
     */
    public function score(string $text, bool $hasImage = false): float
    {
        $text = trim($text);
        $lower = mb_strtolower($text, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}\']+/u', $lower, $wordMatches);
        $words = $wordMatches[0];
        $wordCount = count($words);
        $score = 0.45;

        if (preg_match('/\b(i|i\'m|i\'ve|me|my|mine|we|we\'re|our|ours)\b/ui', $text) === 1) {
            $score += 0.12;
        }

        if (preg_match('/\b(i felt|i learned|i realized|i remember|made me|reminded me|needed that|grateful|honestly)\b/ui', $text) === 1) {
            $score += 0.12;
        }

        if (preg_match('/\b(today|yesterday|tonight|this morning|this evening|somehow|really|just)\b/ui', $text) === 1) {
            $score += 0.08;
        }

        if ($wordCount >= 8) {
            $uniqueWords = count(array_unique($words));
            $lexicalDiversity = $wordCount > 0 ? $uniqueWords / $wordCount : 0;
            $score += $lexicalDiversity >= 0.55 ? 0.10 : 0;
            $score += preg_match('/[.!?]/u', $text) === 1 ? 0.06 : 0;
        }

        $score += $hasImage ? 0.02 : 0;

        $hashtagCount = preg_match_all('/#[\p{L}\p{N}_]+/u', $text);
        if ($hashtagCount > 2) {
            $score -= min(0.20, ($hashtagCount - 2) * 0.04);
        }

        if (preg_match('/https?:\/\/|www\./ui', $text) === 1) {
            $score -= 0.15;
        }

        preg_match_all('/\p{L}/u', $text, $letterMatches);
        preg_match_all('/\p{Lu}/u', $text, $uppercaseMatches);
        $letterCount = count($letterMatches[0]);
        if ($letterCount >= 10 && count($uppercaseMatches[0]) / $letterCount > 0.45) {
            $score -= 0.18;
        }

        if (preg_match('/\b(buy now|limited time|act now|click here|subscribe now|best deal|free offer)\b/ui', $text) === 1) {
            $score -= 0.22;
        }

        if (preg_match('/(.)\1{4,}/u', $text) === 1) {
            $score -= 0.12;
        }

        if ($wordCount < 6) {
            $score -= 0.20;
        }

        return round(max(0.0, min(1.0, $score)), 4);
    }
}
