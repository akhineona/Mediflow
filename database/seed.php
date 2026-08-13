<?php

declare(strict_types=1);

function seed_demo_data(PDO $pdo): array
{
    $roles = [];
    foreach ($pdo->query('SELECT role_id, role_name FROM roles') as $row) {
        $roles[$row['role_name']] = (int) $row['role_id'];
    }

    $accounts = [
        ['Administrator', 'admin', 'admin@mediflow.local', 'Admin@123', 'Admin'],
        ['Reception Desk', 'reception', 'reception@mediflow.local', 'Reception@123', 'Receptionist'],
        ['Dr. Sarah Ahmed', 'doctor', 'doctor@mediflow.local', 'Doctor@123', 'Doctor'],
        ['Laboratory Operator', 'lab', 'lab@mediflow.local', 'Lab@123', 'Lab Operator'],
        ['Demo Patient', 'patient', 'patient@mediflow.local', 'Patient@123', 'Patient'],
    ];

    $userIds = [];
    $stmt = $pdo->prepare('INSERT INTO users (role_id, full_name, username, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'Active\')');
    foreach ($accounts as [$name, $username, $email, $password, $role]) {
        $stmt->execute([$roles[$role], $name, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userIds[$role] = (int) $pdo->lastInsertId();
    }

    $pdo->exec("INSERT INTO departments (department_code, department_name, description) VALUES
        ('MED', 'General Medicine', 'Primary care and general medical consultation'),
        ('CAR', 'Cardiology', 'Heart and cardiovascular care'),
        ('PED', 'Pediatrics', 'Medical care for children'),
        ('DER', 'Dermatology', 'Skin, hair and nail care'),
        ('ORT', 'Orthopedics', 'Bone, joint and musculoskeletal care')");

    $deptId = (int) $pdo->query("SELECT department_id FROM departments WHERE department_code = 'MED'")->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO doctors (user_id, department_id, doctor_code, specialization, qualification, consultation_fee, average_consultation_minutes, room_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userIds['Doctor'], $deptId, 'DOC-2026-0001', 'Internal Medicine', 'MBBS, FCPS (Medicine)', 800, 15, 'Room 201']);
    $doctorId = (int) $pdo->lastInsertId();

    $schedule = $pdo->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, maximum_patients, room_number) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ([0, 1, 2, 3, 4, 5, 6] as $day) {
        $schedule->execute([$doctorId, $day, '09:00:00', '13:00:00', 15, 16, 'Room 201']);
    }

    $stmt = $pdo->prepare('INSERT INTO patients (user_id, patient_code, full_name, date_of_birth, gender, blood_group, phone, email, address, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userIds['Patient'], 'PAT-2026-0001', 'Demo Patient', '2001-05-14', 'Other', 'B+', '01700000000', 'patient@mediflow.local', 'Sylhet, Bangladesh', 'Demo Guardian', 'Parent', '01800000000', $userIds['Administrator']]);
    $patientId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO allergies (allergy_name, allergy_type, description) VALUES
        ('Penicillin', 'Drug', 'Potential allergy to penicillin-class antibiotics'),
        ('NSAID', 'Drug', 'Potential sensitivity to non-steroidal anti-inflammatory drugs'),
        ('Peanut', 'Food', 'Peanut allergy'),
        ('Dust', 'Environmental', 'Dust sensitivity')");

    $penicillinId = (int) $pdo->query("SELECT allergy_id FROM allergies WHERE allergy_name = 'Penicillin'")->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO patient_allergies (patient_id, allergy_id, severity, reaction, notes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$patientId, $penicillinId, 'High', 'Rash and breathing difficulty', 'Demo safety alert']);

    $pdo->exec("INSERT INTO medicines (generic_name, brand_name, strength, dosage_form, manufacturer) VALUES
        ('Paracetamol', 'Napa', '500 mg', 'Tablet', 'Beximco'),
        ('Omeprazole', 'Seclo', '20 mg', 'Capsule', 'Square'),
        ('Amoxicillin', 'Moxacil', '500 mg', 'Capsule', 'Square'),
        ('Azithromycin', 'Zimax', '500 mg', 'Tablet', 'Square'),
        ('Cetirizine', 'Alatrol', '10 mg', 'Tablet', 'Square')");

    $amoxicillinId = (int) $pdo->query("SELECT medicine_id FROM medicines WHERE generic_name = 'Amoxicillin'")->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO medicine_allergy_tags (medicine_id, allergy_id, warning_message) VALUES (?, ?, ?)');
    $stmt->execute([$amoxicillinId, $penicillinId, 'Amoxicillin belongs to the penicillin family and may trigger a serious reaction.']);

    $pdo->exec("INSERT INTO lab_tests (test_code, test_name, category, price, normal_range, preparation_instruction) VALUES
        ('CBC', 'Complete Blood Count', 'Hematology', 500, 'Varies by parameter', 'No special preparation'),
        ('FBS', 'Fasting Blood Sugar', 'Biochemistry', 300, '70-100 mg/dL', 'Fast for 8-10 hours'),
        ('URINE-RE', 'Urine Routine Examination', 'Pathology', 250, 'Standard ranges', 'Provide a clean-catch sample'),
        ('ECG', 'Electrocardiogram', 'Cardiology', 700, 'Clinical interpretation', 'Avoid heavy exercise immediately before test'),
        ('XR-CHEST', 'Chest X-Ray', 'Radiology', 900, 'Clinical interpretation', 'Remove metal objects')");

    $settings = [
        ['hospital_name', 'MediFlow Demo Hospital', 'Displayed hospital name'],
        ['hospital_phone', '+880 1700-000000', 'Hospital contact number'],
        ['hospital_address', 'Sylhet, Bangladesh', 'Hospital address'],
        ['currency_symbol', '৳', 'Currency symbol'],
        ['appointment_cancellation_hours', '4', 'Minimum hours before cancellation'],
        ['queue_refresh_seconds', '30', 'Queue refresh interval'],
    ];
    $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, description, updated_by) VALUES (?, ?, ?, ?)');
    foreach ($settings as $setting) {
        $stmt->execute([$setting[0], $setting[1], $setting[2], $userIds['Administrator']]);
    }


    // Operational sample data so dashboards, reports and print pages are useful immediately.
    $extraPatients = [
        ['PAT-2026-0002', 'Amina Rahman', '1998-09-21', 'Female', 'A+', '01710000001'],
        ['PAT-2026-0003', 'Mahmud Hasan', '1987-02-11', 'Male', 'O+', '01710000002'],
        ['PAT-2026-0004', 'Nusrat Jahan', '2010-12-03', 'Female', 'AB+', '01710000003'],
    ];
    $extraIds = [];
    $stmt = $pdo->prepare('INSERT INTO patients (patient_code, full_name, date_of_birth, gender, blood_group, phone, registration_type, registered_by) VALUES (?, ?, ?, ?, ?, ?, \'Normal\', ?)');
    foreach ($extraPatients as $row) {
        $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $userIds['Receptionist']]);
        $extraIds[] = (int) $pdo->lastInsertId();
    }

    $today = date('Y-m-d');
    $appointments = [
        [$patientId, '09:00:00', '09:15:00', 'Completed', 'Follow-up for fever'],
        [$extraIds[0], '09:15:00', '09:30:00', 'Checked In', 'Persistent headache'],
        [$extraIds[1], '09:30:00', '09:45:00', 'Confirmed', 'Routine medical consultation'],
        [$extraIds[2], '10:00:00', '10:15:00', 'Pending', 'Skin irritation'],
    ];
    $appointmentIds = [];
    $invoiceIds = [];
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, reason, booking_source, appointment_type, status, booked_by) VALUES (?, ?, ?, ?, ?, ?, 'Reception', 'Regular', ?, ?)");
    foreach ($appointments as $row) {
        $stmt->execute([$row[0], $doctorId, $today, $row[1], $row[2], $row[4], $row[3], $userIds['Receptionist']]);
        $appointmentId = (int) $pdo->lastInsertId();
        $appointmentIds[] = $appointmentId;
        $number = 'APT-' . date('Ymd') . '-' . str_pad((string) $appointmentId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE appointments SET appointment_number = ? WHERE appointment_id = ?')->execute([$number, $appointmentId]);

        $pdo->prepare('INSERT INTO invoices (patient_id, appointment_id, subtotal, total_amount, due_amount, payment_status, created_by) VALUES (?, ?, 800, 800, 800, \'Unpaid\', ?)')->execute([$row[0], $appointmentId, $userIds['Receptionist']]);
        $invoiceId = (int) $pdo->lastInsertId();
        $invoiceIds[] = $invoiceId;
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string) $invoiceId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE invoices SET invoice_number = ? WHERE invoice_id = ?')->execute([$invoiceNumber, $invoiceId]);
        $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, reference_id, description, quantity, unit_price, line_total) VALUES (?, 'Consultation', ?, 'Doctor consultation fee', 1, 800, 800)")->execute([$invoiceId, $doctorId]);
    }

    $pdo->prepare("INSERT INTO queue_tokens (appointment_id, token_number, check_in_time, queue_position, queue_status) VALUES (?, ?, NOW(), 1, 'Waiting')")->execute([$appointmentIds[1], 'D' . $doctorId . '-01']);

    $pdo->prepare("INSERT INTO consultations (appointment_id, patient_id, doctor_id, chief_complaint, symptoms, diagnosis, blood_pressure, temperature, weight, pulse_rate, oxygen_saturation, doctor_notes, consultation_date, consultation_status) VALUES (?, ?, ?, 'Fever and weakness', 'Low-grade fever for two days', 'Viral fever', '118/78', 37.7, 62.5, 78, 98, 'Hydration and rest advised', NOW(), 'Completed')")->execute([$appointmentIds[0], $patientId, $doctorId]);
    $consultationId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO prescriptions (consultation_id, patient_id, doctor_id, general_advice, version_number, status, issued_at) VALUES (?, ?, ?, 'Drink water, rest and return if symptoms worsen.', 1, 'Active', NOW())")->execute([$consultationId, $patientId, $doctorId]);
    $prescriptionId = (int) $pdo->lastInsertId();
    $rxNumber = 'RX-' . date('Ymd') . '-' . str_pad((string) $prescriptionId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE prescriptions SET prescription_number = ? WHERE prescription_id = ?')->execute([$rxNumber, $prescriptionId]);
    $paracetamolId = (int) $pdo->query("SELECT medicine_id FROM medicines WHERE generic_name = 'Paracetamol' LIMIT 1")->fetchColumn();
    $pdo->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, duration, timing, instructions) VALUES (?, ?, '1 tablet', 'Three times daily', '3 days', 'After meal', 'Use only when fever is present')")->execute([$prescriptionId, $paracetamolId]);

    $cbcId = (int) $pdo->query("SELECT lab_test_id FROM lab_tests WHERE test_code = 'CBC'")->fetchColumn();
    $pdo->prepare("INSERT INTO lab_requests (consultation_id, patient_id, doctor_id, priority, status, requested_at) VALUES (?, ?, ?, 'Normal', 'Completed', NOW())")->execute([$consultationId, $patientId, $doctorId]);
    $labRequestId = (int) $pdo->lastInsertId();
    $labNumber = 'LAB-' . date('Ymd') . '-' . str_pad((string) $labRequestId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE lab_requests SET request_number = ? WHERE lab_request_id = ?')->execute([$labNumber, $labRequestId]);
    $pdo->prepare("INSERT INTO lab_request_items (lab_request_id, lab_test_id, result_summary, result_value, sample_collected_at, completed_at, processed_by, status) VALUES (?, ?, 'Counts are within expected demo ranges.', 'Normal', NOW(), NOW(), ?, 'Completed')")->execute([$labRequestId, $cbcId, $userIds['Lab Operator']]);
    $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, reference_id, description, quantity, unit_price, line_total) VALUES (?, 'Lab Test', ?, 'Complete Blood Count', 1, 500, 500)")->execute([$invoiceIds[0], $cbcId]);
    $pdo->prepare("UPDATE invoices SET subtotal = 1300, total_amount = 1300, paid_amount = 1300, due_amount = 0, payment_status = 'Paid' WHERE invoice_id = ?")->execute([$invoiceIds[0]]);
    $pdo->prepare("INSERT INTO payments (invoice_id, amount, payment_method, received_by, payment_date, payment_status, notes) VALUES (?, 1300, 'Cash', ?, NOW(), 'Completed', 'Demo payment')")->execute([$invoiceIds[0], $userIds['Receptionist']]);
    $paymentId = (int) $pdo->lastInsertId();
    $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad((string) $paymentId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE payments SET payment_number = ? WHERE payment_id = ?')->execute([$paymentNumber, $paymentId]);

    $pdo->prepare("INSERT INTO follow_ups (consultation_id, patient_id, doctor_id, recommended_date, reason, status) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Review recovery if fever persists', 'Recommended')")->execute([$consultationId, $patientId, $doctorId]);
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_page, related_record_id) VALUES (?, 'Welcome to MediFlow', 'Your demo patient portal is ready.', 'Info', 'dashboard', ?)")->execute([$userIds['Patient'], $patientId]);

    return [
        'accounts' => array_map(static fn(array $a): array => [
            'role' => $a[4], 'email' => $a[2], 'password' => $a[3]
        ], $accounts),
    ];
}
