<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_LABELS = [
        'face' => '顔',
        'body' => '胴体',
        'pants' => 'ズボン',
        'shoes' => '靴',
        'item' => 'アイテム',
    ];

    private const LEGACY_TYPE_MAP = [
        'hair' => 'item',
        'top' => 'body',
        'bottom' => 'pants',
        'accessory' => 'item',
    ];

    private const DEFAULT_LAYOUTS = [
        'face' => ['position_x' => 75, 'position_y' => 42, 'display_width' => 150, 'display_height' => 150, 'z_index' => 40],
        'body' => ['position_x' => 25, 'position_y' => 145, 'display_width' => 250, 'display_height' => 220, 'z_index' => 30],
        'pants' => ['position_x' => 35, 'position_y' => 275, 'display_width' => 230, 'display_height' => 135, 'z_index' => 20],
        'shoes' => ['position_x' => 70, 'position_y' => 400, 'display_width' => 160, 'display_height' => 40, 'z_index' => 10],
        'item' => ['position_x' => 45, 'position_y' => 0, 'display_width' => 210, 'display_height' => 130, 'z_index' => 50],
    ];

    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'position_x')) {
                $table->integer('position_x')->default(0)->after('image_path');
            }

            if (!Schema::hasColumn('items', 'position_y')) {
                $table->integer('position_y')->default(0)->after('position_x');
            }

            if (!Schema::hasColumn('items', 'display_width')) {
                $table->integer('display_width')->default(300)->after('position_y');
            }

            if (!Schema::hasColumn('items', 'display_height')) {
                $table->integer('display_height')->default(300)->after('display_width');
            }

            if (!Schema::hasColumn('items', 'z_index')) {
                $table->integer('z_index')->default(10)->after('display_height');
            }

            if (!Schema::hasColumn('items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('z_index');
            }
        });

        $this->normalizeExistingItems();

        Schema::table('avatars', function (Blueprint $table) {
            if (!Schema::hasColumn('avatars', 'body_id')) {
                $table->unsignedBigInteger('body_id')->nullable()->after('face_id');
            }

            if (!Schema::hasColumn('avatars', 'pants_id')) {
                $table->unsignedBigInteger('pants_id')->nullable()->after('body_id');
            }

            if (!Schema::hasColumn('avatars', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->after('shoes_id');
            }
        });

        DB::table('avatars')->orderBy('id')->get()->each(function ($avatar): void {
            DB::table('avatars')
                ->where('id', $avatar->id)
                ->update([
                    'body_id' => $avatar->body_id ?? $avatar->top_id ?? null,
                    'pants_id' => $avatar->pants_id ?? $avatar->bottom_id ?? null,
                    'item_id' => $avatar->item_id ?? $avatar->accessory_id ?? $avatar->hair_id ?? null,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('avatars', function (Blueprint $table) {
            foreach (['body_id', 'pants_id', 'item_id'] as $column) {
                if (Schema::hasColumn('avatars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $this->restoreLegacyItems();

        Schema::table('items', function (Blueprint $table) {
            foreach (['position_x', 'position_y', 'display_width', 'display_height', 'z_index', 'is_active'] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function normalizeExistingItems(): void
    {
        DB::table('items')->orderBy('id')->get()->each(function ($item): void {
            $oldType = (string) $item->type;
            $newType = self::LEGACY_TYPE_MAP[$oldType] ?? $oldType;

            if (!array_key_exists($newType, self::TYPE_LABELS)) {
                return;
            }

            $path = trim((string) $item->image_path, '/');
            if ($path !== '' && !str_contains($path, '/')) {
                $path = $oldType . '/' . $path;
            }

            DB::table('items')
                ->where('id', $item->id)
                ->update(array_merge(
                    [
                        'type' => $newType,
                        'image_path' => $path,
                    ],
                    self::DEFAULT_LAYOUTS[$newType],
                    ['is_active' => true]
                ));
        });
    }

    private function restoreLegacyItems(): void
    {
        DB::table('items')->orderBy('id')->get()->each(function ($item): void {
            $path = trim((string) $item->image_path, '/');
            $parts = explode('/', $path, 2);

            if (count($parts) !== 2) {
                return;
            }

            [$folder, $filename] = $parts;
            $legacyType = match ($folder) {
                'top' => 'top',
                'bottom' => 'bottom',
                'hair' => 'hair',
                'accessory' => 'accessory',
                default => $folder,
            };

            DB::table('items')
                ->where('id', $item->id)
                ->update([
                    'type' => $legacyType,
                    'image_path' => $filename,
                ]);
        });
    }
};
