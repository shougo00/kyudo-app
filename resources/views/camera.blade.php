@extends('layouts.user')

@section('content')

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose"></script>
@vite(['resources/css/camera/camera.css', 'resources/js/camera/camera.js'])
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="video-container">
    <video id="camera" autoplay playsinline muted></video>
    <canvas id="canvas"></canvas>

    <div id="phase">胴作り</div>
    <div id="metrics"></div>
    <div id="overlayCheckpoints"></div>
</div>


<div id="cameraInfo">カメラ未起動</div>
<button class="kyudo-btn btn-danger" onclick="resetPhase()">最初から</button>
<button class="kyudo-btn btn-main" onclick="startCamera()">カメラ起動</button>
<button class="kyudo-btn btn-sub" onclick="switchCamera()">カメラ切替</button>
<button class="kyudo-btn btn-sub" onclick="toggleDirection()">向き切替</button>
<a href="{{ route('kyudo.result.list') }}" class="kyudo-btn btn-green">
    記録確認
</a>

@endsection