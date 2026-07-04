<?php

namespace App\Http\Controllers;

use App\Models\KyudoResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Record;
use Carbon\Carbon;

class KyudoResultPageController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $query = KyudoResult::query();

        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        $allResults = $query->orderBy('created_at', 'desc')->get();

       $todayResults = $allResults->filter(function ($item) {
            return $item->date === now()->format('Y-m-d');
        });

        // ===== 的中（今日） =====
        $today = now()->format('Y-m-d');

        $todayRecords = Record::with('shots')
            ->where('user_id', auth()->id())
            ->where('date', $today)
            ->get();

        $todayShots = $todayRecords->sum(fn($r) => $r->shots->whereNotNull('result')->count());
        $todayHits  = $todayRecords->sum(fn($r) => $r->shots->where('result', 'hit')->count());
        $todayHitRate = $todayShots > 0 ? round(($todayHits / $todayShots) * 100, 1) : 0;

        // ===== 表示日決定（ここ重要） =====
        $selectedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');

        $previousDate = null;

        $previousDate = null;

        if ($selectedDate === now()->format('Y-m-d')) {
            $previousResult = $allResults
                ->filter(function ($item) {
                    return $item->date < now()->format('Y-m-d')
                        && !is_null($item->right_elbow_angle)
                        && !is_null($item->right_armpit_angle)
                        && !is_null($item->left_armpit_angle)
                        && !is_null($item->kai_time);
                })
                ->sortByDesc('created_at')
                ->first();

            $previousDate = $previousResult
                ? $previousResult->created_at->format('Y-m-d')
                : null;

            $displayDate = $previousDate ?? $selectedDate;
        } else {
            $displayDate = $selectedDate;
        }

        // ===== 角度データ（表示日） =====
        // ===== 表示日決定 =====
        $selectedDate = Carbon::parse($date)->format('Y-m-d');
        $todayDate = now()->format('Y-m-d');

        $previousDate = null;

        // 上のサマリー用
        $summaryDate = $selectedDate;

        // 今日を選択している場合だけ、前回記録を探す
        if ($selectedDate === $todayDate) {
            $previousResult = $allResults
                ->filter(function ($item) use ($todayDate) {
                    return $item->date < $todayDate
                        && !is_null($item->right_elbow_angle)
                        && !is_null($item->right_armpit_angle)
                        && !is_null($item->left_armpit_angle)
                        && !is_null($item->kai_time);
                })
                ->sortByDesc('date')
                ->sortByDesc('created_at')
                ->first();

            if ($previousResult) {
                $previousDate = $previousResult->date;
                $summaryDate = $previousDate;
            }
        }

        // ===== 下の一覧：選択日の記録 =====
        $selectedDayResults = $allResults->filter(function ($item) use ($selectedDate) {
            return $item->date === $selectedDate;
        })->values();

        // ===== 上の比較カード：今日なら前回、今日以外なら選択日 =====
        $summaryResults = $allResults->filter(function ($item) use ($summaryDate) {
            return $item->date === $summaryDate;
        })->values();

        // ===== 的中（表示日） =====
        $selectedRecords = Record::with('shots')
            ->where('user_id', auth()->id())
            ->where('date', $displayDate)
            ->get();

        $selectedShots = $selectedRecords->sum(fn($r) => $r->shots->whereNotNull('result')->count());
        $selectedHits  = $selectedRecords->sum(fn($r) => $r->shots->where('result', 'hit')->count());
        $selectedHitRate = $selectedShots > 0 ? round(($selectedHits / $selectedShots) * 100, 1) : 0;

        // ===== カレンダー =====
        $month = $request->input('month', Carbon::parse($date)->format('Y-m'));
        $currentMonth = Carbon::parse($month . '-01');

        $monthStart = $currentMonth->copy()->startOfMonth()->format('Y-m-d');
        $monthEnd   = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        $calendarRecords = Record::with('shots')
            ->where('user_id', auth()->id())
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get();

        $calendar = [];

        foreach ($calendarRecords->groupBy('date') as $day => $records) {
            $shots = $records->sum(fn($r) => $r->shots->whereNotNull('result')->count());
            $hits  = $records->sum(fn($r) => $r->shots->where('result', 'hit')->count());
            $rate  = $shots > 0 ? round(($hits / $shots) * 100, 1) : 0;

            $calendar[$day] = compact('shots', 'hits', 'rate');
        }

        // ===== ポーズ記録がある日 =====
            $poseRecordDates = KyudoResult::where('user_id', auth()->id())
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('date')
                ->keys()
                ->toArray();
        return view('kyudo_results.index', [
            'date' => $date,
            'todaySummary' => $this->makeSummary($todayResults),

            'todayShots' => $todayShots,
            'todayHits' => $todayHits,
            'todayHitRate' => $todayHitRate,

            'selectedShots' => $selectedShots,
            'selectedHits' => $selectedHits,
            'selectedHitRate' => $selectedHitRate,

            'month' => $month,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'calendar' => $calendar,
            'poseRecordDates' => $poseRecordDates,

            'selectedDaySummary' => $this->makeSummary($summaryResults),
            'results' => $selectedDayResults,
            'displayDate' => $summaryDate,
            'hasPreviousRecord' => $previousDate !== null,
        ]);
    }


    private function makeSummary($items)
    {
        $count = $items->count();

        return [
            'count' => $count,
            'avg_right_elbow' => $count > 0 ? round($items->avg('right_elbow_angle'), 1) : 0,
            'avg_right_armpit' => $count > 0 ? round($items->avg('right_armpit_angle'), 1) : 0,
            'avg_left_armpit' => $count > 0 ? round($items->avg('left_armpit_angle'), 1) : 0,
            'avg_kai_time' => $count > 0 ? round($items->avg('kai_time') / 1000, 2) : 0,
        ];
    }
    public function destroy(KyudoResult $result)
    {
        if ($result->user_id !== auth()->id()) {
            abort(403);
        }

        $result->delete();

        return redirect()
            ->route('kyudo.result.list')
            ->with('success', '記録を削除しました');
    }
}