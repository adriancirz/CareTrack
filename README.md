# CareTrack

CareTrack is a PHP and MySQL clinic information and appointment management system. It is designed as a staged learning project for XAMPP, phpMyAdmin, PDO, and Bootstrap 5.

> **Privacy and deployment notice:** This project is an educational starting point, not a certified clinical system. Use fictional information while developing. Do not put real patient data in a public GitHub repository. Before any real-world deployment, arrange a qualified security, privacy, legal, and clinical review; use HTTPS; secure the database and backups; and store uploaded patient files outside the public web root behind authorization checks.

## Features

- Staff registration, sign-in, password hashing, session protection, CSRF tokens, and role checks.
- Admin dashboard with clinic counts, quick actions, and searchable upcoming appointments.
- Patient registration, search, editing, photo upload, and soft-archiving.
- Appointment scheduling, editing, status changes, and prevention of simultaneous exact-time bookings for one clinician.
- Medical-record creation and viewing with clinician/admin access controls. Records are read-only after creation.
- Admin user management for staff activation, deactivation, and role assignment.
- Admin-only activity log viewer for important account and clinic actions.
- Responsive Bootstrap 5 layout.

## Technology

- PHP 8.2 or newer
- MySQL or MariaDB
- PDO MySQL
- PHP `mbstring` and `fileinfo` extensions
- Apache (XAMPP is suitable for local development)
- Bootstrap 5.3 and Bootstrap Icons, loaded from public CDNs

## Run locally with XAMPP

1. Install and start Apache and MySQL in XAMPP.
2. Clone or copy this project into `C:\xampp\htdocs\caretrack`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
4. Import [`database/caretrack_db.sql`](database/caretrack_db.sql). This creates the `caretrack_db` database and its tables.
5. Configure database access. `config/database.php` reads these environment variables:
   - `CARETRACK_DB_HOST` (defaults to `127.0.0.1`)
   - `CARETRACK_DB_NAME` (defaults to `caretrack_db`)
   - `CARETRACK_DB_USER` (defaults to `root`)
   - `CARETRACK_DB_PASSWORD` (defaults to an empty password if unset)

   The defaults match a typical fresh XAMPP setup. If your MySQL account differs, configure the variables in the environment used by Apache; do not commit credentials.
6. Ensure Apache can write to `uploads/patient-photos/`. Uploaded photos are excluded from Git.
7. Visit `http://localhost/caretrack/setup-admin.php` and create the initial administrator. After successful setup, remove or disable `setup-admin.php`.
8. Sign in at `http://localhost/caretrack/login.php`.

Import the schema once into a fresh database. It is not an idempotent migration script; do not re-import it into a database containing tables or records you need to preserve.

## Deploy to Vercel

