# 📱 Mobile Device Testing & Camera Access Guide

This guide explains how to connect and test the **NCST Events & Tickets System** from physical mobile phones (Android and iOS) on the same local network, and how to enable camera access for optical QR scanning.

---

## 1. Prerequisites & Network Setup

1. **Same Wi-Fi Network**: Ensure your development PC and mobile phone are connected to the exact same Wi-Fi router/hotspot.
2. **Find Your Local IP Address**:
   * Open PowerShell / Command Prompt on Windows and run:
     ```powershell
     ipconfig
     ```
   * Locate your active adapter (e.g., Wireless LAN adapter Wi-Fi) and note the **IPv4 Address** (e.g. `192.168.254.104`).

---

## 2. Windows Firewall Configuration

By default, Windows Defender Firewall blocks inbound traffic to Node.js and Apache from external devices on the local network.

### Option A: Automated One-Click Script (Recommended)
Right-click [`allow-firewall.bat`](file:///c:/xampp/htdocs/Integ%202/allow-firewall.bat) in the project root and select **Run as Administrator**. This automatically creates inbound rules for:
* **Port 5173** (Vite Dev Server)
* **Port 80** (Apache HTTP / XAMPP)
* **Port 443** (Apache HTTPS / SSL)

### Option B: Manual PowerShell Command
Open PowerShell as **Administrator** and run:
```powershell
netsh advfirewall firewall add rule name="NCST Vite Dev Server" dir=in action=allow protocol=TCP localport=5173
netsh advfirewall firewall add rule name="NCST Apache Web" dir=in action=allow protocol=TCP localport=80
```

---

## 3. The Camera Access Requirement (HTTPS / Secure Context)

Modern mobile operating systems (iOS Safari and Android Chrome) **strictly block** access to the device camera (`navigator.mediaDevices.getUserMedia`) when visiting sites over unencrypted HTTP (e.g. `http://192.168.x.x`), treating them as insecure contexts.

Camera access is only permitted under:
1. `http://localhost` or `http://127.0.0.1` (on the local machine only).
2. **`https://` (Encrypted SSL/TLS)**.

### How to Test with Full Camera Support on Mobile

#### Method 1: Vite HTTPS Mode (Built-In Self-Signed Certificate)
The project includes `@vitejs/plugin-basic-ssl` in its development toolchain.
1. Start the Vite server with HTTPS:
   ```bash
   npm run dev -- --https --host 0.0.0.0
   ```
2. On your phone's browser, navigate to:
   ```
   https://<YOUR-IP>:5173/
   # Example: https://192.168.254.104:5173/
   ```
3. **Accepting Self-Signed Certificate Warning**:
   * **Chrome (Android)**: Tap *Advanced* → *Proceed to 192.168.x.x (unsafe)*.
   * **Safari (iOS)**: Tap *Show Details* → *Visit this website* → *Visit Website*.
4. When prompted, tap **Allow** on the camera permission dialog.

#### Method 2: Cloudflare / Ngrok Tunnel (Easiest & Most Reliable)
If your router has client isolation (AP isolation) enabled or you prefer a zero-warning public HTTPS link:
1. Double-click [`mobile-tunnel.bat`](file:///c:/xampp/htdocs/Integ%202/mobile-tunnel.bat) in the project root, or run:
   ```bash
   npx localtunnel --port 5173
   # or
   npx cloudflared tunnel --url http://localhost:5173
   ```
2. Open the generated secure URL (e.g. `https://random-subdomain.loca.lt`) on your phone.
3. This provides an official SSL certificate recognized by all mobile browsers without warning dialogues.

---

## 4. Mobile Testing Scenarios Checklist

### Scenario A: Entrance Scanner Operator (`event_staff`)
1. Log in on your phone using:
   * **Email**: `staff@ncst.edu.ph`
   * **Password**: `password123`
2. Navigate to **Entrance Scanner**.
3. Verify the following mobile UX capabilities:
   * **Camera Viewfinder**: Camera stream renders smoothly within the viewfinder frame.
   * **Torch / Flashlight**: Tap the flashlight button to toggle your phone's LED torch on and off (supported on Android Chrome).
   * **Continuous Rapid Scanning**: Scan multiple tickets back-to-back without manual reset.
   * **Haptic Vibration**: Confirm your device gives a crisp tactile vibration pulse upon a successful scan (or double vibration on invalid ticket).
   * **Audio Chimes**: Confirm the dual-tone chime rings via the Web Audio API synthesizer.
   * **Manual Fallback**: Tap "Enter ID Manually" to test attendance verification when a camera cannot read a damaged QR code.

### Scenario B: Student Digital Ticket (`student`)
1. Log in on your phone using:
   * **Student ID**: `2024-58566`
   * **Password**: `password123`
2. Navigate to **My Passes & Attendance**.
3. Verify that your digital dynamic QR pass displays clearly on mobile screens and adjusts to dark/light theme seamlessly.
4. Verify that scanning this phone screen using another device records attendance in real-time.

### Scenario C: Offline Resilience
1. While logged in as Event Staff on the Scanner view, turn on **Airplane Mode** on your phone.
2. Confirm the **Offline Mode Banner** appears at the top of the screen:
   > *"Offline Mode — Scans and changes are preserved locally and will sync once connection is restored."*
3. Perform a scan or ticket check. Verify that changes are persisted to the device's local queue.
4. Turn **Airplane Mode** off.
5. Verify that the app syncs pending scans back to the database automatically within 5 seconds.

---

## 5. Mobile Troubleshooting Matrix

| Issue | Root Cause | Solution |
| :--- | :--- | :--- |
| **ERR_CONNECTION_TIMED_OUT** | Windows Firewall blocking inbound port 5173 | Run [`allow-firewall.bat`](file:///c:/xampp/htdocs/Integ%202/allow-firewall.bat) as Administrator. |
| **ERR_ADDRESS_UNREACHABLE** | Phone and PC on different Wi-Fi bands/networks | Check PC and phone Wi-Fi settings; connect both to the exact same SSID. |
| **"Cannot access camera" / Permission denied** | Browser accessed over unencrypted `http://` | Access via `https://` (Method 1 or Method 2 above) to satisfy browser security requirements. |
| **"Your connection is not private"** | Self-signed development certificate | Tap "Advanced" and click "Proceed to [IP] (unsafe)". |
| **No haptic vibration on iPhone** | iOS Safari does not support the Web Vibration API | Haptic vibration works on Android devices; iOS falls back gracefully to audible chimes and visual banners. |
