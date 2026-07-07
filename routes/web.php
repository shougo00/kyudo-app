<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupRecordController;
use App\Http\Controllers\LineupController;
use App\Http\Controllers\AttendanceController; 
use App\Http\Controllers\KyudoResultController;      
use App\Http\Controllers\KyudoResultPageController;
use App\Http\Controllers\GroupHistoryController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\MatchLineupController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Admin\SystemController;
Route::get('/', function () {
    return redirect('/login');
});



// 1️⃣ ダッシュボードルート（ログイン後のホーム）
Route::middleware([ 'verified'])->group(function () {
    
    // 2️⃣ プロフィール関連
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/unlock', [SettingController::class, 'unlock'])->name('settings.unlock');
    Route::patch('/settings/user', [SettingController::class, 'updateUser'])->name('settings.user.update');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/promote-grades', [SettingController::class, 'promoteGrades'])->name('settings.promote-grades');
    Route::get('/admin/system', [SystemController::class, 'index'])->name('admin.system.index');
    Route::get('/admin/system/users', [SystemController::class, 'users'])->name('admin.system.users');
    Route::patch('/admin/system/users/{user}', [SystemController::class, 'updateUser'])->name('admin.system.users.update');
    Route::delete('/admin/system/users/{user}', [SystemController::class, 'destroyUser'])->name('admin.system.users.destroy');
    Route::get('/admin/system/groups', [SystemController::class, 'groups'])->name('admin.system.groups');
    Route::delete('/admin/system/groups/{group}', [SystemController::class, 'destroyGroup'])->name('admin.system.groups.destroy');

    // 3️⃣ ユーザ管理画面
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

   Route::get('/home', [RecordController::class, 'index'])->name('home');

    Route::resource('news', NewsController::class)->except(['show']);

    // ホーム画面に変更
    Route::get('/home', [RecordController::class, 'index'])->name('home');
    // 立追加はそのまま
    Route::post('/records', [RecordController::class, 'store'])->name('records.store');
    Route::post('/shots/{id}', [RecordController::class, 'updateShot'])->name('shots.update');
    Route::delete('/records/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
    Route::get('/dashboard', [RecordController::class, 'dashboard'])->name('dashboard');

   // routes/web.php
    Route::get('/avatar', [AvatarController::class,'show'])->name('avatar.show');
    Route::get('/avatar/edit', [AvatarController::class,'edit'])->name('avatar.edit');
    Route::post('/avatar/update', [AvatarController::class,'update'])->name('avatar.update');


    Route::get('/camera', function () {
        if (!auth()->user()?->uses_camera) {
            return redirect()->route('settings.index')->with('status', 'camera-disabled');
        }

        return view('camera');
    })->name('camera');

   Route::middleware('auth')->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups');
       
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');

    Route::get('/groups/join', [GroupController::class, 'joinForm'])->name('groups.join.form');
    Route::post('/groups/join', [GroupController::class, 'join'])->name('groups.join');
    });
    Route::post('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');

    // グループ記録ページ
    Route::get('/group/{groupId}/records', [GroupRecordController::class, 'index'])->name('group.records');
    Route::post('/group/{groupId}/add-tate', [GroupRecordController::class, 'addTate']);
    Route::post('/group/{groupId}/records/switch-sheet', [GroupRecordController::class, 'switchOfficialSheet']);
    Route::post('/group/{groupId}/records/scoring-mode', [GroupRecordController::class, 'updateOfficialScoringMode']);
    Route::get('/group/{groupId}/match-records', [GroupRecordController::class, 'matchIndex'])->name('group.match-records');
    Route::post('/group/{groupId}/match-add-tate', [GroupRecordController::class, 'addMatchTate']);
    Route::post('/group/shot/{id}', [GroupRecordController::class, 'updateShot']);
    Route::get('/group/{groupId}/match-lineup', [MatchLineupController::class, 'index'])->name('group.match-lineup');
    Route::post('/group/{groupId}/match-teams', [MatchLineupController::class, 'storeTeam']);
    Route::patch('/match-teams/{team}', [MatchLineupController::class, 'updateTeam']);
    Route::delete('/match-teams/{team}', [MatchLineupController::class, 'destroy']);
    Route::post('/match-teams/{team}/tate', [MatchLineupController::class, 'saveTate']);
    Route::post('/match-teams/{team}/tate-timer', [MatchLineupController::class, 'saveTateTimer']);
    Route::post('/match-teams/{team}/tate-scoring-mode', [MatchLineupController::class, 'saveTateScoringMode']);
    //　立順作成ページ
    Route::get('/group/{id}/lineup',[LineupController::class,'index']); 
    Route::post('/lineup/{id}/save',[LineupController::class,'save']); 
    Route::post('/lineup/{id}/random',[LineupController::class,'random']);
    Route::post('/lineup/{lineup}/copy-previous', [LineupController::class, 'copyPrevious']);
    // 出欠ページ
    Route::get('/group/{groupId}/attendance', [AttendanceController::class, 'index']);
    Route::post('/group/{groupId}/attendance', [AttendanceController::class, 'save']);
    Route::post('/group/{groupId}/attendance/all-absent', [AttendanceController::class, 'allAbsent']);
    Route::post('/group/{groupId}/attendance/weekly', [AttendanceController::class, 'weeklySettings']);
    Route::post('/kyudo-results', [KyudoResultController::class, 'store'])
        ->middleware('auth');

  
    //射形記録画面
    Route::get('/kyudo-result-list', [KyudoResultPageController::class, 'index'])
        ->name('kyudo.result.list');
    });
    Route::delete('/kyudo-results/{result}', [KyudoResultPageController::class, 'destroy'])
    ->name('kyudo.results.destroy')
    ->middleware('auth');


    Route::get('/group/{group}/history', [GroupHistoryController::class, 'index'])
        ->name('group.history')
        ->middleware('auth');
    
    Route::get('/group/{group}/monthly-print', [GroupHistoryController::class, 'monthlyPrint'])
    ->name('group.monthlyPrint');
    Route::get('/group/{group}/monthly-csv', [GroupHistoryController::class, 'monthlyCsv'])
    ->name('group.monthlyCsv')
    ->middleware('auth');
    
    
    //ライン用
    Route::post('/line/webhook', [LineWebhookController::class, 'handle']);


    //タブレット設定時指定ページに飛ばす処理
    Route::get('/dashboard', function () {
        if (auth()->check() && auth()->user()->is_admin) {

                $groupId = auth()->user()->groups->first()->id ?? null;

                if ($groupId) {
                    return redirect()->route('group.history', ['group' => $groupId]);
                }

                // グループ無い場合の逃げ道
                return redirect()->route('groups');
            }

            return app(\App\Http\Controllers\RecordController::class)->dashboard(request());
    })->name('dashboard')->middleware('auth');
    Route::get('/home', function () {
        if (auth()->check() && auth()->user()->is_admin) {

            $groupId = auth()->user()->groups->first()->id ?? null;

            if ($groupId) {
                return redirect()->route('group.history', ['group' => $groupId]);
            }

            return redirect()->route('groups');
        }

        return app(\App\Http\Controllers\RecordController::class)->index(request());
    })->name('home')->middleware('auth');

// 4️⃣ 認証ルート
require __DIR__.'/auth.php';
