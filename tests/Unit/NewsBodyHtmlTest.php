<?php

use App\Models\News;

test('news body html linkifies urls and keeps body text escaped', function () {
    $news = new News([
        'body' => "詳細は https://example.com/path?a=1&b=2。 または www.example.jp を見てください。\n<script>alert(1)</script>",
    ]);

    $html = (string) $news->bodyHtml();

    expect($html)
        ->toContain('<a href="https://example.com/path?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">https://example.com/path?a=1&amp;b=2</a>。')
        ->toContain('<a href="https://www.example.jp" target="_blank" rel="noopener noreferrer">www.example.jp</a>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>');
});
