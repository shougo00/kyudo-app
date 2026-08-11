<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>アバター素材 A4シート</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9ecef;
            color: #111;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #ced4da;
            background: #fff;
        }

        .print-toolbar button,
        .print-toolbar a {
            border: 1px solid #111;
            border-radius: 6px;
            padding: 8px 12px;
            color: #111;
            background: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .print-toolbar button {
            color: #fff;
            background: #111;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 10mm;
            background: #fff;
        }

        .sheet-title {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6mm;
            border-bottom: 1px solid #111;
            padding-bottom: 3mm;
        }

        .sheet-title h1 {
            margin: 0;
            font-size: 18pt;
            line-height: 1.2;
        }

        .sheet-title span {
            align-self: end;
            color: #555;
            font-size: 9pt;
        }

        .section {
            break-inside: avoid;
            margin-bottom: 6mm;
        }

        .section h2 {
            margin: 0 0 2.5mm;
            font-size: 12pt;
        }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4mm;
        }

        .item-cell {
            min-height: 48mm;
            border: 0.35mm solid #d9d9d9;
            border-radius: 2mm;
            padding: 2mm;
            break-inside: avoid;
        }

        .item-preview {
            width: 34mm;
            height: 34mm;
            margin: 0 auto 1.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .item-name {
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }

        .item-meta {
            margin-top: 1mm;
            color: #555;
            font-size: 7pt;
            line-height: 1.25;
            word-break: break-word;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <a href="{{ route('admin.system.avatar-items') }}">管理画面へ戻る</a>
        <button type="button" onclick="window.print()">印刷 / PDF保存</button>
    </div>

    <main class="sheet">
        <div class="sheet-title">
            <h1>MATOWA アバター素材シート</h1>
            <span>{{ now()->format('Y-m-d H:i') }}</span>
        </div>

        @foreach($typeLabels as $type => $label)
            @php $items = $itemsByType->get($type, collect()); @endphp
            <section class="section">
                <h2>{{ $label }}</h2>
                <div class="item-grid">
                    @forelse($items as $item)
                        <div class="item-cell">
                            <div class="item-preview">
                                <img src="{{ asset($item->assetPath()) }}" alt="{{ $item->name }}">
                            </div>
                            <div class="item-name">{{ $item->name }}</div>
                            <div class="item-meta">
                                X{{ $item->position_x }} / Y{{ $item->position_y }} /
                                {{ $item->display_width }}x{{ $item->display_height }} /
                                Z{{ $item->z_index }}
                            </div>
                        </div>
                    @empty
                        <div class="item-cell">
                            <div class="item-name">素材なし</div>
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </main>
</body>
</html>
