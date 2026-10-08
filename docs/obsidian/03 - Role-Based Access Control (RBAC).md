---
title: "Role-Based Access Control (RBAC) & Security"
tags:
  - security
  - rbac
  - permissions
  - auth
date: 2026-10-08
type: security-architecture
status: complete
---

# 🛡️ Role-Based Access Control (RBAC) & Security

> [!INFO] Navigation
> Back to [[00 - Map of Content (Index)]] | Previous: [[02 - Data Models & Entity Relationships]] | Next: [[04 - Cashier POS & Dynamic QR Workflow]]

---

## 1. Permission Matrix

The system strictly isolates capabilities based on the authenticated user's role:

| Workspace Feature / Action | Admin (`admin`) | Cashier (`cashier`) | Event Staff (`event_staff`) | Teacher (`teacher`) | Student (`student`) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Executive Analytics & Overview** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Event Management (Create/Edit/Archive)** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Ticket POS Station (Issue Tickets)** | ✅ Full | ✅ Full | ❌ | ❌ | ❌ |
| **Dynamic QR Print Queue & Reprints** | ✅ Full | ✅ Full | ❌ | ❌ | ❌ |
| **Optical Entrance Scanner (Scan QR)** | ✅ Full | ❌ | ✅ Full | ❌ | ❌ |
| **Assigned Class Attendance Monitoring** | ✅ Master | ❌ | ❌ | ✅ Scoped | ❌ |
| **Class Attendance Excel Export** | ✅ Full | ❌ | ❌ | ✅ Scoped | ❌ |
| **User Management & Password Reset** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **View Own Passes & Digital QR** | ❌ | ❌ | ❌ | ❌ | ✅ Own Only |
| **Events Calendar View** | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 2. Role Specifications & Boundary Enforcements

### 🛡️ Administrator (`admin`)
* **Scope**: Universal administrative oversight.
* **Capabilities**:
  - Full CRUD operations over events, tickets, and user accounts.
  - Live inspection of all entrance attendance streams.
  - Administrative password reset for any student, teacher, or staff account.
  - Institutional-wide Excel attendance report generation.

---

### 🎟️ Cashier (`cashier`)
* **Scope**: Ticketing Point-of-Sale desk.
* **Capabilities**:
  - Process cash payments and compute exact change.
  - Issue tickets to both enrolled students and outside guests.
  - Manage the **QR Print Queue** to generate adhesive thermal stickers.
  - Automatically provision unregistered student accounts with default password `ncst123`.
* **Hard Restrictions**:
  > [!WARNING]
  > Cashiers **cannot** scan tickets, alter event details, create outsider login accounts, or access attendance analytics.

---

### 📱 Event Staff (`event_staff`)
* **Scope**: Physical event entrance gate.
* **Capabilities**:
  - Operate the **Optical Camera QR Scanner**.
  - Perform fallback ticket number lookup when a ticket is damaged.
  - Record attendance with automatic Philippine Standard Time (PHT) timestamps.
  - Receive duplicate scan alerts (`⚠️ TICKET ALREADY USED`).
* **Hard Restrictions**:
  > [!WARNING]
  > Event staff **cannot** view financial data, issue tickets, or edit events.

---

### 👨‍🏫 Teacher / Faculty (`teacher`)
* **Scope**: Course and section attendance verification.
* **Capabilities**:
  - View attendance exclusively for assigned classes (e.g. `BSIT — 11M1`, `BSIT — 31M1`).
  - Student roster strictly sorted alphabetically by **Last Name (A–Z)**.
  - Export section attendance to pre-formatted Excel grade sheets.
* **Hard Restrictions**:
  > [!WARNING]
  > Teachers **cannot** view students outside their assigned sections, scan tickets at the gate, or edit event information.

---

### 🎓 Student (`student`)
* **Scope**: Personal event dashboard.
* **Capabilities**:
  - View upcoming campus events.
  - Inspect personal digital tickets with live QR codes.
  - Track personal entry attendance status and Time-In.
  - Complete first-time password setup.
* **Hard Restrictions**:
  > [!WARNING]
  > Students **cannot** purchase tickets online (Cashier POS only), view other students' tickets, or transfer tickets.

---

## 3. Anti-Tampering & Security Defenses

1. **Session-Level Anti-Tampering**:
   - Client-side modifications to `localStorage` user roles are neutralized on state synchronization against authoritative server user records.
2. **Server-Side Authorization Gates**:
   - All REST API endpoints verify the session token against server memory before returning or mutating data.
3. **Identity Uniqueness Guarantees**:
   - `studentId`, `employeeId`, and `email` are strictly unique across active and inactive accounts.
4. **Temporary Outsider Expiration**:
   - External guest accounts automatically deactivate at 12:00:00 AM PHT following event conclusion.