Vercel does not provide an official PHP runtime. This project uses the community-maintained [`vercel-php`](https://github.com/vercel-community/php) runtime, so deployment depends on that runtime continuing to work with Vercel. For a conventional PHP deployment, use a host that provides PHP and MySQL directly.

The Vercel setup also requires remote MySQL-compatible storage and a **private** S3-compatible bucket. Vercel function filesystems are not persistent: PHP sessions and uploaded patient photos must not rely on local disk.

1. Create a Vercel project from this repository and keep the repository root as the project root. The included `vercel.json` registers the PHP runtime only for `api/*.php`; `api/index.php` safely dispatches the existing `/caretrack/...` PHP URLs, and Vercel serves the assets from `/assets`.
2. Create an external MySQL or MariaDB database reachable from Vercel. Import [`database/caretrack_db.sql`](database/caretrack_db.sql) into a new database. For an existing database, apply [`database/session-migration.sql`](database/session-migration.sql) once.
3. Create a private S3-compatible bucket with default encryption enabled. Create credentials limited to reading, writing, and deleting objects under `patient-photos/`. Do not enable public access.
4. Add these environment variables to the Vercel project for each deployment environment:

   | Variable | Value |
   | --- | --- |
   | `CARETRACK_DB_HOST` | External database host |
   | `CARETRACK_DB_NAME` | Database name |
   | `CARETRACK_DB_USER` | Database username |
   | `CARETRACK_DB_PASSWORD` | Database password |
   | `CARETRACK_SESSION_STORAGE` | `database` |
   | `CARETRACK_PHOTO_STORAGE` | `s3` |
   | `CARETRACK_S3_BUCKET` | Private bucket name |
   | `CARETRACK_S3_REGION` | Bucket region |
   | `CARETRACK_S3_ACCESS_KEY_ID` | Restricted bucket access key |
   | `CARETRACK_S3_SECRET_ACCESS_KEY` | Restricted bucket secret |
   | `CARETRACK_S3_ENDPOINT` | Optional S3-compatible endpoint; omit for AWS S3 |

5. Deploy, then visit `/caretrack/setup-admin.php` once to create the first administrator. Afterward, confirm setup is disabled and remove `setup-admin.php` from the deployment before inviting staff.
6. Test login, logout, CSRF-protected forms, photo upload and display, patient editing, and photo deletion against the deployed services. Do not import real patient data during testing.

The database session table uses MySQL named locks to avoid concurrent requests overwriting a user's session. Verify that the chosen database supports `GET_LOCK` and `RELEASE_LOCK`. If moving existing patient records, migrate their photos into the private bucket under `patient-photos/` and retain the matching filenames in `patients.profile_image`; local ignored upload files are not part of a Git deployment.

This project is an educational starting point, not a certified clinical system. Do not use real patient information unless the deployment, storage location, access controls, backups, and legal/privacy requirements have been independently reviewed and approved.

## How the system works

### Accounts and permissions

Staff register through `register.php`. A registration creates an inactive receptionist account, so the person cannot sign in until an administrator activates the account from **User Management** and assigns the appropriate role. Passwords are stored as password hashes rather than plain text.

| Role | Main access |
| --- | --- |
| Admin | Dashboard, patients, appointments, medical records, user management, and activity logs |
| Receptionist | Dashboard, patient management, and appointment management |
| Clinician | Dashboard, appointments, and medical records assigned to that clinician |

Authorization is checked in the PHP request handlers, not only by hiding navigation links.

### Patients

Patient records are stored in `patients`. Staff with patient-management permission can create, search, and update patients. Archiving is a soft delete: the record remains in MySQL with `deleted_at` set, rather than being permanently erased. The regular patient list hides archived records.

Patient photos are MIME-checked, limited in size, and given random filenames. The repository intentionally excludes files in `uploads/patient-photos/`; never commit real patient photos.

### Appointments

Appointments connect a patient, a clinician, and a date/time. Staff can schedule and update bookings; authorized users can change appointment status. The current schema has no appointment-duration field, so conflict checking prevents the same clinician from having two active bookings at the **exact same date and time**, but does not detect overlapping time ranges.

### Medical records

Medical records connect a patient with a clinician and may optionally link to an appointment. Admins can view all records; clinicians can view records assigned to them and create records under their own account. The current version does not provide editing or deletion, preserving the original entry as an audit trail.

### Activity logs

`activity_logs` stores the acting account when available, a short action name, the affected record type and ID, and the timestamp. The log avoids storing passwords and clinical note contents. Only administrators can view the log page.

### Application structure

- `config/` — PDO database connection.
- `database/` — initial MySQL schema.
- `includes/` — bootstrap, authentication, CSRF, shared helpers, layout, uploads, and appointment helpers.
- `dashboard/`, `patients/`, `appointments/`, `medical-records/`, `users/`, `activity-logs/` — application modules.
- `assets/` — custom CSS and JavaScript.
- `uploads/patient-photos/` — local uploaded images; contents are not tracked by Git.

## Security notes

- Use fictional data for development and demos.
- Keep database credentials outside source control.
- Do not commit `.env` files, database exports containing records, uploaded photos, or other personal data.
- Remove or disable `setup-admin.php` after the first administrator is created.
- The included Apache `.htaccess` blocks directory listing and common PHP execution extensions in the photo folder. This is an extra safeguard, not a replacement for keeping sensitive uploads outside the public web root.
- This project has not been reviewed or certified for production handling of protected health information.

## Current limitations

- Appointment conflict checks compare exact start times because appointment duration is not represented in the schema.
- Medical records cannot currently be edited or deleted.
- Archived patients are excluded from the standard patient list; there is no dedicated restore/archive browser yet.
- This repository contains no automated test suite or production deployment configuration.
