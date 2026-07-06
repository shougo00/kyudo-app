<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\KyudoResult;
use App\Models\Lineup;
use App\Models\MatchTeam;
use App\Models\News;
use App\Models\Record;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use SplFileObject;

class SystemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $logFiles = $this->logFiles();
        $selectedLog = $this->selectedLog($request, $logFiles);

        return view('admin.system.index', [
            'stats' => $this->stats(),
            'recentUsers' => User::latest()->limit(8)->get(),
            'recentGroups' => Group::with(['host', 'users'])->latest()->limit(8)->get(),
            'recentRecords' => Record::with('user')->latest()->limit(10)->get(),
            'logFiles' => $logFiles,
            'selectedLog' => $selectedLog,
            'logLines' => $selectedLog ? $this->tailLog($selectedLog['path']) : [],
        ]);
    }

    private function authorizeSystemAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || $user->username !== 'KANRI') {
            abort(403, 'システム管理者だけがアクセスできます');
        }
    }

    private function stats(): array
    {
        return [
            'users' => User::count(),
            'normal_users' => User::where('is_admin', false)->count(),
            'admins' => User::where('is_admin', true)->count(),
            'groups' => Group::count(),
            'records' => Record::count(),
            'shots' => Shot::count(),
            'lineups' => Lineup::count(),
            'match_teams' => MatchTeam::count(),
            'kyudo_results' => KyudoResult::count(),
            'news' => News::count(),
            'sessions' => $this->tableCount('sessions'),
            'jobs' => $this->tableCount('jobs'),
        ];
    }

    private function tableCount(string $table): int
    {
        return DB::getSchemaBuilder()->hasTable($table)
            ? DB::table($table)->count()
            : 0;
    }

    private function logFiles(): array
    {
        return collect(File::glob(storage_path('logs/*.log')) ?: [])
            ->map(fn($path) => [
                'name' => basename($path),
                'path' => $path,
                'size' => File::size($path),
                'updated_at' => date('Y-m-d H:i:s', File::lastModified($path)),
            ])
            ->sortByDesc('updated_at')
            ->values()
            ->all();
    }

    private function selectedLog(Request $request, array $logFiles): ?array
    {
        if (empty($logFiles)) {
            return null;
        }

        $requested = basename((string) $request->query('log', ''));

        return collect($logFiles)->firstWhere('name', $requested) ?? $logFiles[0];
    }

    private function tailLog(string $path, int $lines = 300): array
    {
        if (!File::exists($path) || !File::isFile($path)) {
            return [];
        }

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $lines);
        $result = [];

        for ($line = $start; $line <= $lastLine; $line++) {
            $file->seek($line);
            $text = rtrim((string) $file->current(), "\r\n");

            if ($text !== '') {
                $result[] = $text;
            }
        }

        return $result;
    }
}
