<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Avatar;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;

class AvatarController extends Controller
{
    public function show() {
        $avatar = Avatar::where('user_id', Auth::id())->first();
        return view('avatar.show', compact('avatar'));
    }

    public function edit() {
        $avatar = Avatar::where('user_id', Auth::id())->first();

        $hairs = Item::where('type','hair')->get();
        $faces = Item::where('type','face')->get();
        $tops = Item::where('type','top')->get();
        $bottoms = Item::where('type','bottom')->get();
        $shoes = Item::where('type','shoes')->get();
        $accessories = Item::where('type','accessory')->get();

        return view('avatar.edit', compact('avatar','hairs','faces','tops','bottoms','shoes','accessories'));
    }

    public function update(Request $request) {
        Avatar::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'hair_id' => $request->hair_id,
                'face_id' => $request->face_id,
                'top_id' => $request->top_id,
                'bottom_id' => $request->bottom_id,
                'shoes_id' => $request->shoes_id,
                'accessory_id' => $request->accessory_id,
            ]
        );

        return redirect()->route('avatar.show');
    }
}
