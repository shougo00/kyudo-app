<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    // 一覧
    public function index()
    {
        $news = News::latest()->paginate(10);
        return view('admin.news.index', compact('news'));
    }

    // 作成フォーム
    public function create()
    {
        return view('admin.news.create');
    }

    // 保存
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        News::create([
            'title' => $request->title,
            'body' => $request->body,
            'is_published' => $request->has('is_published'),
        ]);

        return redirect()->route('news.index')->with('success','お知らせを作成しました');
    }

    // 編集フォーム
    public function edit(News $news)
    {
        return view('admin.news.edit', compact('news'));
    }

    // 更新
    public function update(Request $request, News $news)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        $news->update([
            'title' => $request->title,
            'body' => $request->body,
            'is_published' => $request->has('is_published'),
        ]);

        return redirect()->route('news.index')->with('success','お知らせを更新しました');
    }

    // 削除
    public function destroy(News $news)
    {
        $news->delete();
        return redirect()->route('news.index')->with('success','お知らせを削除しました');
    }
}