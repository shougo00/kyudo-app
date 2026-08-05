<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $news = $request->user()
            ->receivedNews()
            ->published()
            ->orderByDesc('news.created_at')
            ->paginate(10);

        return view('news.index', [
            'news' => $news,
            'unreadCount' => $request->user()->unreadNewsCount(),
        ]);
    }

    public function show(Request $request, News $news): View
    {
        if (!$news->is_published) {
            abort(404);
        }

        $recipient = $news->recipients()
            ->where('users.id', $request->user()->id)
            ->first();

        if (!$recipient) {
            abort(404);
        }

        if ($recipient->pivot->read_at === null) {
            $news->recipients()->updateExistingPivot($request->user()->id, [
                'read_at' => now(),
            ]);
        }

        return view('news.show', compact('news'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        DB::table('news_user')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereIn('news_id', News::published()->select('id'))
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'お知らせをすべて既読にしました。');
    }
}
