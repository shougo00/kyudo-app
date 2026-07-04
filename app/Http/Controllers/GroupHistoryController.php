<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Record;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupHistoryController extends Controller
{
    public function index(Request $request, Group $group)
    {
        if (!$group->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $view = $request->input('view', 'ranking');

        $availableScoreTypes = [
            'official' => '正規練',
            'self' => '自主練',
            'match' => '試合',
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
        $period = $request->input('period', 'today');
        $limit = $request->input('limit', 10);
        $keyword = trim((string) $request->input('keyword', ''));

        // ===== 月間記録用 =====
        $month = $request->input('month', now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        // ===== グループメンバー =====
        $membersQuery = $group->users()
            ->where('is_admin', false)
            ->with('avatar');

        $members = $membersQuery->get();

        // ===== ランキング用 =====
        [$start, $end] = $this->periodRange($period);

        $memberIds = $members->pluck('id');

        $rankingSourceRecords = Record::with('shots')
            ->whereIn('user_id', $memberIds)
            ->whereBetween('date', [$start, $end])
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
                'match' => $this->calc($records->where('practice_type', 'match')),
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

            if ($limit !== 'all') {
                $items = $items->take((int) $limit)->values();
            }

            return $items;
        };

        $maleRanking = $sortRanking(
            $ranking->filter(fn($row) => $row['user']->gender === 'male')
        );

        $femaleRanking = $sortRanking(
            $ranking->filter(fn($row) => $row['user']->gender === 'female')
        );

        // ===== 月間記録用 =====
        // records に group_id が無いので、メンバーの user_id で絞る
       // ===== 月間記録用 =====
        $monthlyMembersQuery = $group->users()
            ->where('is_admin', false)
            ->with('avatar');

        if ($keyword !== '') {
            $monthlyMembersQuery->where('users.name', 'like', '%' . $keyword . '%');
        }

        $monthlyMembers = $monthlyMembersQuery->get();

        $memberIds = $monthlyMembers->pluck('id');

        $monthlySourceRecords = Record::with('shots')
            ->whereIn('user_id', $memberIds)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->get();

        $monthlyRecords = $monthlyMembers
            ->sortBy('name')
            ->map(function ($user) use ($monthlySourceRecords) {
                $records = $monthlySourceRecords->where('user_id', $user->id);

                return [
                    'user' => $user,
                    'all' => $this->calc($records),
                    'official' => $this->calc($records->where('practice_type', 'official')),
                    'self' => $this->calc($records->where('practice_type', 'self')),
                    'match' => $this->calc($records->where('practice_type', 'match')),
                ];
            })
            ->values();

        return view('group_history.index', compact(
            'group',
            'view',
            'period',
            'limit',
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

    private function periodRange($period)
    {
        if ($period === 'week') {
            return [
                now()->subDays(6)->format('Y-m-d'),
                now()->format('Y-m-d'),
            ];
        }

        if ($period === 'month') {
            return [
                now()->subDays(29)->format('Y-m-d'),
                now()->format('Y-m-d'),
            ];
        }

        if ($period === 'year') {
            return [
                now()->startOfYear()->format('Y-m-d'),
                now()->endOfYear()->format('Y-m-d'),
            ];
        }

        return [
            now()->format('Y-m-d'),
            now()->format('Y-m-d'),
        ];
    }
    public function monthlyPrint(Request $request, Group $group)
    {
        // ★ 所属チェック
        if (!$group->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $month = $request->month ?? now()->format('Y-m');
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $members = $group->users()
            ->where('is_admin', false)
            ->get()
            ->sortBy('name');

        $memberIds = $members->pluck('id');

        $records = Record::with('shots')
            ->whereIn('user_id', $memberIds)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->get();

        $rows = $members->map(function ($user) use ($records) {

            $userRecords = $records->where('user_id', $user->id);

            return [
                'name' => $user->name,
                'official' => $this->calc($userRecords->where('practice_type', 'official')),
                'self' => $this->calc($userRecords->where('practice_type', 'self')),
                'match' => $this->calc($userRecords->where('practice_type', 'match')),
                'all' => $this->calc($userRecords),
            ];
        });

        return view('group_history.monthly_print', compact(
            'group',
            'currentMonth',
            'rows'
        ));
    }
}
