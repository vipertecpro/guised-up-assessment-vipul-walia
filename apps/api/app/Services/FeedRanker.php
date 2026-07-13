<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

final class FeedRanker
{
    public const PER_PAGE = 20;

    private const CANDIDATE_LIMIT = 500;

    private const SEMANTIC_LIMIT = 50;

    /**
     * Create a bounded, personalized, in-memory ranking for one authenticated user.
     *
     * @return array{posts: Collection<int, Post>, total: int, last_page: int, semantic_ranking_available: bool}
     */
    public function rank(User $user, int $page, EmbeddingsClient $embeddings): array
    {
        $candidates = Post::query()
            ->with('user:id,name')
            ->where('user_id', '!=', $user->getKey())
            ->where('created_at', '>=', now()->subDays(45))
            ->latest('created_at')
            ->latest('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $relationshipTotals = $this->relationshipTotals($user);
        $strongestRelationship = $relationshipTotals === []
            ? 0.0
            : max(array_values($relationshipTotals));
        $seedDocumentIds = $this->seedDocumentIds($user);
        $semanticScores = [];
        $semanticAvailable = true;

        if ($seedDocumentIds !== []) {
            try {
                $semanticScores = $this->semanticScores(
                    $embeddings->recommend($seedDocumentIds, self::SEMANTIC_LIMIT),
                );
            } catch (Throwable) {
                $semanticAvailable = false;
            }
        }

        $ranked = $candidates->map(function (Post $post) use (
            $relationshipTotals,
            $strongestRelationship,
            $semanticScores,
        ): Post {
            $authenticity = $this->clamp((float) $post->authenticity_score);
            $relationship = $strongestRelationship > 0
                ? $this->clamp(($relationshipTotals[$post->user_id] ?? 0) / $strongestRelationship)
                : 0.0;
            $semantic = $this->clamp($semanticScores[$post->id] ?? 0.0);
            $ageHours = max(0.0, (now()->getTimestamp() - $post->created_at->getTimestamp()) / 3600);
            $timeDecay = $this->clamp(exp(-$ageHours / 72));
            $score = (0.25 * $authenticity)
                + (0.30 * $relationship)
                + (0.30 * $semantic)
                + (0.15 * $timeDecay);

            $post->setRelation('ranking_score', $score);
            $post->setRelation('ranking', [
                'score' => round($score, 4),
                'authenticity' => round($authenticity, 4),
                'relationship_depth' => round($relationship, 4),
                'semantic_similarity' => round($semantic, 4),
                'time_decay' => round($timeDecay, 4),
            ]);

            return $post;
        })->sort(function (Post $left, Post $right): int {
            $scoreComparison = $right->ranking_score <=> $left->ranking_score;

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $dateComparison = $right->created_at->getTimestamp() <=> $left->created_at->getTimestamp();

            return $dateComparison !== 0 ? $dateComparison : ($right->id <=> $left->id);
        })->values();

        $total = $ranked->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        return [
            'posts' => $ranked->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            'total' => $total,
            'last_page' => $lastPage,
            'semantic_ranking_available' => $semanticAvailable,
        ];
    }

    /**
     * @return array<int, float>
     */
    private function relationshipTotals(User $user): array
    {
        return Interaction::query()
            ->join('posts', 'posts.id', '=', 'interactions.post_id')
            ->where('interactions.user_id', $user->getKey())
            ->groupBy('posts.user_id')
            ->selectRaw(
                "posts.user_id AS author_id, SUM(CASE interactions.type WHEN 'view' THEN 1 WHEN 'reaction' THEN 3 WHEN 'reply' THEN 5 ELSE 0 END) AS weighted_total",
            )
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->author_id => (float) $row->weighted_total])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function seedDocumentIds(User $user): array
    {
        return Interaction::query()
            ->with('post:id,vector_document_id')
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->pluck('post.vector_document_id')
            ->filter(fn (mixed $id): bool => is_string($id) && preg_match('/^post-\d+$/', $id) === 1)
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{document_id: string, score: float}>  $results
     * @return array<int, float>
     */
    private function semanticScores(array $results): array
    {
        $scores = [];

        foreach ($results as $result) {
            if (preg_match('/^post-(\d+)$/', $result['document_id'], $matches) === 1) {
                $scores[(int) $matches[1]] = $this->clamp($result['score']);
            }
        }

        return $scores;
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
