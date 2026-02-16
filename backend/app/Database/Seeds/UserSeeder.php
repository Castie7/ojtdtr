<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'student_id'           => '2001501',
            'name'                 => 'Karlo Jim Leano',
            'password'             => password_hash('admin123', PASSWORD_DEFAULT),
            'total_hours_required' => 386.00,
            'hours_rendered'       => 0.00
        ];

        // Check if user exists first to avoid duplicates
        $db = \Config\Database::connect();
        $builder = $db->table('users');
        
        if ($builder->where('student_id', '2001501')->countAllResults() === 0) {
            $builder->insert($data);
            echo "User Karlo Jim Leano created successfully.\n";
        } else {
            $builder->where('student_id', '2001501')->update(['total_hours_required' => 386.00]);
            echo "User updated to 386 hours.\n";
        }
    }
}
