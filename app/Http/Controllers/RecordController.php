<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Group;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;
use Carbon\Carbon;

class RecordController extends Controller
{
    // 一覧表示
    

    public function index(Request $request)
    {
        // リクエストから日付と練習タイプを取得、なければデフォルトを設定
        $date = $request->date ?? date('Y-m-d');
        $type = $request->type ?? 'official'; // デフォルトは正規練
        if (!in_array($type, ['official', 'self'], true)) {
            $type = 'official';
        }

        // 該当ユーザーのレコードを取得（shotsリレーションも読み込む）
        $records = Record::with('shots')
            ->where('user_id', auth()->id())
            ->where('date', $date)
            ->where('practice_type', $type)
            ->orderBy('tate_no')
            ->get();

        // 前日・翌日の日付を計算
        $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
        $nextDate = Carbon::parse($date)->addDay()->format('Y-m-d');
        $isInGroup = auth()->user()?->groups()->exists() ?? false;
        $canManageTates = $type === 'self' || !$isInGroup;

        // Ajaxリクエストの場合は部分HTMLだけ返す
        if ($request->ajax()) {
            return view('partials.records', compact('records', 'type', 'date', 'canManageTates'))->render();
        }
       $totalShots = $records->sum(function($record) {
            return $record->shots->whereNotNull('result')->count();
        });

        $totalHits = $records->sum(function($record) {
            return $record->shots->where('result', 'hit')->count();
        });

        $hitRate = $totalShots > 0 ? round(($totalHits / $totalShots) * 100, 3) : 0;
        $numericScoreOptions = collect(auth()->user()?->groups()->first()?->numeric_score_options ?? [])
            ->filter(fn ($option) => isset($option['value'], $option['color']))
            ->map(fn ($option) => [
                'value' => (int) $option['value'],
                'color' => $option['color'],
            ])
            ->values();

        // 通常リクエストはフルビューを返す
        return view('home', compact(
            'records',
            'date',
            'type',
            'prevDate',
            'nextDate',
            'totalShots',
            'totalHits',
            'hitRate',
            'numericScoreOptions',
            'canManageTates'
        ));
    }
    // 立追加
    public function store(Request $request)
    {
        $date = $request->date;
        $practiceType = $request->practice_type; // 正規練 / 自主練
        if (!in_array($practiceType, ['official', 'self'], true)) {
            $practiceType = 'official';
        }

        // その日のその練習タイプの最大立番号を取得
        $maxTate = Record::where('user_id', auth()->id())
            ->where('date', $date)
            ->where('practice_type', $practiceType) // ←ここ重要
            ->max('tate_no');

        $newTate = $maxTate ? $maxTate + 1 : 1; // 練習タイプごとに1立目から

        $record = Record::create([
            'user_id' => auth()->id(),
            'date' => $date,
            'tate_no' => $newTate,
            'practice_type' => $practiceType
        ]);

        // 4射作成
        for ($i = 1; $i <= 4; $i++) {
            Shot::create([
                'record_id' => $record->id,
                'shot_no' => $i,
                'result' => null
            ]);
        }

        return redirect()
            ->route('home', [
                'date' => $date,
                'type' => $practiceType,
            ])
            ->withFragment("record-{$record->id}");
    }
    public function updateShot(Request $request, $id)
    {
        $shot = Shot::findOrFail($id);

        $shot->result = $request->result;
        if ($request->has('numeric_score')) {
            $shot->numeric_score = $request->numeric_score;
        } else {
            $shot->numeric_score = null;
        }
        $shot->save();

        return response()->json(['success' => true]);
    }
    // 立削除
    public function destroy($id)
    {
        $record = Record::where('user_id', auth()->id())->findOrFail($id);

        $date = $record->date;
        $practiceType = $record->practice_type;

        // 射を削除
        $record->shots()->delete();

        // 立を削除
        $record->delete();

        // 残った立番号を詰める
        $remainingRecords = Record::where('user_id', auth()->id())
            ->where('date', $date)
            ->where('practice_type', $practiceType)
            ->orderBy('tate_no')
            ->get();

        $tateNo = 1;
        foreach($remainingRecords as $r) {
            $r->tate_no = $tateNo++;
            $r->save();
        }

        return response()->json(['success' => true]);
    }
    public function dashboard(Request $request)
    {
        $viewer = auth()->user();
        $targetUser = $viewer;
        $targetGroup = null;

        if ($request->filled('user_id')) {
            $targetGroup = Group::findOrFail($request->integer('group_id'));

            if ($viewer?->username !== 'KANRI' && !$targetGroup->users()->where('users.id', $viewer->id)->exists()) {
                abort(403);
            }

            $targetUser = User::where('is_admin', false)->findOrFail($request->integer('user_id'));

            if (!$targetGroup->users()->where('users.id', $targetUser->id)->exists()) {
                abort(404);
            }
        }

        $userId = $targetUser->id;
        $isViewingOwnHistory = (int) $userId === (int) $viewer->id;

        $type = $request->type ?? 'all';
        if ($type === 'match') {
            $type = 'official';
        }

        // 月
        $month = $request->month ?? now()->format('Y-m');
        $current = \Carbon\Carbon::parse($month . '-01');

        $start = $current->copy()->startOfMonth()->format('Y-m-d');
        $end   = $current->copy()->endOfMonth()->format('Y-m-d');

        // typeをURLに乗せて保持
        $prevMonth = $current->copy()->subMonth()->format('Y-m');
        $nextMonth = $current->copy()->addMonth()->format('Y-m');

        // データ取得（今月）
        $records = Record::with('shots')
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->whereIn('practice_type', ['official', 'self'])
            ->get();

        // 共通計算
        $calc = function($records) {
            $shots = $records->sum(fn($r) => $r->shots->whereNotNull('result')->count());
            $hits  = $records->sum(fn($r) => $r->shots->where('result','hit')->count());
            $rate  = $shots > 0 ? round(($hits/$shots)*100,1) : 0;
            return compact('shots','hits','rate');
        };

        // ===== 今日 =====
        $today = now()->format('Y-m-d');
        $todayRecords = Record::with('shots')
            ->where('user_id', $userId)
            ->where('date', $today)
            ->whereIn('practice_type', ['official', 'self'])
            ->get();

        $todayOfficial = $calc($todayRecords->where('practice_type','official'));
        $todaySelf     = $calc($todayRecords->where('practice_type','self'));
        $todayAll      = $calc($todayRecords);

        // ===== 月間 =====
        $monthOfficial = $calc($records->where('practice_type','official'));
        $monthSelf     = $calc($records->where('practice_type','self'));
        $monthAll      = $calc($records);

        // ===== 年間 =====
        $year = $current->format('Y');
        $yearStart = $year . '-01-01';
        $yearEnd   = $year . '-12-31';

        $yearRecords = Record::with('shots')
            ->where('user_id', $userId)
            ->whereBetween('date', [$yearStart, $yearEnd])
            ->whereIn('practice_type', ['official', 'self'])
            ->get();

        $yearOfficial = $calc($yearRecords->where('practice_type','official'));
        $yearSelf     = $calc($yearRecords->where('practice_type','self'));
        $yearAll      = $calc($yearRecords);

        // ===== カレンダー =====
        $calendar = [];
        foreach ($records->groupBy('date') as $date => $dayRecords) {
            $calendar[$date] = [
                'official' => $calc($dayRecords->where('practice_type','official')),
                'self'     => $calc($dayRecords->where('practice_type','self')),
                'all'      => $calc($dayRecords),
            ];
        }

        return view('dashboard', compact(
            'calendar',
            'month',
            'prevMonth',
            'nextMonth',
            'type',
            'targetUser',
            'targetGroup',
            'isViewingOwnHistory',
            'todayOfficial','todaySelf','todayAll',
            'monthOfficial','monthSelf','monthAll',
            'yearOfficial','yearSelf','yearAll'
        ));
    }
}
