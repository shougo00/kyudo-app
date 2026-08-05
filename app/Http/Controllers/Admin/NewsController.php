<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeNewsAdmin($request);

        $news = News::withCount('recipients')->latest()->paginate(10);

        return view('admin.news.index', compact('news'));
    }

    public function create(Request $request): View
    {
        $this->authorizeNewsAdmin($request);

        return view('admin.news.create', [
            'users' => $this->recipientUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeNewsAdmin($request);

        $validated = $this->validateNews($request);

        $news = News::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'image_path' => $this->storeImage($request),
            'is_published' => $request->has('is_published'),
        ]);

        $this->syncRecipients($news, $validated['recipient_ids']);

        return redirect()->route('admin.news.index')->with('success', 'お知らせを作成しました');
    }

    public function edit(Request $request, News $news): View
    {
        $this->authorizeNewsAdmin($request);

        $news->load('recipients');

        return view('admin.news.edit', [
            'news' => $news,
            'users' => $this->recipientUsers(),
            'selectedUserIds' => $news->recipients->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, News $news): RedirectResponse
    {
        $this->authorizeNewsAdmin($request);

        $validated = $this->validateNews($request);

        $imagePath = $news->image_path;

        if ($request->boolean('remove_image') && $imagePath) {
            $this->deleteImage($imagePath);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($imagePath);
            $imagePath = $this->storeImage($request);
        }

        $news->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'image_path' => $imagePath,
            'is_published' => $request->has('is_published'),
        ]);

        $this->syncRecipients($news, $validated['recipient_ids']);

        return redirect()->route('admin.news.index')->with('success', 'お知らせを更新しました');
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        $this->authorizeNewsAdmin($request);

        $this->deleteImage($news->image_path);
        $news->delete();

        return redirect()->route('admin.news.index')->with('success', 'お知らせを削除しました');
    }

    private function authorizeNewsAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || $user->username !== 'KANRI') {
            abort(403, 'システム管理者だけがお知らせを管理できます');
        }
    }

    private function recipientUsers(): Collection
    {
        return User::query()
            ->where('username', '!=', 'KANRI')
            ->orderBy('name')
            ->get();
    }

    private function validateNews(Request $request): array
    {
        $allowedRecipientIds = $this->recipientUsers()
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->all();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', Rule::in($allowedRecipientIds)],
        ], [
            'recipient_ids.required' => '配信先ユーザーを選択してください。',
            'recipient_ids.min' => '配信先ユーザーを1人以上選択してください。',
            'recipient_ids.*.in' => '選択できないユーザーが含まれています。',
            'image.image' => '画像ファイルを選択してください。',
            'image.mimes' => '画像は jpg, jpeg, png, gif, webp のいずれかを選択してください。',
            'image.max' => '画像は5MB以内にしてください。',
        ]);
    }

    private function syncRecipients(News $news, array $recipientIds): void
    {
        $currentReadTimes = $news->recipients()
            ->get()
            ->mapWithKeys(fn(User $user) => [$user->id => $user->pivot->read_at])
            ->all();

        $syncData = collect($recipientIds)
            ->mapWithKeys(fn($userId) => [
                (int) $userId => ['read_at' => $currentReadTimes[(int) $userId] ?? null],
            ])
            ->all();

        $news->recipients()->sync($syncData);
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        $directory = public_path('news_images');
        File::ensureDirectoryExists($directory);

        $file = $request->file('image');
        $filename = Str::uuid().'.'.$file->extension();
        $file->move($directory, $filename);

        return 'news_images/'.$filename;
    }

    private function deleteImage(?string $path): void
    {
        if (!$path || !str_starts_with($path, 'news_images/')) {
            return;
        }

        File::delete(public_path($path));
    }
}
