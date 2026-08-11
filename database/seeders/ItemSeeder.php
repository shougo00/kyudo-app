<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use Illuminate\Support\Facades\File;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = public_path('avatars');

        $directories = [
            'face' => 'face',
            'top' => 'body',
            'bottom' => 'pants',
            'shoes' => 'shoes',
            'accessory' => 'item',
            'hair' => 'item',
            'bow' => 'item',
        ];

        foreach ($directories as $directory => $type) {
            $path = $basePath . '/' . $directory;

            if (!File::exists($path)) continue;

            $files = File::files($path);

            foreach ($files as $file) {
                $filename = $file->getFilename();

                if (!preg_match('/\.(png|jpg|jpeg|webp|gif|svg)$/i', $filename)) continue;

                $imagePath = $directory . '/' . $filename;
                $layout = Item::defaultLayoutFor($type);
                $isActive = !($directory === 'top' && !preg_match('/\.svg$/i', $filename));

                Item::firstOrCreate([
                    'type' => $type,
                    'image_path' => $imagePath,
                ], [
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'price' => 0,
                    'position_x' => $layout['position_x'],
                    'position_y' => $layout['position_y'],
                    'display_width' => $layout['display_width'],
                    'display_height' => $layout['display_height'],
                    'z_index' => $layout['z_index'],
                    'is_active' => $isActive,
                ]);
            }
        }
    }
}
