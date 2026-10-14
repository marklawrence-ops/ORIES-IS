<?php

namespace App\Services;

use App\Models\Project;

/**
 * Crosschecking Module: flags proposal titles that are exact, case-insensitive, or
 * near-duplicate matches of existing records using Jaro-Winkler similarity.
 * It only flags; the verifying ORIES staff member makes the final determination.
 */
class DuplicateTitleChecker
{
    public function threshold(): float
    {
        return (float) config('ories.duplicate_threshold', 0.85);
    }

    /**
     * @return array<int, array{project_id:int, title:string, office:string, stage:string, score:float}>
     */
    public function findMatches(string $title, ?int $ignoreProjectId = null, int $limit = 5): array
    {
        $needle = self::normalize($title);
        if ($needle === '') {
            return [];
        }

        $matches = [];
        Project::query()
            ->where('verification_status', '!=', 'returned')
            ->when($ignoreProjectId, fn ($q) => $q->where('id', '!=', $ignoreProjectId))
            ->select(['id', 'title', 'office', 'stage'])
            ->orderBy('id')
            ->chunk(500, function ($projects) use ($needle, &$matches) {
                foreach ($projects as $project) {
                    $score = self::similarity($needle, self::normalize($project->title));
                    if ($score >= $this->threshold()) {
                        $matches[] = [
                            'project_id' => $project->id,
                            'title' => $project->title,
                            'office' => $project->office,
                            'stage' => $project->stage,
                            'score' => round($score, 4),
                        ];
                    }
                }
            });

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    /**
     * Lowercases, strips punctuation and the articles "a", "an", and "the", and collapses
     * whitespace so that case, punctuation, and article differences count as exact matches.
     */
    public static function normalize(string $title): string
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title);
        $title = preg_replace('/\b(a|an|the)\b/u', ' ', $title);

        return trim(preg_replace('/\s+/u', ' ', $title));
    }

    /**
     * Higher of the plain Jaro-Winkler score and the score on alphabetically sorted words,
     * so that titles with reordered phrases are still recognized as near-duplicates.
     */
    public static function similarity(string $a, string $b): float
    {
        return max(self::jaroWinkler($a, $b), self::jaroWinkler(self::sortTokens($a), self::sortTokens($b)));
    }

    public static function sortTokens(string $normalized): string
    {
        $tokens = explode(' ', $normalized);
        sort($tokens);

        return implode(' ', $tokens);
    }

    public static function jaroWinkler(string $a, string $b, float $prefixScale = 0.1): float
    {
        $jaro = self::jaro($a, $b);

        $prefix = 0;
        $max = min(4, mb_strlen($a), mb_strlen($b));
        for ($i = 0; $i < $max && mb_substr($a, $i, 1) === mb_substr($b, $i, 1); $i++) {
            $prefix++;
        }

        return $jaro + $prefix * $prefixScale * (1 - $jaro);
    }

    public static function jaro(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $s1 = mb_str_split($a);
        $s2 = mb_str_split($b);
        $len1 = count($s1);
        $len2 = count($s2);
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $window = max(0, intdiv(max($len1, $len2), 2) - 1);
        $matched1 = array_fill(0, $len1, false);
        $matched2 = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $window);
            $end = min($i + $window + 1, $len2);
            for ($j = $start; $j < $end; $j++) {
                if (! $matched2[$j] && $s1[$i] === $s2[$j]) {
                    $matched1[$i] = $matched2[$j] = true;
                    $matches++;
                    break;
                }
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (! $matched1[$i]) {
                continue;
            }
            while (! $matched2[$k]) {
                $k++;
            }
            if ($s1[$i] !== $s2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        return ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
    }
}
