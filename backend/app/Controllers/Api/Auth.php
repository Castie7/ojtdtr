<?php namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class Auth extends ResourceController {
    use ResponseTrait;

    public function login() {
        // 1. Get JSON Data
        $json = $this->request->getJSON();
        $studentId = $json->student_id ?? '';
        $password = $json->password ?? ''; // In real app, verify hash

        // 2. Validate against Database
        $db = \Config\Database::connect();
        $user = $db->table('users')->where('student_id', $studentId)->get()->getRowArray();

        // 3. Verify Password
        if ($user && password_verify($password, $user['password'])) {
            // Success: Return User ID and a dummy token
            return $this->respond([
                'status' => 200,
                'message' => 'Login Successful',
                'user_id' => $user['id'], 
                'token' => bin2hex(random_bytes(16)), // Dummy token for now
                'user' => [
                    'name' => $user['name'],
                    'student_id' => $user['student_id']
                ]
            ]);
        } else {
            // Fail
            return $this->failNotFound('Invalid Student ID or Password');
        }
    }
} 