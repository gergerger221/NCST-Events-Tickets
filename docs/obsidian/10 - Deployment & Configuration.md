---
title: "Deployment & Configuration Guide"
tags:
  - deployment
  - setup
  - xampp
  - vite
  - devops
date: 2026-10-08
type: deployment-guide
status: complete
---

# 🚀 Deployment & Configuration Guide

> [!INFO] Navigation
> Back to [[00 - Map of Content (Index)]] | Previous: [[09 - Database Schema & SQL Reference]]

---

## 1. Prerequisites

* **Node.js**: v18.0.0 or higher
* **Package Manager**: `npm` (v9+)
* **Database & Web Server (Optional / Production)**: XAMPP with Apache, PHP 8.1+, and MySQL / MariaDB

---

## 2. Quickstart Launcher (`start.bat`)

For Windows users, a convenient one-click launcher [`start.bat`](file:///c:/xampp/htdocs/Integ%202/start.bat) is provided:

1. Double-click **`start.bat`** (or run `.\start.bat` in PowerShell/CMD).
2. It will automatically:
   - Verify Node.js is installed.
   - Install `node_modules` if missing (`npm install`).
   - Connect to XAMPP MySQL and verify database tables (`api/setup.php`).
   - Launch the Vite development server at `http://localhost:5173`.
   - Open your default web browser automatically.

---

## 3. Development Mode (Manual)

The development environment runs with an embedded API and session middleware in `vite.config.ts`.

### Installation & Launch
```bash
# 1. Install dependencies
npm install

# 2. Run the development server
npm run dev
```

The application will be accessible at:
$$\text{http://localhost:5173/}$$

---

## 3. Production Build

To compile and minify the Vue 3 single-page application for deployment:

```bash
# Compile TypeScript and build production bundle
npm run build
```

This generates optimized static files inside the `dist/` directory:
* `dist/index.html`
* `dist/assets/index-*.css`
* `dist/assets/index-*.js`

### Previewing the Production Build
```bash
npm run preview
```

---

## 4. XAMPP / Apache Deployment

When deploying into `c:\xampp\htdocs\Integ 2`:

1. **Import Database**:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin/`).
   - Create database `event_attendance_db`.
   - Import [`database.sql`](file:///c:/xampp/htdocs/Integ%202/database.sql).
2. **Environment Configuration (`.env`)**:
   - Create `.env` from [`.env.example`](file:///c:/xampp/htdocs/Integ%202/.env.example):
     ```env
     APP_ENV=production
     APP_KEY=your-64-character-hex-app-key
     DB_HOST=127.0.0.1
     DB_NAME=event_attendance_db
     DB_USER=root
     DB_PASS=
     ```
   - [`api/db.php`](file:///c:/xampp/htdocs/Integ%202/api/db.php) and [`api/auth_helper.php`](file:///c:/xampp/htdocs/Integ%202/api/auth_helper.php) load connection credentials and HMAC secrets from `.env`.
3. **Verify Database Setup**:
   - Run `php api/setup.php` to verify table schema integrity and seed state.
4. **Build Frontend**:
   - Run `npm run build` to generate the production bundle in `dist/`.
5. **Run Master Test Verification**:
   - Run `npm test` to verify all 19 frontend and 2 backend suites pass with 100% green status.


---

## 5. Default Demo Accounts

For testing and evaluation purposes, the system pre-configures representative accounts:

| Role | Username / Identifier | Default Password | Description |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@ncst.edu.ph` / `ADM-001` | `password123` | Master institutional administrator |
| **Cashier** | `cashier@ncst.edu.ph` / `CSH-001` | `password123` | Front-desk ticketing cashier |
| **Event Staff** | `staff@ncst.edu.ph` / `STF-001` | `password123` | Entrance gate scanner operator |
| **Teacher** | `teacher@ncst.edu.ph` / `TCH-001` | `password123` | Faculty instructor (`BSIT — 11M1`, `31M1`) |
| **Student** | `2024-58566` / `2024-58566@student.ncst.edu.ph` | `password123` | Pre-registered enrolled student |
| **New Student** | *(Any unregistered Student ID e.g. `2026-00129`)* | `ncst123` | Automatic first-purchase student password |
