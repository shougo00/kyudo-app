<div class="rank-card">
    @php
        $scoreTypes = $scoreTypes ?? ['all'];
        $allSelected = in_array('all', $scoreTypes, true);
    @endphp

    <div class="rank-no">{{ $rank }}</div>

    <div class="avatar-area">
        @php
            $avatar = $row['user']->avatar;
        @endphp

        @if ($avatar)
            @foreach(['bottom','shoes','top','face','hair','accessory'] as $part)
                @if($avatar->$part)
                    <img src="{{ asset('avatars/'.$part.'/'.$avatar->$part->image_path) }}"
                         class="avatar-layer {{ $part }}">
                @endif
            @endforeach
        @else
            <img src="{{ asset('avatars/default.png') }}"
                 style="width:52px;height:62px;object-fit:contain;">
        @endif
    </div>

    <div class="rank-info">
        <div class="name-with-action">
            <div class="user-name">{{ $row['user']->name }}</div>
            <a href="{{ route('dashboard', ['group_id' => $group->id, 'user_id' => $row['user']->id]) }}"
               class="detail-link">
                詳細
            </a>
        </div>

        <div class="score-line {{ $allSelected ? 'active-score-subtle' : '' }}">
            <span>総合</span>
            <span>
                {{ $row['all']['shots'] }}射
                {{ $row['all']['hits'] }}中
                {{ $row['all']['rate'] }}%
            </span>
        </div>

        <div class="score-line {{ !$allSelected && in_array('official', $scoreTypes, true) ? 'active-score-subtle' : '' }}">
            <span>正規練</span>
            <span>
                {{ $row['official']['shots'] }}射
                {{ $row['official']['hits'] }}中
                {{ $row['official']['rate'] }}%
            </span>
        </div>

        <div class="score-line {{ !$allSelected && in_array('self', $scoreTypes, true) ? 'active-score-subtle' : '' }}">
            <span>自主練</span>
            <span>
                {{ $row['self']['shots'] }}射
                {{ $row['self']['hits'] }}中
                {{ $row['self']['rate'] }}%
            </span>
        </div>

        <div class="score-line {{ !$allSelected && in_array('match', $scoreTypes, true) ? 'active-score-subtle' : '' }}">
            <span>試合</span>
            <span>
                {{ $row['match']['shots'] }}射
                {{ $row['match']['hits'] }}中
                {{ $row['match']['rate'] }}%
            </span>
        </div>
    </div>
</div>
