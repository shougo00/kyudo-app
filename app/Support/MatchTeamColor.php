<?php

namespace App\Support;

use App\Models\MatchTeam;
use Illuminate\Support\Collection;

class MatchTeamColor
{
    private const BASE_COLORS = [
        'male' => '#0d6efd',
        'female' => '#dc3545',
        'mixed' => '#198754',
    ];

    private const PALETTES = [
        'male' => [
            '#0d6efd',
            '#0dcaf0',
            '#20c997',
            '#ffc107',
            '#fd7e14',
            '#dc3545',
        ],
        'female' => [
            '#dc3545',
            '#e83e8c',
            '#6f42c1',
            '#0d6efd',
        ],
        'mixed' => [
            '#198754',
        ],
    ];

    public static function defaultForDivision(?string $division): string
    {
        $division = self::normalizeDivision($division);

        return self::BASE_COLORS[$division];
    }

    public static function nextAutomaticColor(int $groupId, ?string $division): string
    {
        $division = self::normalizeDivision($division);
        $activeTeams = MatchTeam::query()
            ->where('group_id', $groupId)
            ->where('division', $division)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'group_id', 'division', 'color', 'sort_order']);
        $usedColors = self::colorsByTeamId($activeTeams)->values();
        $palette = self::PALETTES[$division] ?? self::PALETTES['mixed'];

        foreach ($palette as $color) {
            if (! $usedColors->contains($color)) {
                return $color;
            }
        }

        return self::automaticColor($division, $usedColors->count());
    }

    public static function colorsByTeamId($teams): Collection
    {
        $divisionIndexes = [];

        return collect($teams)
            ->sortBy(fn($team) => sprintf(
                '%012d-%s-%012d-%012d',
                (int) ($team->group_id ?? 0),
                self::normalizeDivision($team->division ?? null),
                (int) ($team->sort_order ?? 0),
                (int) ($team->id ?? 0),
            ))
            ->mapWithKeys(function ($team) use (&$divisionIndexes) {
                $division = self::normalizeDivision($team->division ?? null);
                $key = ((int) ($team->group_id ?? 0)) . '-' . $division;
                $index = $divisionIndexes[$key] ?? 0;
                $divisionIndexes[$key] = $index + 1;

                return [
                    (int) $team->id => self::colorForStoredValue($team->color ?? null, $division, $index),
                ];
            });
    }

    public static function colorForStoredValue(?string $color, ?string $division, int $automaticIndex = 0): string
    {
        $division = self::normalizeDivision($division);

        if (self::isValidHex($color)) {
            return strtolower($color);
        }

        return self::automaticColor($division, $automaticIndex);
    }

    public static function shouldRefreshLegacyDefault(?string $color, ?string $division): bool
    {
        if (! self::isValidHex($color)) {
            return true;
        }

        return strtolower($color) === self::defaultForDivision($division);
    }

    public static function automaticColor(?string $division, int $index): string
    {
        $division = self::normalizeDivision($division);
        $palette = self::PALETTES[$division] ?? self::PALETTES['mixed'];

        return $palette[$index % count($palette)];
    }

    private static function normalizeDivision(?string $division): string
    {
        return array_key_exists((string) $division, self::BASE_COLORS)
            ? (string) $division
            : 'mixed';
    }

    private static function isValidHex(?string $color): bool
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1;
    }
}
