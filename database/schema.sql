SET NAMES utf8mb4;
-- statement-break
SET FOREIGN_KEY_CHECKS = 0;
-- statement-break
DROP VIEW IF EXISTS view_today_appointments;
-- statement-break
DROP VIEW IF EXISTS view_outstanding_invoices;
-- statement-break
DROP VIEW IF EXISTS view_doctor_daily_queue;
-- statement-break
DROP TABLE IF EXISTS audit_logs;
-- statement-break
DROP TABLE IF EXISTS notifications;
-- statement-break
DROP TABLE IF EXISTS payments;
-- statement-break
DROP TABLE IF EXISTS invoice_items;
-- statement-break
DROP TABLE IF EXISTS invoices;
-- statement-break
DROP TABLE IF EXISTS follow_ups;
-- statement-break
DROP TABLE IF EXISTS lab_request_items;
-- statement-break
DROP TABLE IF EXISTS lab_requests;
-- statement-break
DROP TABLE IF EXISTS lab_tests;
-- statement-break
DROP TABLE IF EXISTS prescription_revisions;
-- statement-break
DROP TABLE IF EXISTS prescription_items;
-- statement-break
DROP TABLE IF EXISTS prescriptions;
-- statement-break
DROP TABLE IF EXISTS medicine_allergy_tags;
-- statement-break
DROP TABLE IF EXISTS medicines;
-- statement-break
DROP TABLE IF EXISTS consultations;
-- statement-break
DROP TABLE IF EXISTS queue_tokens;
-- statement-break
DROP TABLE IF EXISTS appointments;
-- statement-break
DROP TABLE IF EXISTS schedule_exceptions;
-- statement-break
DROP TABLE IF EXISTS doctor_schedules;
-- statement-break
DROP TABLE IF EXISTS patient_allergies;
-- statement-break
DROP TABLE IF EXISTS allergies;
-- statement-break
DROP TABLE IF EXISTS doctors;
-- statement-break
DROP TABLE IF EXISTS departments;
-- statement-break
DROP TABLE IF EXISTS patients;
-- statement-break
DROP TABLE IF EXISTS system_settings;
-- statement-break
DROP TABLE IF EXISTS users;
-- statement-break
DROP TABLE IF EXISTS roles;
-- statement-break
CREATE TABLE roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(40) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    profile_image VARCHAR(255) NULL,
    account_status VARCHAR(20) NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE system_settings (
    setting_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    description VARCHAR(255) NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE patients (
    patient_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL UNIQUE,
    patient_code VARCHAR(30) NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    date_of_birth DATE NULL,
    approximate_age SMALLINT UNSIGNED NULL,
    gender VARCHAR(20) NULL,
    blood_group VARCHAR(10) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    emergency_contact_name VARCHAR(120) NULL,
    emergency_contact_relationship VARCHAR(60) NULL,
    emergency_contact_phone VARCHAR(30) NULL,
    national_id VARCHAR(50) NULL UNIQUE,
    registration_type VARCHAR(20) NOT NULL DEFAULT 'Normal',
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    registered_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_patients_registered_by FOREIGN KEY (registered_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_patients_phone (phone),
    INDEX idx_patients_name_dob (full_name, date_of_birth)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE departments (
    department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE doctors (
    doctor_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    department_id INT UNSIGNED NOT NULL,
    doctor_code VARCHAR(30) NULL UNIQUE,
    specialization VARCHAR(120) NOT NULL,
    qualification VARCHAR(255) NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    average_consultation_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    room_number VARCHAR(30) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctors_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_doctors_department FOREIGN KEY (department_id) REFERENCES departments(department_id),
    INDEX idx_doctors_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE allergies (
    allergy_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    allergy_name VARCHAR(120) NOT NULL UNIQUE,
    allergy_type VARCHAR(60) NULL,
    description VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE patient_allergies (
    patient_allergy_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    allergy_id INT UNSIGNED NOT NULL,
    severity VARCHAR(30) NULL,
    reaction VARCHAR(255) NULL,
    notes TEXT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient_allergies_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_patient_allergies_allergy FOREIGN KEY (allergy_id) REFERENCES allergies(allergy_id),
    UNIQUE KEY uq_patient_allergy (patient_id, allergy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE doctor_schedules (
    schedule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    maximum_patients SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    room_number VARCHAR(30) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedules_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    INDEX idx_schedule_lookup (doctor_id, day_of_week, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE schedule_exceptions (
    exception_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    exception_date DATE NOT NULL,
    exception_type VARCHAR(30) NOT NULL,
    reason VARCHAR(255) NULL,
    alternative_start_time TIME NULL,
    alternative_end_time TIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_exceptions_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    UNIQUE KEY uq_doctor_exception (doctor_id, exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE appointments (
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_number VARCHAR(40) NULL UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    reason TEXT NULL,
    booking_source VARCHAR(30) NOT NULL DEFAULT 'Portal',
    appointment_type VARCHAR(30) NOT NULL DEFAULT 'Regular',
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    booked_by INT UNSIGNED NULL,
    booked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancellation_reason VARCHAR(255) NULL,
    cancelled_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_appointments_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    CONSTRAINT fk_appointments_booked_by FOREIGN KEY (booked_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_appointments_doctor_slot (doctor_id, appointment_date, start_time),
    INDEX idx_appointments_patient_date (patient_id, appointment_date),
    INDEX idx_appointments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE queue_tokens (
    token_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL UNIQUE,
    token_number VARCHAR(30) NOT NULL,
    check_in_time DATETIME NOT NULL,
    queue_position SMALLINT UNSIGNED NULL,
    queue_status VARCHAR(30) NOT NULL DEFAULT 'Waiting',
    consultation_start_time DATETIME NULL,
    consultation_end_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_queue_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    INDEX idx_queue_status (queue_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE consultations (
    consultation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    chief_complaint TEXT NULL,
    symptoms TEXT NULL,
    diagnosis TEXT NULL,
    blood_pressure VARCHAR(20) NULL,
    temperature DECIMAL(4,1) NULL,
    weight DECIMAL(6,2) NULL,
    pulse_rate SMALLINT UNSIGNED NULL,
    oxygen_saturation DECIMAL(5,2) NULL,
    doctor_notes TEXT NULL,
    consultation_date DATETIME NOT NULL,
    consultation_status VARCHAR(30) NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_consultation_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
    CONSTRAINT fk_consultation_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_consultation_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    INDEX idx_consultation_patient (patient_id, consultation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE medicines (
    medicine_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generic_name VARCHAR(120) NOT NULL,
    brand_name VARCHAR(120) NULL,
    strength VARCHAR(60) NULL,
    dosage_form VARCHAR(60) NULL,
    manufacturer VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medicine (generic_name, brand_name, strength)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE medicine_allergy_tags (
    medicine_id INT UNSIGNED NOT NULL,
    allergy_id INT UNSIGNED NOT NULL,
    warning_message VARCHAR(255) NULL,
    PRIMARY KEY (medicine_id, allergy_id),
    CONSTRAINT fk_med_allergy_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE,
    CONSTRAINT fk_med_allergy_allergy FOREIGN KEY (allergy_id) REFERENCES allergies(allergy_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE prescriptions (
    prescription_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_number VARCHAR(40) NULL UNIQUE,
    consultation_id INT UNSIGNED NOT NULL UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    general_advice TEXT NULL,
    allergy_override_reason TEXT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    issued_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prescription_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(consultation_id),
    CONSTRAINT fk_prescription_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_prescription_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE prescription_items (
    prescription_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    dosage VARCHAR(80) NULL,
    frequency VARCHAR(80) NULL,
    duration VARCHAR(80) NULL,
    timing VARCHAR(80) NULL,
    instructions VARCHAR(255) NULL,
    CONSTRAINT fk_prescription_item_prescription FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE,
    CONSTRAINT fk_prescription_item_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE prescription_revisions (
    revision_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot LONGTEXT NOT NULL,
    change_reason VARCHAR(255) NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_revision_prescription FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE,
    CONSTRAINT fk_revision_user FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE lab_tests (
    lab_test_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_code VARCHAR(30) NOT NULL UNIQUE,
    test_name VARCHAR(120) NOT NULL,
    category VARCHAR(80) NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    normal_range VARCHAR(255) NULL,
    preparation_instruction VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE lab_requests (
    lab_request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(40) NULL UNIQUE,
    consultation_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
    status VARCHAR(30) NOT NULL DEFAULT 'Requested',
    requested_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lab_request_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(consultation_id),
    CONSTRAINT fk_lab_request_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_lab_request_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    INDEX idx_lab_request_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE lab_request_items (
    request_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_request_id INT UNSIGNED NOT NULL,
    lab_test_id INT UNSIGNED NOT NULL,
    result_summary TEXT NULL,
    result_value VARCHAR(120) NULL,
    report_file_path VARCHAR(255) NULL,
    sample_collected_at DATETIME NULL,
    completed_at DATETIME NULL,
    processed_by INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Requested',
    CONSTRAINT fk_lab_item_request FOREIGN KEY (lab_request_id) REFERENCES lab_requests(lab_request_id) ON DELETE CASCADE,
    CONSTRAINT fk_lab_item_test FOREIGN KEY (lab_test_id) REFERENCES lab_tests(lab_test_id),
    CONSTRAINT fk_lab_item_user FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uq_request_test (lab_request_id, lab_test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE follow_ups (
    follow_up_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    recommended_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Recommended',
    related_appointment_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_followup_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(consultation_id),
    CONSTRAINT fk_followup_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_followup_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id),
    CONSTRAINT fk_followup_appointment FOREIGN KEY (related_appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE invoices (
    invoice_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(40) NULL UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_invoice_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    CONSTRAINT fk_invoice_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_invoice_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE invoice_items (
    invoice_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    item_type VARCHAR(40) NOT NULL,
    reference_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    INDEX idx_invoice_item_reference (item_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(40) NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    transaction_reference VARCHAR(120) NULL,
    received_by INT UNSIGNED NULL,
    payment_date DATETIME NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'Completed',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
    CONSTRAINT fk_payment_receiver FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(40) NOT NULL DEFAULT 'Info',
    related_page VARCHAR(100) NULL,
    related_record_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action_type VARCHAR(80) NOT NULL,
    table_name VARCHAR(80) NULL,
    record_id VARCHAR(80) NULL,
    old_data LONGTEXT NULL,
    new_data LONGTEXT NULL,
    ip_address VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_table_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement-break
CREATE OR REPLACE VIEW view_today_appointments AS
SELECT a.appointment_id, a.appointment_number, a.appointment_date, a.start_time, a.end_time,
       a.status, p.patient_id, p.patient_code, p.full_name AS patient_name,
       d.doctor_id, u.full_name AS doctor_name, dep.department_name
FROM appointments a
JOIN patients p ON p.patient_id = a.patient_id
JOIN doctors d ON d.doctor_id = a.doctor_id
JOIN users u ON u.user_id = d.user_id
JOIN departments dep ON dep.department_id = d.department_id
WHERE a.appointment_date = CURDATE();
-- statement-break
CREATE OR REPLACE VIEW view_outstanding_invoices AS
SELECT i.invoice_id, i.invoice_number, i.patient_id, p.patient_code, p.full_name AS patient_name,
       i.total_amount, i.paid_amount, i.due_amount, i.payment_status, i.created_at
FROM invoices i
JOIN patients p ON p.patient_id = i.patient_id
WHERE i.status = 'Active' AND i.due_amount > 0;
-- statement-break
CREATE OR REPLACE VIEW view_doctor_daily_queue AS
SELECT q.token_id, q.token_number, q.queue_status, q.check_in_time, q.consultation_start_time,
       a.appointment_id, a.appointment_date, a.start_time, a.status AS appointment_status,
       p.patient_id, p.patient_code, p.full_name AS patient_name,
       d.doctor_id, u.full_name AS doctor_name
FROM queue_tokens q
JOIN appointments a ON a.appointment_id = q.appointment_id
JOIN patients p ON p.patient_id = a.patient_id
JOIN doctors d ON d.doctor_id = a.doctor_id
JOIN users u ON u.user_id = d.user_id
WHERE a.appointment_date = CURDATE();
-- statement-break
SET FOREIGN_KEY_CHECKS = 1;
