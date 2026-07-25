<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineupMember extends Model
{
    protected $fillable = [
        'lineup_id',
        'user_id',
        'position',
        'is_absent',
        'is_late',
    ];

    public static function ensureForLineupUser(Lineup $lineup, User $user): self
    {
        static::ensureForLineupUsers($lineup, [$user]);

        return static::query()
            ->where('lineup_id', $lineup->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public static function ensureForLineupUsers(Lineup $lineup, iterable $users): void
    {
        $now = now();
        $rows = collect($users)
            ->filter(fn($user) => $user instanceof User && $user->id)
            ->unique(fn(User $user) => (int) $user->id)
            ->map(fn(User $user) => [
                'lineup_id' => $lineup->id,
                'user_id' => $user->id,
                'position' => null,
                'is_absent' => $user->isDefaultAbsentForDate($lineup->date),
                'is_late' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        static::query()->insertOrIgnore($rows);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lineup()
    {
        return $this->belongsTo(Lineup::class);
    }
}
