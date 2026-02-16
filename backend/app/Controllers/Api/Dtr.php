<?php namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class Dtr extends ResourceController {
    use ResponseTrait;

    protected $format = 'json';
    protected $db;

    public function __construct() {
        $this->db = \Config\Database::connect();
    }

    // GET: Fetch Dashboard Stats
    public function stats($userId) {
        $builder = $this->db->table('users');
        $user = $builder->where('id', $userId)->get()->getRowArray();
        
        // Calculate remaining
        $user['remaining'] = $user['total_hours_required'] - $user['hours_rendered'];
        $user['percentage'] = ($user['hours_rendered'] / $user['total_hours_required']) * 100;

        // Count Late Arrivals
        $user['total_lates'] = $this->db->table('attendance')
                                        ->where('user_id', $userId)
                                        ->where('status', 'LATE')
                                        ->countAllResults();

        return $this->respond($user);
    }

    // POST: Clock In
    public function clockIn() {
        $json = $this->request->getJSON();
        $userId = $json->userId;
        
        // Use provided date/time or default to now
        $date = $json->date ?? date('Y-m-d');
        $time = $json->timeIn ?? date('Y-m-d H:i:s');
        $timeOut = $json->timeOut ?? null; // Optional timeOut

        // Check if currently clocked in (pending time_out)
        // Only check if we are NOT providing a timeOut (i.e. starting a new open session)
        if (!$timeOut) {
            $active = $this->db->table('attendance')
                           ->where('user_id', $userId)
                           ->where('date', $date)
                           ->where('time_out', null)
                           ->countAllResults();

            if ($active > 0) return $this->fail('You are currently clocked in. Please clock out first.', 400);
        }

        // Determine status
        // AM: Late if > 08:00:59 (Strict 8:00 cutoff usually means 8:01 is late)
        // PM: Late if > 13:00:59 (Strict 1:00 PM cutoff usually means 1:01 is late)
        $timestamp = strtotime($time);
        $hour = (int)date('H', $timestamp);
        $minute = (int)date('i', $timestamp);
        
        $isLate = false;
        if ($hour < 12) {
             // AM Shift: Late if 8:01 or later
             if ($hour > 8 || ($hour == 8 && $minute >= 1)) $isLate = true;
        } else {
             // PM Shift: Late if 13:01 or later
             if ($hour > 13 || ($hour == 13 && $minute >= 1)) $isLate = true;
        }
        
        $data = [
            'user_id' => $userId,
            'date' => $date,
            'time_in' => $time,
            'time_out' => $timeOut, // Insert time_out if provided
            'status' => $isLate ? 'LATE' : 'PRESENT' 
        ];

        $this->db->table('attendance')->insert($data);
        
        // Recalculate hours if we inserted a complete log
        if ($timeOut) {
            $this->updateTotalHours($userId);
        }

        return $this->respondCreated(['message' => 'Clock In Successful', 'time' => $time]);
    }

    // POST: Clock Out
    public function clockOut() {
        $json = $this->request->getJSON();
        $userId = $json->userId;
        $date = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // Find active session
        $log = $this->db->table('attendance')
                    ->where('user_id', $userId)
                    ->where('date', $date)
                    ->where('time_out', null)
                    ->orderBy('time_in', 'DESC')
                    ->get()
                    ->getRowArray();

        if (!$log) return $this->fail('No active Clock In record found for today', 400);

        // Calculate hours
        $t1 = strtotime($log['time_in']);
        $t2 = strtotime($now);
        $hours = round(abs($t2 - $t1) / 3600, 2);

        // Update Attendance
        // Check if remarks were provided, if not preserve previous or set default
        $remarks = $json->remarks ?: ($log['remarks'] ?: 'Task done');

        $this->db->table('attendance')->where('id', $log['id'])->update([
            'time_out' => $now,
            'remarks' => $remarks
        ]);

        // Update User Total
        $this->updateTotalHours($userId);

        return $this->respond(['message' => 'Clock Out Successful', 'hours_added' => $hours]);
    }
    // POST: Edit Log
    public function editLog() {
        $json = $this->request->getJSON();
        $id = $json->id;
        $userId = $json->userId;

        $data = [
            'date' => $json->date,
            'time_in' => $json->time_in,
            'time_out' => $json->time_out ?: null,
            'remarks' => $json->remarks
        ];

        $this->db->table('attendance')->where('id', $id)->update($data);
        
        // Recalculate total hours to ensure accuracy
        $this->updateTotalHours($userId);

        return $this->respond(['message' => 'Log updated successfully']);
    }

    // POST: Import CSV
    public function importCsv() {
        $file = $this->request->getFile('file');
        $userId = $this->request->getPost('userId');

        if (!$file->isValid() || $file->getExtension() !== 'csv') {
            return $this->fail('Invalid CSV file', 400);
        }

        $csv = array_map('str_getcsv', file($file->getTempName()));
        $header = array_shift($csv); 

        $count = 0;
        foreach ($csv as $row) {
            // Expected: Date, AM In, AM Out, PM In, PM Out, Remarks
            if (count($row) < 2) continue;

            $date = date('Y-m-d', strtotime($row[0])); // Col 0: Date
            $remarks = $row[5] ?? ''; // Col 5: Remarks

            // Function to insert log
            $insertLog = function($tIn, $tOut, $isPm = false) use ($userId, $date, $remarks, &$count) {
                if (empty($tIn) || empty($tOut)) return;

                $tsIn = strtotime("$date $tIn");
                $tsOut = strtotime("$date $tOut");

                // Auto-convert 12-hour format for PM column if needed
                if ($isPm) {
                    if (date('H', $tsIn) < 12) $tsIn += 12 * 3600;
                    if (date('H', $tsOut) < 12) $tsOut += 12 * 3600;
                }

                $timeIn = date('Y-m-d H:i:s', $tsIn);
                $timeOut = date('Y-m-d H:i:s', $tsOut);

                // Lateness Logic
                $h = (int)date('H', $tsIn);
                $m = (int)date('i', $tsIn);
                $isLate = false;
                if ($h < 12) {
                     if ($h > 8 || ($h == 8 && $m >= 1)) $isLate = true;
                } else {
                     if ($h > 13 || ($h == 13 && $m >= 1)) $isLate = true;
                }

                // Check if record exists
                $existing = $this->db->table('attendance')
                               ->where('user_id', $userId)
                               ->where('date', $date)
                               ->where('time_in', $timeIn)
                               ->get()
                               ->getRowArray();

                if ($existing) {
                    // Update if existing record has no Time Out but CSV does
                    if ($existing['time_out'] == null && !empty($tsOut)) {
                        $this->db->table('attendance')->where('id', $existing['id'])->update([
                            'time_out' => $timeOut,
                            'remarks' => $remarks
                        ]);
                        $count++;
                    }
                } else {
                    $this->db->table('attendance')->insert([
                        'user_id' => $userId,
                        'date' => $date,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'status' => $isLate ? 'LATE' : 'PRESENT',
                        'remarks' => $remarks
                    ]);
                    $count++;
                }
            };
            
            // Process AM (Col 1 & 2)
            $insertLog($row[1] ?? null, $row[2] ?? null, false);
            
            // Process PM (Col 3 & 4)
            $insertLog($row[3] ?? null, $row[4] ?? null, true);
        }

        $this->updateTotalHours($userId);
        return $this->respond(['message' => "$count records imported successfully"]);
    }

    // GET: Fetch Logs for User
    public function logs($userId) {
        $logs = $this->db->table('attendance')
                         ->where('user_id', $userId)
                         ->orderBy('date', 'DESC')
                         ->get()
                         ->getResultArray();
        return $this->respond($logs);
    }

    // Helper: Recalculate Total Hours
    private function updateTotalHours($userId) {
        $logs = $this->db->table('attendance')
                         ->where('user_id', $userId)
                         ->where('time_out !=', null)
                         ->get()
                         ->getResultArray();
        
        $total = 0;
        foreach ($logs as $log) {
            $t1 = strtotime($log['time_in']);
            $t2 = strtotime($log['time_out']);
            $hours = round(abs($t2 - $t1) / 3600, 2);
            $total += $hours;
        }

        $this->db->table('users')->where('id', $userId)->update(['hours_rendered' => $total]);
    }

    // DELETE: Reset All Data (Except Users)
    public function resetData() {
        $this->db->table('attendance')->truncate();
        $this->db->table('users')->update(['hours_rendered' => 0]);
        return $this->respond(['message' => 'All attendance logs deleted and hours reset.']);
    }
}