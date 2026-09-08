@php
    $printRows = collect($rows ?? []);
    $rankColspan = $group->show_monthly_rank_on_print ? 12 : 11;
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
                <th colspan="3">正規練</th>
                <th colspan="3">自主練</th>
                <th colspan="3">総合</th>
                @if($group->show_monthly_rank_on_print)
                    <th rowspan="2">順位</th>
                @endif
            </tr>
            <tr>
                <th>射数</th>
                <th>的中数</th>
                <th>的中率</th>

                <th>射数</th>
                <th>的中数</th>
                <th>的中率</th>

                <th>射数</th>
                <th>的中数</th>
                <th>的中率</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($printRows as $row)
                <tr>
                    <td class="name-col">{{ $row['name'] }}</td>
                    <td>{{ $row['grade'] }}</td>

                    <td>{{ $row['official']['shots'] }}</td>
                    <td>{{ $row['official']['hits'] }}</td>
                    <td>{{ $row['official']['rate'] }}%</td>

                    <td>{{ $row['self']['shots'] }}</td>
                    <td>{{ $row['self']['hits'] }}</td>
                    <td>{{ $row['self']['rate'] }}%</td>

                    <td>{{ $row['all']['shots'] }}</td>
                    <td>{{ $row['all']['hits'] }}</td>
                    <td>{{ $row['all']['rate'] }}%</td>
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
