<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPasswordToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'after'      => 'name',
            ],
        ];
        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'password');
    }
}
