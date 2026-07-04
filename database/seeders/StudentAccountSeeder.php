<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentAccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->students() as $student) {
            User::updateOrCreate(
                ['username' => $student['student_no']],
                [
                    'name' => $student['name'],
                    'email' => null,
                    'password' => Hash::make($student['student_no']),
                    'is_admin' => false,
                    'gender' => $student['gender'],
                    'grade_level' => $student['grade_level'],
                ]
            );
        }
    }

    private function students(): array
    {
        return [
            ['student_no' => '202331001', 'name' => '明松大樹', 'gender' => 'male', 'grade_level' => 4],
            ['student_no' => '202337017', 'name' => '加本麦人', 'gender' => 'male', 'grade_level' => 4],
            ['student_no' => '202331086', 'name' => '平岡輝', 'gender' => 'male', 'grade_level' => 4],
            ['student_no' => '202325077', 'name' => '三木孝太郎', 'gender' => 'male', 'grade_level' => 4],
            ['student_no' => '202325078', 'name' => '南優来', 'gender' => 'male', 'grade_level' => 4],
            ['student_no' => '202425005', 'name' => '内田丈登', 'gender' => 'male', 'grade_level' => 3],
            ['student_no' => '202425029', 'name' => '竹口晃一郎', 'gender' => 'male', 'grade_level' => 3],
            ['student_no' => '202437040', 'name' => '知名海音', 'gender' => 'male', 'grade_level' => 3],
            ['student_no' => '202431078', 'name' => '藤高琉生', 'gender' => 'male', 'grade_level' => 3],
            ['student_no' => '202537006', 'name' => '飯田光', 'gender' => 'male', 'grade_level' => 2],
            ['student_no' => '202523007', 'name' => '石田皇輝', 'gender' => 'male', 'grade_level' => 2],
            ['student_no' => '202525027', 'name' => '橘龍之介', 'gender' => 'male', 'grade_level' => 2],
            ['student_no' => '202640004', 'name' => '荒川勇人', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202618003', 'name' => '大屋敷官明', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202615019', 'name' => '多田永遠', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202640054', 'name' => '坂東祐星', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202631075', 'name' => '松下竜大', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202631085', 'name' => '山﨑世絆', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202640084', 'name' => '吉田裕貴', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202615037', 'name' => '升島大輔', 'gender' => 'male', 'grade_level' => 1],
            ['student_no' => '202325002', 'name' => '淺野智子', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202323018', 'name' => '小賀野鈴', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202325021', 'name' => '沖田絢香', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202337070', 'name' => '落合南海', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202325031', 'name' => '甲地里咲綺', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202325071', 'name' => '廣澤星香', 'gender' => 'female', 'grade_level' => 4],
            ['student_no' => '202420021', 'name' => '小﨑由菜', 'gender' => 'female', 'grade_level' => 3],
            ['student_no' => '202423043', 'name' => '田中夢乃', 'gender' => 'female', 'grade_level' => 3],
            ['student_no' => '202431075', 'name' => '坂東瑞季', 'gender' => 'female', 'grade_level' => 3],
            ['student_no' => '202531019', 'name' => '植田しおん', 'gender' => 'female', 'grade_level' => 2],
            ['student_no' => '202526005', 'name' => '梅木花', 'gender' => 'female', 'grade_level' => 2],
            ['student_no' => '202531025', 'name' => '遠藤未来', 'gender' => 'female', 'grade_level' => 2],
            ['student_no' => '202525009', 'name' => '岡部小梅', 'gender' => 'female', 'grade_level' => 2],
            ['student_no' => '202520086', 'name' => '森穂華', 'gender' => 'female', 'grade_level' => 2],
            ['student_no' => '202631003', 'name' => '明松凜', 'gender' => 'female', 'grade_level' => 1],
            ['student_no' => '202615049', 'name' => '石川つぐみ', 'gender' => 'female', 'grade_level' => 1],
            ['student_no' => '202620007', 'name' => '市場笑怜', 'gender' => 'female', 'grade_level' => 1],
            ['student_no' => '202625019', 'name' => '小松桃子', 'gender' => 'female', 'grade_level' => 1],
            ['student_no' => '202631046', 'name' => '高山しゃいな', 'gender' => 'female', 'grade_level' => 1],
            ['student_no' => '202631052', 'name' => '近澤緋那', 'gender' => 'female', 'grade_level' => 1],
        ];
    }
}
