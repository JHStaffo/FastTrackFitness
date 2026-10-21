<?php

namespace App\Scoring;

/**
 * Every number in the program rules. Built from config('wellness.rules') or a
 * published rule version, so admins can change values without a code change.
 */
final readonly class RuleSet
{
    public const DAILY_CATEGORIES = ['meals', 'sleep', 'workout', 'steps', 'water', 'meal_plan'];

    public const TIERS = ['monthly' => 'month', 'quarterly' => 'quarter', 'annual' => 'year'];

    /** @param  array<string, mixed>  $rules */
    public function __construct(public array $rules) {}

    public static function current(): self
    {
        return new self(config('wellness.rules'));
    }

    /** @param  array<string, mixed>  $overrides */
    public function with(array $overrides): self
    {
        return new self(array_replace_recursive($this->rules, $overrides));
    }

    public function daily(string $key): int
    {
        return $this->rules['daily'][$key];
    }

    public function weeklyPointsRequired(): int
    {
        return $this->rules['weekly_reward']['points_required'];
    }

    public function weeklyAmountUsd(): int
    {
        return $this->rules['weekly_reward']['amount_usd'];
    }

    /** @return array{points_per_event: int, max_points_per_period: int, limit_period: string, requires_document: bool} */
    public function other(string $category): array
    {
        return $this->rules['other'][$category];
    }

    public function contestOtherCap(string $periodKind): ?int
    {
        return $this->rules['contest_other_caps'][$periodKind] ?? null;
    }

    public function stacking(): string
    {
        return $this->rules['stacking'];
    }
}
