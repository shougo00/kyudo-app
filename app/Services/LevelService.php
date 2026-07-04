<?php

namespace App\Services;

use App\Models\User;

class LevelService
{
    /**
     * EXP付与 + レベルアップ処理
     */
    public static function addExp(User $user, int $exp): void
    {
        // EXP加算
        $user->exp += $exp;

        // レベルアップ判定（複数回対応）
        while ($user->exp >= $user->next_exp) {

            // 次レベルに必要なEXPを消費
            $user->exp -= $user->next_exp;

            // レベルアップ
            $user->level++;

            // レベルアップ報酬（例：ポイント）
            $user->point += 500;

            // 次レベル必要EXPを更新
            $user->next_exp = self::calcNextExp($user->level);
        }

        $user->save();
    }

    /**
     * 次のレベルに必要なEXP計算
     */
    public static function calcNextExp(int $level): int
    {
        // 基本式（おすすめ）
        return 100 + (($level - 1) * 50);
    }
}
