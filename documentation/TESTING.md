# MediFlow Testing Guide

## Automated checks included during packaging

- PHP syntax lint for every PHP file
- JavaScript syntax check with Node.js
- Route-to-page file consistency check
- Required-file and writable-folder verification
- SQL package structure check: 27 tables, three views and marker-delimited setup statements
- Static database-call scan covering PDO query/prepare/execute usage
- Literal INSERT/UPDATE column validation against the schema
- Route-reference consistency check
- HTTP smoke test: `index.php` redirects to setup before installation and `setup.php` renders successfully
- Failed-install safety test: no runtime config or setup lock is left behind when database connection is unavailable

## Required XAMPP integration tests

Run these after installation because they require a live MySQL/MariaDB server.

### Installer

- Fresh database installs successfully.
- Existing occupied database requires explicit replacement confirmation.
- Wrong database credentials show a safe error and do not create `config/config.php` or `storage/setup.lock`.
- Successful setup creates both runtime files and blocks reinstallation.
- Demo-data mode creates the displayed role accounts.

### Authentication

- Correct credentials log in.
- Incorrect credentials remain rejected.
- Disabled accounts cannot log in.
- Each role sees only authorized navigation and pages.
- Session timeout returns to login.

### Patient management

- New patient receives a unique code.
- Duplicate phone/NID/name+DOB displays a warning.
- Emergency quick registration works with approximate age.
- Allergies save and appear in consultation.

### Appointments

- Past dates are rejected.
- Unavailable dates have no slots.
- Existing bookings disable the slot.
- Two simultaneous requests cannot book the same doctor slot.
- Patient cannot hold two appointments at the same time.
- Inactive doctors/patients cannot be booked.

### Queue

- Check-in generates one token.
- Rechecking the same appointment does not create a duplicate token.
- Consultation cannot start before check-in.
- No-show and completed states update correctly.

### Consultation and prescription

- Draft saves without completing the appointment.
- Completion updates appointment and queue.
- Medicine rows save correctly.
- Allergy conflict requires an override reason.
- Editing a prescription creates a revision snapshot.
- Follow-up and laboratory request records are created.

### Laboratory

- Status progression saves.
- Completed status requires a result or uploaded report.
- Unsupported files and files over 5 MB are rejected.
- Patient can download only their own report.

### Billing

- Consultation invoice is created with appointment.
- Lab tests add invoice items once.
- Discount cannot exceed subtotal.
- Payment cannot exceed due amount.
- Full and partial payment statuses calculate correctly.
- Invoice and payment print views match database amounts.

### Security

- SQL injection strings remain data, not SQL.
- CSRF-free POST requests fail.
- Patient cannot change another patient ID in a URL to see protected data.
- PHP files uploaded as reports are rejected.
- `config/config.php`, SQL files and setup lock cannot be downloaded through Apache.

## Browser testing

Test current Chrome, Edge and Firefox at desktop, tablet and mobile widths. Also test reduced-motion mode.
