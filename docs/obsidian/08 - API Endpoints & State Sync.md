---
title: "API Endpoints & State Synchronization"
tags:
  - api
  - endpoints
  - rest
  - state-sync
  - authentication
date: 2026-10-08
type: api-reference
status: complete
---

# 🔌 API Endpoints & State Synchronization

> [!INFO] Navigation
> Back to [[00 - Map of Content (Index)]] | Previous: [[07 - Faculty & Teacher Portal]] | Next: [[09 - Database Schema & SQL Reference]]

---

## 1. Authentication & Security Headers

Authenticated requests must include the HMAC-SHA256 bearer token in the `Authorization` request header:

```http
Authorization: Bearer <HMAC_AUTH_TOKEN>
Content-Type: application/json
```

* **Token Format**: `header.payload.signature` signed with server `APP_KEY`.
* **Token Lifetime**: 24 hours.
* **Stateless Validation**: Verified securely on each request via [`api/auth_helper.php`](api/auth_helper.php).
* **Apache Preservation**: Handled automatically in `api/.htaccess` to prevent Apache from stripping the header.
* **Rate Limiting**: `POST /api/auth/login` enforces a rate limit of 5 attempts/minute per IP address (returning `429 Too Many Requests` on breach).

---

## 2. API Endpoints Reference

### Authentication Endpoints

#### `POST /api/auth/login`
Authenticates a user against central server state.
* **Request Body**:
  ```json
  {
    "identifier": "2024-58566",
    "password": "mypassword123"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Welcome back, Juan Dela Cruz!",
    "user": {
      "id": "usr-student-123456",
      "name": "Juan Dela Cruz",
      "role": "student",
      "studentId": "2024-58566",
      "email": "2024-58566@student.ncst.edu.ph"
    },
    "token": "sess_student_usr-student-123456",
    "sessionToken": "sess_student_usr-student-123456"
  }
  ```

#### `POST /api/auth/verify-session`
Validates active session token and returns fresh user profile.

#### `POST /api/auth/reset-password`
Handles self-service student password resets (requires 2-factor match of Student ID + registered Email) or administrative resets.

---

### Student Onboarding Endpoints

#### `POST /api/lookup-student`
Authoritative lookup for portal registration.
* **Request Body**:
  ```json
  {
    "studentId": "2024-58566"
  }
  ```
* **Response (200 OK)**:
  ```json
  {
    "success": true,
    "found": true,
    "hasExistingAccount": false,
    "student": {
      "id": "rec-2024-58566",
      "studentId": "2024-58566",
      "name": "Juan Dela Cruz",
      "department": "Computer Studies Department",
      "course": "BSIT",
      "yearLevel": "3rd Year",
      "section": "31M1",
      "email": "2024-58566@student.ncst.edu.ph"
    },
    "message": "Student Record Found"
  }
  ```

#### `POST /api/register`
Creates and persists a new student user in central database state.

---

### Ticketing & POS Endpoints

#### `GET /api/tickets`
Retrieves tickets scoped to user role:
* **Admin / Cashier**: Returns all issued tickets.
* **Student**: Returns strictly tickets owned by authenticated student.

#### `POST /api/tickets/issue` / `POST /api/purchase`
Cashier POS endpoint to create ticket records and log transactions. Restricted to `admin` and `cashier`.

---

### Scanning & Attendance Endpoints

#### `POST /api/scan-ticket`
Validates ticket scan at entrance gate.
* **Request Body**:
  ```json
  {
    "eventId": "evt-juans-2026",
    "rawScanData": "TIX-10009",
    "scanType": "entry",
    "area": "Main Gymnasium Gate"
  }
  ```
* **Response (200 OK — Entry Granted)**:
  ```json
  {
    "success": true,
    "message": "Ticket valid. Attendance recorded in Philippine Time.",
    "attendee_name": "Christian Bautista",
    "student_id": "2026-00129",
    "ticket_number": "TIX-10009",
    "attendance_status": "PRESENT",
    "time_in": "08:15 AM",
    "verified_by": "Staff Maria Santos"
  }
  ```

#### `GET /api/attendance`
Retrieves attendance records scoped by role (Admin = all records; Teacher = assigned section records; Student = own records).

---

### State Synchronization Endpoints

#### `GET /api/state`
Returns the full scoped state tree (`users`, `events`, `tickets`, `attendanceLogs`, `transactions`) based on the authenticated role.

#### `GET /api/state?since=<TIMESTAMP>` (Delta Sync)
Sub-2ms real-time delta synchronization endpoint:
* **Query Parameter**: `since` — ISO-8601 timestamp string of client's latest sync.
* **Response (Changes Found)**:
  ```json
  {
    "isDelta": true,
    "serverTime": "2026-10-08T22:30:00Z",
    "events": [ ... ],
    "tickets": [ ... ],
    "attendanceLogs": [ ... ]
  }
  ```
* **Response (Zero Changes)**:
  ```json
  {
    "notModified": true,
    "serverTime": "2026-10-08T22:30:00Z"
  }
  ```
* Bandwidth reduction: >98% compared to snapshot transfers.

#### `POST /api/state`
Pushes atomic state mutations from authorized roles (`admin`, `cashier`, `event_staff`). Client batches updates with a 400ms debounce.

