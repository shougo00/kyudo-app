<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class News extends Model
{
    protected $fillable = [
        'title',
        'body',
        'image_path',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function recipients()
    {
        return $this->belongsToMany(User::class, 'news_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function imageUrl(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'storage/')) {
            return asset($this->image_path);
        }

        if (str_starts_with($this->image_path, 'news_images/')) {
            if (File::exists(public_path($this->image_path))) {
                return asset($this->image_path);
            }

            return Storage::disk('public')->url($this->image_path);
        }

        return asset($this->image_path);
    }
}
