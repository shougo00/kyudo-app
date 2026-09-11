@php
    $printRows = collect($rows ?? []);
    $scoreColumns = collect($scoreColumns ?? [
        ['key' => 'official', 'label' => '正規練'],
        ['key' => 'self', 'label' => '自主練'],
        ['key' => 'all', 'label' => '総合'],
    ])->values();
    $rankColspan = 2 + ($scoreColumns->count() * 3) + ($group->show_monthly_rank_on_print ? 1 : 0);
@endphp

<section class="monthly-print-section">
    @if(!empty($sectionTitle))
        <h4 class="monthly-print-section-title">{{ $sectionTitle }}</h4>
    @endif

    <table class="print-table">
        <thead>
            <tr>
                <th rowspan="2">名前</th>
                <th rowspan="2">学年</th>
                @foreach($scoreColumns as $scoreColumn)
                    <th colspan="3">{{ $scoreColumn['label'] }}</th>
                @endforeach
                @if($group->show_monthly_rank_on_print)
                    <th rowspan="2">順位</th>
                @endif
            </tr>
            <tr>
                @foreach($scoreColumns as $scoreColumn)
                    <th>射数</th>
                    <th>的中数</th>
                    <th>的中率</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse ($printRows as $row)
                <tr>
                    <td class="name-col">{{ $row['name'] }}</td>
                    <td>{{ $row['grade'] }}</td>

                    @foreach($scoreColumns as $scoreColumn)
                        @php
                            $score = $row[$scoreColumn['key']] ?? ['shots' => 0, 'hits' => 0, 'rate' => 0];
                        @endphp
                        <td>{{ $score['shots'] }}</td>
                        <td>{{ $score['hits'] }}</td>
                        <td>{{ $score['rate'] }}%</td>
                    @endforeach
                    @if($group->show_monthly_rank_on_print)
                        <td>{{ $row['rank'] ? $row['rank'] . '位' : '' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $rankColspan }}">記録はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>
