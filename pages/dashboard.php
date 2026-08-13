<?php
$user = current_user();
$role = $user['role_name'];
$today = date('Y-m-d');
$cards = [];
$recent = [];
$sectionTitle = 'Today at a glance';

if ($role === 'Admin') {
    $cards = [
        ['Patients', query_value("SELECT COUNT(*) FROM patients WHERE status='Active'"), '♙', '#2563eb', 'Registered active patients'],
        ['Appointments today', query_value('SELECT COUNT(*) FROM appointments WHERE appointment_date=?', [$today]), '◷', '#0f766e', 'All appointment statuses'],
        ['Doctors active', query_value("SELECT COUNT(*) FROM doctors WHERE status='Active'"), '⚕', '#7c3aed', 'Available clinician profiles'],
        ['Revenue today', money(query_value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=? AND payment_status='Completed'", [$today])), '৳', '#15803d', 'Completed payments'],
        ['Pending lab items', query_value("SELECT COUNT(*) FROM lab_request_items WHERE status NOT IN ('Completed','Cancelled')"), '⚗', '#d97706', 'Awaiting completion'],
    ];
    $stmt = db()->prepare("SELECT a.appointment_number,a.start_time,a.status,p.full_name patient_name,u.full_name doctor_name FROM appointments a JOIN patients p ON p.patient_id=a.patient_id JOIN doctors d ON d.doctor_id=a.doctor_id JOIN users u ON u.user_id=d.user_id WHERE a.appointment_date=? ORDER BY a.start_time LIMIT 8");
    $stmt->execute([$today]); $recent = $stmt->fetchAll();
} elseif ($role === 'Receptionist') {
    $cards = [
        ['Bookings today', query_value('SELECT COUNT(*) FROM appointments WHERE appointment_date=?', [$today]), '◷', '#2563eb', 'Appointments scheduled'],
        ['Checked in', query_value("SELECT COUNT(*) FROM appointments WHERE appointment_date=? AND status IN ('Checked In','Waiting','In Consultation')", [$today]), '✓', '#0f766e', 'Patients on site'],
        ['Waiting', query_value("SELECT COUNT(*) FROM queue_tokens q JOIN appointments a ON a.appointment_id=q.appointment_id WHERE a.appointment_date=? AND q.queue_status='Waiting'", [$today]), '≡', '#d97706', 'Current queue'],
        ['Payments today', money(query_value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=? AND payment_status='Completed'", [$today])), '৳', '#15803d', 'Recorded collections'],
    ];
    $stmt=db()->prepare("SELECT a.appointment_number,a.start_time,a.status,p.full_name patient_name,u.full_name doctor_name FROM appointments a JOIN patients p ON p.patient_id=a.patient_id JOIN doctors d ON d.doctor_id=a.doctor_id JOIN users u ON u.user_id=d.user_id WHERE a.appointment_date=? ORDER BY a.start_time LIMIT 10");$stmt->execute([$today]);$recent=$stmt->fetchAll();
} elseif ($role === 'Doctor') {
    $doctor = doctor_for_current_user();
    $doctorId = (int)($doctor['doctor_id']??0);
    $cards = [
        ['Appointments today', query_value('SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=?', [$doctorId,$today]), '◷', '#2563eb', 'Assigned appointments'],
        ['Patients waiting', query_value("SELECT COUNT(*) FROM queue_tokens q JOIN appointments a ON a.appointment_id=q.appointment_id WHERE a.doctor_id=? AND a.appointment_date=? AND q.queue_status='Waiting'", [$doctorId,$today]), '≡', '#d97706', 'Checked in queue'],
        ['Completed today', query_value("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND status='Completed'", [$doctorId,$today]), '✓', '#15803d', 'Consultations completed'],
        ['Follow-ups due', query_value("SELECT COUNT(*) FROM follow_ups WHERE doctor_id=? AND recommended_date BETWEEN ? AND DATE_ADD(?,INTERVAL 7 DAY) AND status='Recommended'", [$doctorId,$today,$today]), '↻', '#7c3aed', 'Next seven days'],
    ];
    $stmt=db()->prepare("SELECT a.appointment_number,a.start_time,a.status,p.full_name patient_name,u.full_name doctor_name FROM appointments a JOIN patients p ON p.patient_id=a.patient_id JOIN doctors d ON d.doctor_id=a.doctor_id JOIN users u ON u.user_id=d.user_id WHERE a.doctor_id=? AND a.appointment_date=? ORDER BY a.start_time LIMIT 10");$stmt->execute([$doctorId,$today]);$recent=$stmt->fetchAll();
} elseif ($role === 'Lab Operator') {
    $cards = [
        ['New requests', query_value("SELECT COUNT(*) FROM lab_request_items WHERE status='Requested'"), '⚗', '#2563eb', 'Awaiting sample collection'],
        ['Samples collected', query_value("SELECT COUNT(*) FROM lab_request_items WHERE status='Sample Collected'"), '◉', '#0f766e', 'Ready to process'],
        ['Processing', query_value("SELECT COUNT(*) FROM lab_request_items WHERE status='Processing'"), '⌛', '#d97706', 'Tests in progress'],
        ['Completed today', query_value("SELECT COUNT(*) FROM lab_request_items WHERE DATE(completed_at)=? AND status='Completed'", [$today]), '✓', '#15803d', 'Reports completed'],
    ];
    $stmt=db()->query("SELECT lr.request_number,p.full_name patient_name,lt.test_name,lri.status,lr.priority FROM lab_request_items lri JOIN lab_requests lr ON lr.lab_request_id=lri.lab_request_id JOIN patients p ON p.patient_id=lr.patient_id JOIN lab_tests lt ON lt.lab_test_id=lri.lab_test_id WHERE lri.status NOT IN ('Completed','Cancelled') ORDER BY FIELD(lr.priority,'Urgent','High','Normal'),lr.requested_at LIMIT 10");$recent=$stmt->fetchAll();
    $sectionTitle='Laboratory work queue';
} else {
    $patient = patient_for_current_user();
    $patientId = (int)($patient['patient_id']??0);
    $nextAppointment = db()->prepare("SELECT a.*,u.full_name doctor_name FROM appointments a JOIN doctors d ON d.doctor_id=a.doctor_id JOIN users u ON u.user_id=d.user_id WHERE a.patient_id=? AND CONCAT(a.appointment_date,' ',a.start_time)>=NOW() AND a.status NOT IN ('Cancelled','No-show','Completed') ORDER BY a.appointment_date,a.start_time LIMIT 1");$nextAppointment->execute([$patientId]);$next=$nextAppointment->fetch();
    $cards = [
        ['Next appointment', $next ? format_date($next['appointment_date'],'d M').' '.format_time($next['start_time']) : 'None', '◷', '#2563eb', $next ? $next['doctor_name'] : 'Book when you need care'],
        ['Pending bills', money(query_value("SELECT COALESCE(SUM(due_amount),0) FROM invoices WHERE patient_id=? AND status='Active'", [$patientId])), '৳', '#d97706', 'Outstanding balance'],
        ['Prescriptions', query_value('SELECT COUNT(*) FROM prescriptions WHERE patient_id=?',[$patientId]), '✎', '#0f766e', 'Available records'],
        ['Follow-ups', query_value("SELECT COUNT(*) FROM follow_ups WHERE patient_id=? AND status='Recommended' AND recommended_date>=?",[$patientId,$today]), '↻', '#7c3aed', 'Upcoming recommendations'],
    ];
    $stmt=db()->prepare("SELECT a.appointment_number,a.start_time,a.status,a.appointment_date,p.full_name patient_name,u.full_name doctor_name FROM appointments a JOIN patients p ON p.patient_id=a.patient_id JOIN doctors d ON d.doctor_id=a.doctor_id JOIN users u ON u.user_id=d.user_id WHERE a.patient_id=? ORDER BY a.appointment_date DESC,a.start_time DESC LIMIT 8");$stmt->execute([$patientId]);$recent=$stmt->fetchAll();
    $sectionTitle='Recent appointments';
}
?>
<div class="page-header"><div><h2>Welcome, <?=e(explode(' ',$user['full_name'])[0])?></h2><p><?=e(date('l, d F Y'))?> — here is your current MediFlow overview.</p></div><div class="actions"><?php if(in_array($role,['Patient','Receptionist','Admin'],true)):?><a class="btn btn-primary" href="<?=e(route_url('appointments',['new'=>1]))?>">＋ Book appointment</a><?php endif;?></div></div>
<div class="grid <?=count($cards)>=5?'grid-5':'grid-4'?> mb-2"><?php foreach($cards as $i=>$card):?><div class="card stat-card" style="--stat-color:<?=e($card[3])?>;animation-delay:<?=$i*50?>ms"><div><div class="stat-label"><?=e($card[0])?></div><div class="stat-value" <?=is_numeric($card[1])?'data-number="'.e((string)$card[1]).'"':''?>><?=e((string)$card[1])?></div><div class="stat-note"><?=e($card[4])?></div></div><div class="stat-icon"><?=e($card[2])?></div></div><?php endforeach;?></div>
<div class="grid grid-3">
<div class="card" style="grid-column:span 2"><div class="card-header"><div><h3><?=e($sectionTitle)?></h3><p>Latest operational information.</p></div><a class="btn btn-light btn-sm" href="<?=e($role==='Lab Operator'?route_url('labs'):route_url('appointments'))?>">View all</a></div>
<?php if($recent):?><div class="table-wrap"><table class="data-table"><thead><tr><?php if($role==='Lab Operator'):?><th>Request</th><th>Patient</th><th>Test</th><th>Priority</th><th>Status</th><?php else:?><th>Appointment</th><th>Date/Time</th><th>Patient</th><th>Doctor</th><th>Status</th><?php endif;?></tr></thead><tbody><?php foreach($recent as $row):?><tr><?php if($role==='Lab Operator'):?><td class="table-title"><?=e($row['request_number'])?></td><td><?=e($row['patient_name'])?></td><td><?=e($row['test_name'])?></td><td><?=e($row['priority'])?></td><td><span class="badge badge-<?=status_class($row['status'])?>"><?=e($row['status'])?></span></td><?php else:?><td class="table-title"><?=e($row['appointment_number'])?></td><td><?=isset($row['appointment_date'])?format_date($row['appointment_date'],'d M').' ':''?><?=format_time($row['start_time'])?></td><td><?=e($row['patient_name'])?></td><td><?=e($row['doctor_name'])?></td><td><span class="badge badge-<?=status_class($row['status'])?>"><?=e($row['status'])?></span></td><?php endif;?></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="empty-state"><div class="empty-icon">◷</div><p>No records to display yet.</p></div><?php endif;?></div>
<div class="card"><div class="card-header"><div><h3>Quick actions</h3><p>Common tasks for your role.</p></div></div><div style="display:grid;gap:9px"><?php if($role==='Admin'):?><a class="btn btn-light" href="<?=e(route_url('doctors'))?>">⚕ Manage doctors</a><a class="btn btn-light" href="<?=e(route_url('reports'))?>">◫ Open reports</a><a class="btn btn-light" href="<?=e(route_url('users'))?>">♧ Manage users</a><?php elseif($role==='Receptionist'):?><a class="btn btn-light" href="<?=e(route_url('patients',['new'=>1]))?>">♙ Register patient</a><a class="btn btn-light" href="<?=e(route_url('queue'))?>">≡ Open queue</a><a class="btn btn-light" href="<?=e(route_url('billing'))?>">৳ Record payment</a><?php elseif($role==='Doctor'):?><a class="btn btn-light" href="<?=e(route_url('queue'))?>">≡ View queue</a><a class="btn btn-light" href="<?=e(route_url('consultations'))?>">✚ Consultations</a><a class="btn btn-light" href="<?=e(route_url('schedules'))?>">▤ My schedule</a><?php elseif($role==='Lab Operator'):?><a class="btn btn-light" href="<?=e(route_url('labs'))?>">⚗ Process requests</a><a class="btn btn-light" href="<?=e(route_url('notifications'))?>">◉ Notifications</a><?php else:?><a class="btn btn-light" href="<?=e(route_url('appointments',['new'=>1]))?>">◷ Book appointment</a><a class="btn btn-light" href="<?=e(route_url('prescriptions'))?>">✎ View prescriptions</a><a class="btn btn-light" href="<?=e(route_url('labs'))?>">⚗ View lab reports</a><?php endif;?></div></div>
</div>
