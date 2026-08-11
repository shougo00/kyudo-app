<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AvatarItemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $selectedType = (string) $request->query('type', 'all');
        if ($selectedType !== 'all' && !in_array($selectedType, Item::avatarTypes(), true)) {
            $selectedType = 'all';
        }

        $items = Item::query()
            ->whereIn('type', Item::avatarTypes())
            ->whereNotIn('image_path', ['body/body1.svg', 'body/body2.svg'])
            ->when($selectedType !== 'all', fn($query) => $query->where('type', $selectedType))
            ->orderByRaw("case type when 'face' then 1 when 'body' then 2 when 'pants' then 3 when 'shoes' then 4 when 'item' then 5 else 6 end")
            ->orderBy('z_index')
            ->orderBy('name')
            ->paginate(16)
            ->withQueryString();

        return view('admin.avatar_items.index', [
            'items' => $items,
            'typeLabels' => Item::TYPE_LABELS,
            'selectedType' => $selectedType,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in(Item::avatarTypes())],
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif,svg', 'max:4096'],
        ], [
            'image.mimes' => '画像は png / jpg / jpeg / webp / gif / svg 形式でアップロードしてください。',
            'image.max' => '画像は4MB以内にしてください。',
        ]);

        $file = $validated['image'];
        $type = $validated['type'];
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = now()->format('YmdHis') . '_' . Str::random(10) . '.' . $extension;
        $directory = public_path('avatars/' . $type);

        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        Item::create(array_merge(
            [
                'type' => $type,
                'name' => $validated['name'],
                'price' => 0,
                'image_path' => $type . '/' . $filename,
                'is_active' => true,
            ],
            Item::defaultLayoutFor($type)
        ));

        return back()->with('success', 'アバター素材を追加しました。');
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in(Item::avatarTypes())],
            'name' => ['required', 'string', 'max:80'],
            'position_x' => ['required', 'integer', 'min:-300', 'max:600'],
            'position_y' => ['required', 'integer', 'min:-300', 'max:900'],
            'display_width' => ['required', 'integer', 'min:1', 'max:900'],
            'display_height' => ['required', 'integer', 'min:1', 'max:900'],
            'z_index' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $item->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));

        return back()->with('success', "{$item->name} を更新しました。");
    }

    public function export(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $items = Item::query()
            ->whereIn('type', Item::avatarTypes())
            ->whereNotIn('image_path', ['body/body1.svg', 'body/body2.svg'])
            ->orderByRaw("case type when 'face' then 1 when 'body' then 2 when 'pants' then 3 when 'shoes' then 4 when 'item' then 5 else 6 end")
            ->orderBy('z_index')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return view('admin.avatar_items.export', [
            'itemsByType' => $items,
            'typeLabels' => Item::TYPE_LABELS,
        ]);
    }

    private function authorizeSystemAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || $user->username !== 'KANRI') {
            abort(403, 'システム管理者だけがアクセスできます');
        }
    }
}
