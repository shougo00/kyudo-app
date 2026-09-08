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

        .monthly-print-section + .monthly-print-section {
            margin-top: 14px;
        }

        .monthly-print-section-title {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 700;
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

        @foreach ($monthlyPrintSections as $monthlyPrintSection)
            @include('group_history.partials.monthly_print_table', [
                'sectionTitle' => $monthlyPrintSection['title'],
                'rows' => $monthlyPrintSection['rows'],
            ])
        @endforeach
    </main>
</body>
</html>
