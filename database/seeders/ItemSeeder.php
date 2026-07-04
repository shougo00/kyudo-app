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

        $types = [
            'face',
            'hair',
            'top',
            'bottom',
        ];

        foreach ($types as $type) {
            $path = $basePath . '/' . $type;

            if (!File::exists($path)) continue;

            $files = File::files($path);

            foreach ($files as $file) {
                $filename = $file->getFilename();

                if (!preg_match('/\.(png|jpg|jpeg)$/i', $filename)) continue;

                Item::firstOrCreate([
                    'type' => $type,
                    'image_path' => $filename,
                ], [
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'price' => 0
                ]);
            }
        }
    }
}