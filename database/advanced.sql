-- Optional DBMS demonstration objects.
-- Import manually in phpMyAdmin after the normal setup if your instructor
-- specifically requires stored procedures. The application does not depend on them.

DROP PROCEDURE IF EXISTS sp_doctor_daily_summary;
DELIMITER $$
CREATE PROCEDURE sp_doctor_daily_summary(IN p_doctor_id INT, IN p_date DATE)
BEGIN
    SELECT
        COUNT(*) AS total_appointments,
        SUM(status = 'Completed') AS completed_consultations,
        SUM(status = 'No-show') AS no_shows,
        SUM(status IN ('Checked In', 'Waiting', 'In Consultation')) AS active_queue
    FROM appointments
    WHERE doctor_id = p_doctor_id AND appointment_date = p_date;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_patient_balance;
DELIMITER $$
CREATE PROCEDURE sp_patient_balance(IN p_patient_id INT)
BEGIN
    SELECT
        patient_id,
        COALESCE(SUM(total_amount), 0) AS total_billed,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM invoices
    WHERE patient_id = p_patient_id AND status = 'Active'
    GROUP BY patient_id;
END$$
DELIMITER ;
