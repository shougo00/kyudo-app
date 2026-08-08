<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactInquiryController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'groupName' => ['required', 'string', 'max:255'],
            'representativeName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'groupName.required' => '団体名を入力してください。',
            'representativeName.required' => '代表者名を入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式で入力してください。',
        ]);

        ContactInquiry::create([
            'group_name' => $validated['groupName'],
            'representative_name' => $validated['representativeName'],
            'email' => $validated['email'],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        $message = 'お問い合わせありがとうございます。担当者よりご連絡します。';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 201);
        }

        return back()
            ->with('contact_success', $message)
            ->withFragment('contact');
    }
}
