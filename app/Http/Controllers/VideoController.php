<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function upload(Request $request)
    {
        $file = $request->file('video');

        $path = $file->store('videos', 'public');

        $video = Video::create([
            'path' => $path
        ]);

        return response()->json([
            'url' => asset('storage/' . $path)
        ]);
    }

    public function list()
    {
        $videos = Video::latest()->get();

        return response()->json($videos->map(function ($v) {
            return [
                'url' => asset('storage/' . $v->path)
            ];
        }));
    }
}
