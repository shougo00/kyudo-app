<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Lineup;
use App\Models\LineupMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    public function handle(Request $request)
    {
        foreach ($request->input('events', []) as $event) {
            if (($event['type'] ?? null) !== 'message') continue;
            if (($event['message']['type'] ?? null) !== 'text') continue;

            $text = trim($event['message']['text']);
            $lineUserId = $event['source']['userId'] ?? null;

            if (!$lineUserId) continue;

            Log::info('LINE message', [
                'text' => $text,
                'line_user_id' => $lineUserId,
            ]);

            // 連携 123456
            if (preg_match('/^連携\s*([0-9]{6})$/u', $text, $matches)) {
                $code = $matches[1];

                $user = User::where('line_link_code', $code)->first();

                if (!$user) {
                    Log::info('LINE連携コードが見つかりません', [
                        'code' => $code,
                    ]);
                    return response('OK', 200);
                }

                $user->line_user_id = $lineUserId;
                $user->line_link_code = null;
                $user->save();
                $replyToken = $event['replyToken'] ?? null;

                if ($replyToken) {
                    Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('LINE_CHANNEL_ACCESS_TOKEN'),
                    ])->post('https://api.line.me/v2/bot/message/reply', [
                        'replyToken' => $replyToken,
                        'messages' => [
                            [
                                'type' => 'text',
                                'text' => "LINE連携が完了しました！\nこのグループで「休みます」の単語でその日の出欠が欠席として登録されます。",
                            ],
                        ],
                    ]);
                }

                Log::info('LINE連携完了', [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'line_user_id' => $lineUserId,
                ]);

                return response('OK', 200);
            }

            // 連携済みユーザー確認
            $user = User::where('line_user_id', $lineUserId)->first();

            if (!$user) {
                Log::info('LINE未連携ユーザーです');
                return response('OK', 200);
            }

            if (preg_match('/(遅刻|遅れ|おくれ|遅れる|遅れます)/u', $text)) {
            $date = $this->extractDateFromText($text);

            $group = $user->groups()->first();

            if (!$group) {
                Log::info('所属グループなし', [
                    'user_id' => $user->id,
                ]);
                return response('OK', 200);
            }

            $lineup = Lineup::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'date' => $date,
                ],
                [
                    'tate_size' => 3,
                ]
            );

            $member = LineupMember::ensureForLineupUser($lineup, $user);

            $member->update([
                'is_absent' => false,
                'is_late' => true,
            ]);

            Log::info('LINEから遅刻登録完了', [
                'user_id' => $user->id,
                'name' => $user->name,
                'group_id' => $group->id,
                'date' => $date,
            ]);

            return response('OK', 200);
        }

            if (preg_match('/(休|やす|欠席|けっせき)/u', $text)) {
                $date = $this->extractDateFromText($text);

                $group = $user->groups()->first();

                if (!$group) {
                    Log::info('所属グループなし', [
                        'user_id' => $user->id,
                    ]);
                    return response('OK', 200);
                }

                $lineup = Lineup::firstOrCreate(
                    [
                        'group_id' => $group->id,
                        'date' => $date,
                    ],
                    [
                        'tate_size' => 3,
                    ]
                );

                $member = LineupMember::ensureForLineupUser($lineup, $user);

                $member->update([
                    'is_absent' => true,
                    'is_late' => false,
                ]);

                Log::info('LINEから欠席登録完了', [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'group_id' => $group->id,
                    'date' => $date,
                ]);
            }

            if (str_contains($text, '出席') || str_contains($text, '行きます')) {
                Log::info('出席登録予定', [
                    'user_id' => $user->id,
                    'name' => $user->name,
                ]);
            }
        }

        return response('OK', 200);
    }
    private function extractDateFromText(string $text): string
    {
        $today = Carbon::today('Asia/Tokyo');

        // 今日
        if (str_contains($text, '今日') || str_contains($text, '本日')) {
            return $today->toDateString();
        }

        // 明日
        if (str_contains($text, '明日') || str_contains($text, 'あした')) {
            return $today->copy()->addDay()->toDateString();
        }

        // 明後日
        if (str_contains($text, '明後日') || str_contains($text, 'あさって')) {
            return $today->copy()->addDays(2)->toDateString();
        }

        // 2026年5月10日 / 2026/5/10 / 2026-5-10
        if (preg_match('/(\d{4})[年\/\-](\d{1,2})[月\/\-](\d{1,2})日?/u', $text, $m)) {
            return Carbon::createFromDate($m[1], $m[2], $m[3], 'Asia/Tokyo')->toDateString();
        }

        // 5月10日 / 5/10 / 5-10
        if (preg_match('/(\d{1,2})[月\/\-](\d{1,2})日?/u', $text, $m)) {
            $date = Carbon::createFromDate($today->year, $m[1], $m[2], 'Asia/Tokyo');

            // すでに過ぎている日付なら来年扱い
            if ($date->lt($today)) {
                $date->addYear();
            }

            return $date->toDateString();
        }

        // 何も日付がなければ今日
        return $today->toDateString();
    }
}
