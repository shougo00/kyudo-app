<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Avatar extends Model
{
    public const PART_LABELS = [
        'face' => '顔',
        'body' => '胴体',
        'pants' => 'ズボン',
        'shoes' => '靴',
        'item' => 'アイテム',
    ];

    protected $fillable = [
        'user_id',
        'face_id',
        'body_id',
        'pants_id',
        'shoes_id',
        'item_id',
        'hair_id',
        'top_id',
        'bottom_id',
        'accessory_id',
    ];

    public function hair() { return $this->belongsTo(Item::class,'hair_id'); }
    public function face() { return $this->belongsTo(Item::class,'face_id'); }
    public function top() { return $this->belongsTo(Item::class,'top_id'); }
    public function bottom() { return $this->belongsTo(Item::class,'bottom_id'); }
    public function shoes() { return $this->belongsTo(Item::class,'shoes_id'); }
    public function accessory() { return $this->belongsTo(Item::class,'accessory_id'); }
    public function body() { return $this->belongsTo(Item::class,'body_id'); }
    public function pants() { return $this->belongsTo(Item::class,'pants_id'); }
    public function item() { return $this->belongsTo(Item::class,'item_id'); }

    public static function partKeys(): array
    {
        return array_keys(self::PART_LABELS);
    }

    public static function partLabels(): array
    {
        return self::PART_LABELS;
    }

    public static function itemRelations(): array
    {
        return array_map(fn(string $part): string => 'avatar.' . $part, self::partKeys());
    }

    public function displayItems(): Collection
    {
        return collect(self::partKeys())
            ->mapWithKeys(fn(string $part): array => [$part => $this->{$part}])
            ->filter(fn(?Item $item): bool => $item !== null && $item->is_active)
            ->sortBy(fn(Item $item): int => $item->z_index ?? 0);
    }
}
