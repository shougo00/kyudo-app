<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KyudoResult;

class KyudoResultController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'kai_time' => 'required|integer',
            'right_elbow_angle' => 'nullable|numeric',
            'right_armpit_angle' => 'nullable|numeric',
            'left_armpit_angle' => 'nullable|numeric',
        ]);

        KyudoResult::create([
            'user_id' => auth()->id(),
            'date' => now()->format('Y-m-d'),
            'kai_time' => $request->kai_time,
            'right_elbow_angle' => $request->right_elbow_angle,
            'right_armpit_angle' => $request->right_armpit_angle,
            'left_armpit_angle' => $request->left_armpit_angle,
        ]);

        return response()->json([
            'message' => '保存しました'
        ]);
    }
}