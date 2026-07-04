@extends('layouts.user')

@section('content')

@vite(['resources/css/home/home.css', 'resources/js/home/home.js'])
<link rel="manifest" href="/manifest.json">

<meta name="theme-color" content="#317EFB">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="弓道">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-3 {{ $type=='self' ? 'self-bg' : 'official-bg' }}" id="records-container" data-type="{{ $type }}">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">的中記録</h4>

        <div class="d-flex align-items-center">
            <!-- 的中率 -->
            <div class="summary-text me-2" id="summary">
                <span class="shots">{{ $totalShots }}射</span>
                <span class="hits">{{ $totalHits }}中</span>
                <span class="rate">{{ number_format($hitRate, 1) }}％</span>
            </div>

            <!-- ボタン -->
            <div class="practice-type-buttons">
                <a href="{{ route('home', ['date'=>$date, 'type'=>'official']) }}" 
                class="btn btn-sm {{ ($type ?? 'official') == 'official' ? 'btn-danger' : 'btn-outline-danger' }}">
                    正規練
                </a>

                <a href="{{ route('home', ['date'=>$date, 'type'=>'self']) }}" 
                class="btn btn-sm {{ ($type ?? 'official') == 'self' ? 'btn-primary' : 'btn-outline-primary' }}">
                    自主練
                </a>
            </div>
        </div>
    </div>

    <!-- 日付ナビ -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <a href="{{ route('home', ['date' => $prevDate, 'type'=>$type]) }}" class="btn btn-outline-secondary">＜</a>

        <form id="date-form" method="GET" action="{{ route('home') }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="date" name="date" value="{{ $date }}" class="form-control text-center" id="date-picker">
        </form>

        <a href="{{ route('home', ['date' => $nextDate, 'type'=>$type]) }}" class="btn btn-outline-secondary">＞</a>
    </div>

    <!-- 立追加 -->
    <form method="POST" action="{{ route('records.store') }}" class="mb-3">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="practice_type" value="{{ $type }}">
        <button class="btn btn-primary w-100">＋ 立を追加</button>
    </form>

    <!-- 一覧 -->
    @foreach($records as $record)
        <div class="card mb-3 p-2" data-record-id="{{ $record->id }}">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <strong>{{ $record->tate_no }}立目</strong>
                    <button class="delete-record ms-2" data-id="{{ $record->id }}" title="立を削除">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <span id="result-{{ $record->id }}">{{ $record->shots->where('result', 'hit')->count() }}/4</span>
            </div>
            <div class="d-flex justify-content-around mt-2">
                @foreach($record->shots as $shot)
                    <button class="shot-btn {{ $shot->result == 'hit' ? 'shot-hit' : '' }} {{ $shot->result == 'miss' ? 'shot-miss' : '' }} {{ $shot->result == null ? 'shot-none' : '' }}"
                            data-id="{{ $shot->id }}"
                            data-record="{{ $record->id }}"
                            data-result="{{ $shot->result }}"
                            title="クリックで入力">

                        @if($shot->result == 'hit')
                        <i class="fa-regular fa-circle"></i>
                        @elseif($shot->result == 'miss')
                            <i class="fas fa-xmark"></i>
                        @else
                            ＋
                        @endif

                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@endsection