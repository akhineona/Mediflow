<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_login();

$action = get_string('action');

try {
    if ($action === 'doctors') {
        $departmentId = (int) ($_GET['department_id'] ?? 0);
        $sql = "SELECT d.doctor_id, u.full_name, d.specialization, d.consultation_fee, d.room_number
                FROM doctors d JOIN users u ON u.user_id = d.user_id
                WHERE d.status = 'Active' AND u.account_status = 'Active'";
        $params = [];
        if ($departmentId > 0) {
            $sql .= ' AND d.department_id = ?';
            $params[] = $departmentId;
        }
        $sql .= ' ORDER BY u.full_name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['ok' => true, 'doctors' => $stmt->fetchAll()]);
    }

    if ($action === 'slots') {
        $doctorId = (int) ($_GET['doctor_id'] ?? 0);
        $date = get_string('date');
        if ($doctorId < 1 || !validate_date($date)) {
            json_response(['ok' => false, 'message' => 'Select a doctor and a valid date.'], 422);
        }
        if ($date < date('Y-m-d')) {
            json_response(['ok' => true, 'slots' => []]);
        }
        $day = (int) date('w', strtotime($date));
        $exceptionStmt = db()->prepare('SELECT * FROM schedule_exceptions WHERE doctor_id = ? AND exception_date = ? LIMIT 1');
        $exceptionStmt->execute([$doctorId, $date]);
        $exception = $exceptionStmt->fetch();
        if ($exception && $exception['exception_type'] === 'Unavailable') {
            json_response(['ok' => true, 'slots' => [], 'message' => $exception['reason'] ?: 'Doctor unavailable on this date.']);
        }

        $stmt = db()->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 'Active' ORDER BY start_time");
        $stmt->execute([$doctorId, $day]);
        $schedules = $stmt->fetchAll();
        if ($exception && $exception['exception_type'] === 'Alternative Hours') {
            if (!$schedules || !$exception['alternative_start_time'] || !$exception['alternative_end_time'] || $exception['alternative_start_time'] >= $exception['alternative_end_time']) {
                json_response(['ok' => true, 'slots' => [], 'message' => 'Alternative hours are not configured correctly for this date.']);
            }
            $alternative = $schedules[0];
            $alternative['start_time'] = $exception['alternative_start_time'];
            $alternative['end_time'] = $exception['alternative_end_time'];
            $schedules = [$alternative];
        }
        $slots = [];
        $bookedStmt = db()->prepare("SELECT start_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('Cancelled', 'No-show')");
        $bookedStmt->execute([$doctorId, $date]);
        $booked = array_map(static fn(array $r): string => substr($r['start_time'], 0, 5), $bookedStmt->fetchAll());

        foreach ($schedules as $schedule) {
            $cursor = new DateTime($date . ' ' . $schedule['start_time']);
            $end = new DateTime($date . ' ' . $schedule['end_time']);
            $duration = max(5, (int) $schedule['slot_duration']);
            $generated = 0;
            $maximum = max(1, (int) $schedule['maximum_patients']);
            while ($cursor < $end && $generated < $maximum) {
                $slotEnd = (clone $cursor)->modify('+' . $duration . ' minutes');
                if ($slotEnd > $end) {
                    break;
                }
                $time = $cursor->format('H:i');
                $disabledPast = $date === date('Y-m-d') && $cursor <= new DateTime();
                $slots[] = [
                    'time' => $time,
                    'label' => $cursor->format('h:i A'),
                    'end_time' => $slotEnd->format('H:i'),
                    'available' => !$disabledPast && !in_array($time, $booked, true),
                    'room' => $schedule['room_number'],
                ];
                $cursor = $slotEnd;
                $generated++;
            }
        }
        json_response(['ok' => true, 'slots' => $slots]);
    }

    if ($action === 'patient-search') {
        require_roles(['Admin', 'Receptionist', 'Doctor']);
        $query = get_string('q');
        if (mb_strlen($query) < 2) {
            json_response(['ok' => true, 'patients' => []]);
        }
        $like = '%' . $query . '%';
        $stmt = db()->prepare("SELECT patient_id, patient_code, full_name, phone, date_of_birth FROM patients WHERE status = 'Active' AND (patient_code LIKE ? OR full_name LIKE ? OR phone LIKE ?) ORDER BY full_name LIMIT 15");
        $stmt->execute([$like, $like, $like]);
        json_response(['ok' => true, 'patients' => $stmt->fetchAll()]);
    }

    if ($action === 'medicine-warning') {
        require_roles(['Admin', 'Doctor']);
        $patientId = (int) ($_GET['patient_id'] ?? 0);
        $medicineId = (int) ($_GET['medicine_id'] ?? 0);
        if ($patientId < 1 || $medicineId < 1 || !can_view_patient($patientId)) {
            json_response(['ok' => false, 'message' => 'Patient access denied.'], 403);
        }
        $stmt = db()->prepare("SELECT a.allergy_name, pa.severity, mat.warning_message
            FROM medicine_allergy_tags mat
            JOIN allergies a ON a.allergy_id = mat.allergy_id
            JOIN patient_allergies pa ON pa.allergy_id = a.allergy_id
            WHERE pa.patient_id = ? AND mat.medicine_id = ?");
        $stmt->execute([$patientId, $medicineId]);
        json_response(['ok' => true, 'warnings' => $stmt->fetchAll()]);
    }

    if ($action === 'queue-status') {
        require_roles(['Admin', 'Receptionist', 'Doctor']);
        $doctorId = (int) ($_GET['doctor_id'] ?? 0);
        if (has_role('Doctor')) {
            $doctor = doctor_for_current_user();
            if (!$doctor) {
                json_response(['ok' => false, 'message' => 'Doctor profile was not found.'], 403);
            }
            $doctorId = (int) $doctor['doctor_id'];
        }
        $params = [date('Y-m-d')];
        $sql = "SELECT q.token_number, q.queue_status, q.check_in_time, a.start_time, p.full_name AS patient_name
                FROM queue_tokens q JOIN appointments a ON a.appointment_id = q.appointment_id
                JOIN patients p ON p.patient_id = a.patient_id
                WHERE a.appointment_date = ?";
        if ($doctorId > 0) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= " ORDER BY FIELD(q.queue_status, 'In Consultation', 'Waiting', 'Completed', 'No-show'), q.check_in_time";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['ok' => true, 'queue' => $stmt->fetchAll()]);
    }

    json_response(['ok' => false, 'message' => 'Unknown API action.'], 404);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => (bool) config('app.debug', false) ? $e->getMessage() : 'The request could not be completed.'], 500);
}
