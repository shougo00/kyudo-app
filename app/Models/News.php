<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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

    public function bodyHtml(): HtmlString
    {
        return new HtmlString(nl2br($this->linkifyUrls((string) $this->body), false));
    }

    private function linkifyUrls(string $text): string
    {
        $pattern = '~(?:https?://|www\.)[^\s<>"\']+~iu';
        $html = '';
        $offset = 0;

        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$matchedUrl, $position]) {
            $html .= e(substr($text, $offset, $position - $offset));

            [$url, $trailingText] = $this->splitTrailingPunctuation($matchedUrl);
            $href = preg_match('~^https?://~i', $url) ? $url : 'https://'.$url;

            $html .= '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>';
            $html .= e($trailingText);

            $offset = $position + strlen($matchedUrl);
        }

        return $html.e(substr($text, $offset));
    }

    private function splitTrailingPunctuation(string $url): array
    {
        if (preg_match('/([.,!?;:)\]\}、。！？）」』】]+)$/u', $url, $matches)) {
            return [
                preg_replace('/([.,!?;:)\]\}、。！？）」』】]+)$/u', '', $url),
                $matches[1],
            ];
        }

        return [$url, ''];
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
