<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ShotChangeLog extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    public static function retentionCutoff(): Carbon
    {
        return now()->subMonthNoOverflow();
    }

    public static function deleteExpired(CarbonInterface $requestedCutoff): int
    {
        // A forged cutoff must never delete the most recent month of history.
        $cutoff = $requestedCutoff->min(static::retentionCutoff())->format('Y-m-d H:i:s.u');
        $total = 0;

        do {
            $deleted = static::where('created_at', '<', $cutoff)->limit(1000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'old_numeric_score' => 'integer',
            'new_numeric_score' => 'integer',
        ];
    }

    protected function practiceDate(): Attribute
    {
        // The operation timestamp has microseconds; the practice date stays date-only.
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value),
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function oldValueLabel(): string
    {
        return $this->valueLabel($this->old_result, $this->old_numeric_score);
    }

    public function newValueLabel(): string
    {
        return $this->valueLabel($this->new_result, $this->new_numeric_score);
    }

    public function operationLabel(): string
    {
        if ($this->new_result === null && $this->new_numeric_score === null) {
            return '取消';
        }

        return $this->old_result === null && $this->old_numeric_score === null ? '入力' : '変更';
    }

    private function valueLabel(?string $result, ?int $score): string
    {
        $parts = [];
        if ($result !== null) {
            $parts[] = match ($result) {
                'hit' => '○',
                'miss' => '×',
                default => $result,
            };
        }
        if ($score !== null) {
            $parts[] = $score.'点';
        }

        return $parts ? implode(' / ', $parts) : '未入力';
    }
}
