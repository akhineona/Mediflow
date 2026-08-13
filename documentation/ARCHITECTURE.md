# MediFlow Architecture

## Architecture style

MediFlow uses a small MVC-inspired, three-layer design suitable for a DBMS laboratory project.

### Presentation layer

- HTML pages and reusable PHP partials
- `assets/css/app.css` design system
- `assets/js/app.js` interactions, modals, appointment slots and allergy checks

### Application layer

- `index.php` front controller and role-based route table
- Page controllers inside `pages/`
- Shared authentication, validation, CSRF, notifications and audit helpers inside `app/`
- JSON read APIs in `api.php`
- Authorized file delivery in `download.php`

### Data layer

- MySQL/MariaDB schema in `database/schema.sql`
- 27 normalized tables
- Three reporting views
- Foreign keys, unique constraints and indexed lookup columns
- PDO prepared statements and application transactions

## Main workflow

Patient registration → doctor schedule → appointment → invoice → check-in → queue token → consultation → prescription/lab request → lab report → payment → follow-up.

## Main relationships

- Role 1:M User
- User 1:0..1 Patient or Doctor
- Department 1:M Doctor
- Doctor 1:M Schedule and Appointment
- Patient 1:M Appointment
- Appointment 1:0..1 Queue Token and Consultation
- Consultation 1:0..1 Prescription and 1:M Lab Request/Follow-up
- Prescription 1:M Prescription Item
- Lab Request 1:M Lab Request Item
- Invoice 1:M Invoice Item and Payment
- Patient M:N Allergy
- Medicine M:N Allergy through safety tags

## Authorization model

- Patient: own appointments, prescriptions, laboratory reports, invoices and profile
- Doctor: assigned patient history, own schedules, consultations and prescriptions
- Receptionist: patient registration, appointments, check-in and billing
- Lab Operator: test workflow and report upload
- Admin: all configuration, catalogs, users, reports and audit logs

## File structure

- `app/`: shared bootstrap, PDO, helpers and authentication
- `pages/`: complete role-facing modules
- `partials/`: navigation and layout
- `assets/`: interface styling and JavaScript
- `database/`: schema and demo seeding
- `uploads/`: protected report storage
- `storage/`: setup lock and application logs
- `documentation/`: installation, architecture, testing and user guidance
