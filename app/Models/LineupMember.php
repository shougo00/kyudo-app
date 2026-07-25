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
        $users = collect($users)
            ->filter(fn($user) => $user instanceof User && $user->id)
            ->unique(fn(User $user) => (int) $user->id)
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->map(fn($id) => (int) $id)->all();

        $existingUserIds = static::query()
            ->where('lineup_id', $lineup->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $now = now();
        $rows = $users
            ->reject(fn(User $user) => in_array((int) $user->id, $existingUserIds, true))
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
