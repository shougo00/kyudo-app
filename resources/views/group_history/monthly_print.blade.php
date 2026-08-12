<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $group->name }} 月間記録</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin: 12px;
        }

        .print-actions button {
            border: 1px solid #111;
            border-radius: 6px;
            padding: 8px 12px;
            color: #fff;
            background: #111;
            font-weight: 700;
            cursor: pointer;
        }

        h3 {
            margin: 0 0 10px;
            text-align: center;
            font-size: 18px;
        }

        .print-page {
            padding: 8mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        th {
            background: #eee;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .name-col {
            text-align: left;
            white-space: nowrap;
        }

        @media print {
            .print-actions {
                display: none;
            }

            .print-page {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">印刷</button>
    </div>

    <main class="print-page">
        <h3>{{ $group->name }} 月間記録（{{ $currentMonth->format('Y年n月') }}）</h3>

        <table>
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
                @foreach ($rows as $row)
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
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
