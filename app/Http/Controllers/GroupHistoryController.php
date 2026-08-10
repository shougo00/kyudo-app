<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Lineup;
use App\Models\Record;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupHistoryController extends Controller
{
    public function index(Request $request, Group $group)
    {
        if (auth()->user()?->username !== 'KANRI' && !$group->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $view = in_array($request->input('view', 'ranking'), ['ranking', 'monthly'], true)
            ? $request->input('view', 'ranking')
            : 'ranking';

        $availableScoreTypes = [
            'official' => '正規練',
            'self' => '自主練',
        ];

        $scoreTypes = $request->input('score_types');
        if ($scoreTypes === null && $request->filled('score_type')) {
            $scoreTypes = $request->input('score_type') === 'all'
                ? ['all']
                : [$request->input('score_type')];
        }

        $allowedScoreTypes = array_merge(['all'], array_keys($availableScoreTypes));
        $scoreTypes = collect((array) ($scoreTypes ?? ['all']))
            ->filter(fn($type) => in_array($type, $allowedScoreTypes, true))
            ->values()
            ->all();

        if (empty($scoreTypes) || in_array('all', $scoreTypes, true)) {
            $scoreTypes = ['all'];
        }
        $period = in_array($request->input('period', 'today'), ['today', 'week', 'month', 'year', 'date', 'custom'], true)
            ? $request->input('period', 'today')
            : 'today';
        $requestedLimit = (string) $request->input('limit', 'all');
        $limit = in_array($requestedLimit, ['5', '10', '20', 'all'], true)
            ? $requestedLimit
            : 'all';
        [$rangeStart, $rangeEnd, $startDate, $endDate] = $this->rankingDateRange($request, $period);
        $rankingCalendarMonth = $this->validMonthOr(
            (string) $request->input('calendar_month', ''),
            Carbon::parse($startDate)->format('Y-m')
        );
        $rankingCalendarCurrentMonth = Carbon::parse($rankingCalendarMonth . '-01')->startOfMonth();
        $rankingCalendarPrevMonth = $rankingCalendarCurrentMonth->copy()->subMonth()->format('Y-m');
        $rankingCalendarNextMonth = $rankingCalendarCurrentMonth->copy()->addMonth()->format('Y-m');
        $keyword = trim((string) $request->input('keyword', ''));

        // ===== 月間記録用 =====
        $month = $request->input('month', now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        $maleRanking = collect();
        $femaleRanking = collect();
        $monthlyRecords = collect();
        $rankingLineupDates = [];

        if ($view === 'ranking') {
            // ===== グループメンバー =====
            $members = $group->users()
                ->where('is_admin', false)
                ->with('avatar')
                ->get();

            // ===== ランキング用 =====
            $memberIds = $members->pluck('id');

            $rankingSourceRecords = Record::with('shots')
                ->whereIn('user_id', $memberIds)
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->whereIn('practice_type', array_keys($availableScoreTypes))
                ->get()
                ->groupBy('user_id');

            $ranking = $members->map(function ($user) use ($rankingSourceRecords, $scoreTypes) {
                $records = $rankingSourceRecords->get($user->id, collect());
                $selectedRecords = in_array('all', $scoreTypes, true)
                    ? $records
                    : $records->whereIn('practice_type', $scoreTypes);

                return [
                    'user' => $user,
                    'selected' => $this->calc($selectedRecords),
                    'all' => $this->calc($records),
                    'official' => $this->calc($records->where('practice_type', 'official')),
                    'self' => $this->calc($records->where('practice_type', 'self')),
                ];
            });

            $sortRanking = function ($items) use ($limit) {
                $items = $items
                    ->sort(function ($a, $b) {
                        if ($a['selected']['rate'] == $b['selected']['rate']) {
                            return $b['selected']['hits'] <=> $a['selected']['hits'];
                        }

                        return $b['selected']['rate'] <=> $a['selected']['rate'];
                    })
                    ->values();

                if ($limit === 'all') {
                    return $items;
                }

                return $items->take((int) $limit)->values();
            };

            $maleRanking = $sortRanking(
                $ranking->filter(fn($row) => $row['user']->gender === 'male')
            );

            $femaleRanking = $sortRanking(
                $ranking->filter(fn($row) => $row['user']->gender === 'female')
            );

            $rankingLineupDates = Lineup::where('group_id', $group->id)
                ->whereYear('date', $rankingCalendarCurrentMonth->year)
                ->whereMonth('date', $rankingCalendarCurrentMonth->month)
                ->whereHas('members', function ($q) {
                    $q->where('is_absent', false)
                        ->whereNotNull('position');
                })
                ->pluck('date')
                ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                ->toArray();
        } else {
            $monthlyRecords = $this->monthlyRows($group, $currentMonth, $keyword);
        }

        return view('group_history.index', compact(
            'group',
            'view',
            'period',
            'limit',
            'startDate',
            'endDate',
            'rankingCalendarMonth',
            'rankingCalendarCurrentMonth',
            'rankingCalendarPrevMonth',
            'rankingCalendarNextMonth',
            'rankingLineupDates',
            'keyword',
            'scoreTypes',
            'availableScoreTypes',
            'maleRanking',
            'femaleRanking',
            'month',
            'currentMonth',
            'prevMonth',
            'nextMonth',
            'monthlyRecords'
        ));
    }

    private function calc($records)
    {
        $shots = $records->sum(function ($record) {
            return $record->shots->whereNotNull('result')->count();
        });

        $hits = $records->sum(function ($record) {
            return $record->shots->where('result', 'hit')->count();
        });

        $rate = $shots > 0 ? round(($hits / $shots) * 100, 1) : 0;

        return [
            'shots' => $shots,
            'hits' => $hits,
            'rate' => $rate,
        ];
    }

    private function monthlyRows(Group $group, Carbon $currentMonth, string $keyword = '')
    {
        $monthlyMembersQuery = $group->users()
            ->where('is_admin', false)
            ->with('avatar')
            ->when($group->uses_grades, function ($query) {
                $query
                    ->orderByRaw('users.grade_level IS NULL')
                    ->orderByDesc('users.grade_level');
            })
            ->orderBy('users.name');

        if ($keyword !== '') {
            $monthlyMembersQuery->where('users.name', 'like', '%' . $keyword . '%');
        }

        $monthlyMembers = $monthlyMembersQuery->get();

        $memberIds = $monthlyMembers->pluck('id');

        $monthlySourceRecords = Record::with('shots')
            ->whereIn('user_id', $memberIds)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->whereIn('practice_type', ['official', 'self'])
            ->get();

        return $monthlyMembers
            ->map(function ($user) use ($monthlySourceRecords) {
                $records = $monthlySourceRecords->where('user_id', $user->id);

                return [
                    'user' => $user,
                    'all' => $this->calc($records),
                    'official' => $this->calc($records->where('practice_type', 'official')),
                    'self' => $this->calc($records->where('practice_type', 'self')),
                ];
            })
            ->values();
    }

    private function rankingDateRange(Request $request, string $period): array
    {
        $today = now()->format('Y-m-d');

        if ($period === 'week') {
            $start = now()->subDays(6)->format('Y-m-d');
            $end = $today;

            return [
                $start,
                $end,
                $start,
                $end,
            ];
        }

        if ($period === 'month') {
            $start = now()->subDays(29)->format('Y-m-d');
            $end = $today;

            return [
                $start,
                $end,
                $start,
                $end,
            ];
        }

        if ($period === 'year') {
            $start = now()->subDays(364)->format('Y-m-d');
            $end = $today;

            return [
                $start,
                $end,
                $start,
                $end,
            ];
        }

        if ($period === 'date') {
            $date = $this->validDateOr((string) $request->input('start_date', ''), $today);

            return [
                $date,
                $date,
                $date,
                $date,
            ];
        }

        if ($period === 'custom') {
            $start = $this->validDateOr((string) $request->input('start_date', ''), $today);
            $end = $this->validDateOr((string) $request->input('end_date', ''), $start);

            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            return [
                $start,
                $end,
                $start,
                $end,
            ];
        }

        return [
            $today,
            $today,
            $today,
            $today,
        ];
    }

    private function validDateOr(string $date, string $fallback): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fallback;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        if (!checkdate($month, $day, $year)) {
            return $fallback;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function validMonthOr(string $month, string $fallback): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $fallback;
        }

        [$year, $monthValue] = array_map('intval', explode('-', $month));

        if ($monthValue < 1 || $monthValue > 12) {
            return $fallback;
        }

        return sprintf('%04d-%02d', $year, $monthValue);
    }

    public function monthlyPrint(Request $request, Group $group)
    {
        // ★ 所属チェック
        if (!$group->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $month = $request->month ?? now()->format('Y-m');
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $rows = $this->monthlyRows($group, $currentMonth)
            ->map(fn ($row) => [
                'name' => $row['user']->name,
                'grade' => $row['user']->grade_level ? $row['user']->grade_level . '学年' : '',
                'official' => $row['official'],
                'self' => $row['self'],
                'all' => $row['all'],
            ]);

        return view('group_history.monthly_print', compact(
            'group',
            'currentMonth',
            'rows'
        ));
    }

    public function monthlyCsv(Request $request, Group $group)
    {
        if (!$group->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $month = $request->month ?? now()->format('Y-m');
        $keyword = trim((string) $request->input('keyword', ''));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $rows = $this->monthlyRows($group, $currentMonth, $keyword);
        $filename = "group_{$group->id}_monthly_records_{$currentMonth->format('Y-m')}.csv";

        return response()->streamDownload(function () use ($rows, $group, $currentMonth) {
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                $group->name . ' 月間記録',
                $currentMonth->format('Y年n月'),
            ]);
            fputcsv($handle, []);
            fputcsv($handle, [
                '名前',
                '学年',
                '正規練 射数',
                '正規練 的中数',
                '正規練 的中率',
                '自主練 射数',
                '自主練 的中数',
                '自主練 的中率',
                '総合 射数',
                '総合 的中数',
                '総合 的中率',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['user']->name,
                    $row['user']->grade_level ? $row['user']->grade_level . '学年' : '',
                    $row['official']['shots'],
                    $row['official']['hits'],
                    $row['official']['rate'] . '%',
                    $row['self']['shots'],
                    $row['self']['hits'],
                    $row['self']['rate'] . '%',
                    $row['all']['shots'],
                    $row['all']['hits'],
                    $row['all']['rate'] . '%',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
