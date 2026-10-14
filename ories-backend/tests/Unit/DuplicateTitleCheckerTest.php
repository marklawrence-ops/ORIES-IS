<?php

namespace Tests\Unit;

use App\Services\DuplicateTitleChecker;
use PHPUnit\Framework\TestCase;

class DuplicateTitleCheckerTest extends TestCase
{
    public function test_jaro_winkler_matches_reference_values(): void
    {
        // Reference pairs from Winkler (1990)
        $this->assertEqualsWithDelta(0.9611, DuplicateTitleChecker::jaroWinkler('martha', 'marhta'), 0.0001);
        $this->assertEqualsWithDelta(0.8400, DuplicateTitleChecker::jaroWinkler('dwayne', 'duane'), 0.0001);
        $this->assertEqualsWithDelta(0.8133, DuplicateTitleChecker::jaroWinkler('dixon', 'dicksonx'), 0.0001);
        $this->assertSame(1.0, DuplicateTitleChecker::jaroWinkler('same', 'same'));
        $this->assertSame(0.0, DuplicateTitleChecker::jaroWinkler('abc', ''));
    }

    public function test_case_and_punctuation_differences_are_exact_matches(): void
    {
        $a = DuplicateTitleChecker::normalize('Flood Early-Warning App for Barangays.');
        $b = DuplicateTitleChecker::normalize('FLOOD EARLY WARNING APP FOR BARANGAYS');

        $this->assertSame(1.0, DuplicateTitleChecker::similarity($a, $b));
    }

    public function test_reordered_phrases_are_detected_as_near_duplicates(): void
    {
        $a = DuplicateTitleChecker::normalize('Machine Learning Model for Predicting Student Attrition in State Universities');
        $b = DuplicateTitleChecker::normalize('Predictive Model for Student Attrition in State Universities Using Machine Learning');

        $this->assertGreaterThanOrEqual(0.85, DuplicateTitleChecker::similarity($a, $b));
    }

    public function test_unrelated_titles_score_below_threshold(): void
    {
        $a = DuplicateTitleChecker::normalize('Assessment of Microplastic Contamination in Cancabato Bay');
        $b = DuplicateTitleChecker::normalize('Financial Literacy Program for Market Vendors in Tacloban City');

        $this->assertLessThan(0.85, DuplicateTitleChecker::similarity($a, $b));
    }
}
