<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Avatar;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AvatarController extends Controller
{
    public function show() {
        $avatar = Avatar::with(Avatar::partKeys())
            ->where('user_id', Auth::id())
            ->first();

        return view('avatar.show', compact('avatar'));
    }

    public function edit() {
        $avatar = Avatar::with(Avatar::partKeys())
            ->where('user_id', Auth::id())
            ->first();

        $itemsByType = Item::query()
            ->whereIn('type', Item::avatarTypes())
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('z_index')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return view('avatar.edit', [
            'avatar' => $avatar,
            'parts' => Avatar::partLabels(),
            'itemsByType' => $itemsByType,
        ]);
    }

    public function update(Request $request) {
        $rules = [];

        foreach (Avatar::partKeys() as $part) {
            $rules[$part . '_id'] = [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($query) => $query
                    ->where('type', $part)
                    ->where('is_active', true)),
            ];
        }

        $validated = $request->validate($rules);

        Avatar::updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        return redirect()->route('avatar.show');
    }
}
