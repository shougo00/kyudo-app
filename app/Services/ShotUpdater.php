<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Lineup;
use App\Models\MatchTeam;
use App\Models\Record;
use App\Models\Shot;
use App\Models\ShotChangeLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShotUpdater
{
    public function groupIdFor(Record $record): ?int
    {
        $groupId = MatchTeam::withTrashed()
            ->whereHas('members', fn ($query) => $query->where('official_record_id', $record->id))
            ->value('group_id');

        $groupId ??= Lineup::where('date', $record->date)
            ->whereHas('members', fn ($query) => $query->where('user_id', $record->user_id))
            ->value('group_id');

        if (! $groupId && $record->practice_type === 'match') {
            $groupId = MatchTeam::withTrashed()
                ->where('id', $record->match_team_id)
                ->orWhere(function ($query) use ($record) {
                    $query->where('date', $record->date)
                        ->whereHas('members', function ($query) use ($record) {
                            $query->where('user_id', $record->user_id)
                                ->where('tate_no', $record->tate_no);
                        });
                })
                ->value('group_id');
        }

        return $groupId ? (int) $groupId : null;
    }

    public function update(Shot $shot, array $values, User $actor, string $source, ?int $groupId = null): void
    {
        DB::transaction(function () use ($shot, $values, $actor, $source, $groupId) {
            // Read the previous value under a lock, rather than using a stale request's model.
            $current = Shot::whereKey($shot->id)->lockForUpdate()->firstOrFail();
            $before = $this->value($current);
            $current->fill([
                'result' => $values['result'] ?? null,
                'numeric_score' => $values['numeric_score'] ?? null,
            ]);
            $after = $this->value($current);
            $current->save();

            $record = $current->record;
            if ($record->practice_type !== 'official' || $before === $after) {
                return;
            }

            $groupId ??= $this->groupIdFor($record);
            $group = $groupId ? Group::find($groupId) : null;
            $subject = $record->user;

            // A failed history insert rolls back the shot update as well.
            ShotChangeLog::create([
                'group_id' => $groupId,
                'group_name' => $group?->name,
                'record_id' => $record->id,
                'shot_id' => $current->id,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'actor_username' => $actor->username,
                'subject_user_id' => $subject->id,
                'subject_name' => $subject->name,
                'subject_username' => $subject->username,
                'practice_date' => $record->date,
                'official_sheet_no' => $record->official_sheet_no ?? 1,
                'tate_no' => $record->tate_no,
                'shot_no' => $current->shot_no,
                'old_result' => $before['result'],
                'new_result' => $after['result'],
                'old_numeric_score' => $before['numeric_score'],
                'new_numeric_score' => $after['numeric_score'],
                'source' => $source,
                'created_at' => now(),
            ]);
        }, 3);
    }

    private function value(Shot $shot): array
    {
        return [
            'result' => $shot->result ?: null,
            'numeric_score' => $shot->numeric_score === null ? null : (int) $shot->numeric_score,
        ];
    }
}
