<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public const CANVAS_WIDTH = 300;
    public const CANVAS_HEIGHT = 450;

    public const TYPE_LABELS = [
        'face' => '顔',
        'body' => '胴体',
        'pants' => 'ズボン',
        'shoes' => '靴',
        'item' => 'アイテム',
    ];

    public const LEGACY_TYPE_MAP = [
        'hair' => 'item',
        'top' => 'body',
        'bottom' => 'pants',
        'accessory' => 'item',
    ];

    public const DEFAULT_LAYOUTS = [
        'face' => [
            'position_x' => 75,
            'position_y' => 42,
            'display_width' => 150,
            'display_height' => 150,
            'z_index' => 40,
        ],
        'body' => [
            'position_x' => 25,
            'position_y' => 145,
            'display_width' => 250,
            'display_height' => 220,
            'z_index' => 30,
        ],
        'pants' => [
            'position_x' => 35,
            'position_y' => 275,
            'display_width' => 230,
            'display_height' => 135,
            'z_index' => 20,
        ],
        'shoes' => [
            'position_x' => 70,
            'position_y' => 400,
            'display_width' => 160,
            'display_height' => 40,
            'z_index' => 10,
        ],
        'item' => [
            'position_x' => 45,
            'position_y' => 0,
            'display_width' => 210,
            'display_height' => 130,
            'z_index' => 50,
        ],
    ];

    protected $fillable = [
        'name',
        'type',
        'price',
        'image_path',
        'layer_order',
        'position_x',
        'position_y',
        'display_width',
        'display_height',
        'z_index',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'display_width' => 'integer',
        'display_height' => 'integer',
        'z_index' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function avatarTypes(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    public static function labelForType(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    public static function normalizeType(string $type): string
    {
        return self::LEGACY_TYPE_MAP[$type] ?? $type;
    }

    public static function defaultLayoutFor(string $type): array
    {
        return self::DEFAULT_LAYOUTS[self::normalizeType($type)] ?? self::DEFAULT_LAYOUTS['item'];
    }

    public function assetPath(): string
    {
        $path = trim((string) $this->image_path, '/');

        if ($path === '') {
            return 'avatars/default.png';
        }

        if (str_contains($path, '/')) {
            return 'avatars/' . $path;
        }

        return 'avatars/' . $this->type . '/' . $path;
    }
}
