/**
 * Camera Utility & Diagnostic Engine for NCST Entrance Scanner
 * Provides robust error categorization, secure context checks,
 * constraint fallbacks, and complete media track lifecycle cleanup.
 */

export type CameraErrorType = 
  | 'permission_denied'
  | 'not_found'
  | 'busy'
  | 'overconstrained'
  | 'insecure_context'
  | 'aborted'
  | 'unknown';

export interface CameraErrorInfo {
  type: CameraErrorType;
  title: string;
  message: string;
  tip: string;
  actionLabel: string;
  canRetry: boolean;
}

/**
 * Checks if the current page environment is a secure context suitable for MediaDevices.
 * Modern browsers require HTTPS or localhost (127.0.0.1) to access camera hardware.
 */
export function checkSecureContext(): { isSecure: boolean; error?: CameraErrorInfo } {
  if (typeof window === 'undefined') {
    return { isSecure: true };
  }

  const hostname = window.location.hostname || '';
  const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]';
  const isHttps = window.location.protocol === 'https:';
  const isSecure = Boolean(window.isSecureContext || isLocalhost || isHttps);

  if (!isSecure) {
    return {
      isSecure: false,
      error: {
        type: 'insecure_context',
        title: 'HTTPS or Localhost Required',
        message: 'Camera hardware access is restricted on unencrypted HTTP connections.',
        tip: 'Modern mobile browsers strictly require HTTPS or localhost (127.0.0.1) to access camera hardware. If accessing across your local network (e.g. 10.x.x.x or 192.168.x.x), please switch to HTTPS and ensure the local development certificate is trusted.',
        actionLabel: 'Switch to HTTPS',
        canRetry: false
      }
    };
  }

  if (typeof navigator !== 'undefined' && (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function')) {
    return {
      isSecure: false,
      error: {
        type: 'insecure_context',
        title: 'Camera API Blocked or Unavailable',
        message: 'Your browser environment does not support or has blocked the MediaDevices camera API.',
        tip: 'If accessing over HTTPS on mobile, ensure the NCST Root CA certificate is installed and trusted on this device (Settings > Security > Install CA certificate). Also verify camera permissions.',
        actionLabel: 'Retry Camera',
        canRetry: true
      }
    };
  }

  return { isSecure: true };
}

/**
 * Accurately parses camera initialization exceptions into user-friendly diagnostic guidance.
 * Distinguishes permission denials, missing cameras, busy hardware, overconstraints, and insecure contexts.
 */
export function parseCameraError(err: any): CameraErrorInfo {
  // Check secure context first
  const secureCheck = checkSecureContext();
  if (!secureCheck.isSecure && secureCheck.error) {
    return secureCheck.error;
  }

  const rawName = String(err?.name || '').trim();
  const rawMsg = String(err?.message || (typeof err === 'string' ? err : '')).trim();
  const combined = `${rawName} ${rawMsg}`.toLowerCase();

  // 1. Permission Denied (NotAllowedError / PermissionDeniedError)
  if (
    rawName === 'NotAllowedError' ||
    rawName === 'PermissionDeniedError' ||
    combined.includes('permission denied') ||
    combined.includes('permission dismissed') ||
    combined.includes('notallowederror') ||
    combined.includes('permission_denied')
  ) {
    return {
      type: 'permission_denied',
      title: 'Camera Permission Denied',
      message: 'Camera access was blocked by your browser or operating system.',
      tip: 'To enable camera: Tap or click the lock / tune icon (🔒 / ⚙️) next to the URL in your browser address bar, set Camera to "Allow", then click "Retry Camera".',
      actionLabel: 'Retry Camera',
      canRetry: true
    };
  }

  // 2. Camera Not Found (NotFoundError / DevicesNotFoundError)
  if (
    rawName === 'NotFoundError' ||
    rawName === 'DevicesNotFoundError' ||
    combined.includes('notfounderror') ||
    combined.includes('devicesnotfounderror') ||
    combined.includes('requested device not found') ||
    combined.includes('no camera') ||
    combined.includes('no video input')
  ) {
    return {
      type: 'not_found',
      title: 'No Camera Detected',
      message: 'No video input camera or webcam was found on this device.',
      tip: 'Please plug in a USB webcam, switch to a mobile device with an integrated camera, or use "Upload QR Image" or "Manual Entry" below.',
      actionLabel: 'Retry Detection',
      canRetry: true
    };
  }

  // 3. Camera Busy / In Use by Another App (NotReadableError / TrackStartError)
  if (
    rawName === 'NotReadableError' ||
    rawName === 'TrackStartError' ||
    combined.includes('notreadableerror') ||
    combined.includes('trackstarterror') ||
    combined.includes('could not start video source') ||
    combined.includes('in use') ||
    combined.includes('concurrently') ||
    combined.includes('hardware error')
  ) {
    return {
      type: 'busy',
      title: 'Camera is Currently In Use',
      message: 'The camera could not be started because another application is using it.',
      tip: 'Another program (such as Zoom, Teams, Google Meet, Skype, or another open browser window) may have locked the camera. Please close other camera apps and retry.',
      actionLabel: 'Retry Camera',
      canRetry: true
    };
  }

  // 4. Overconstrained / Constraints Not Supported (OverconstrainedError)
  if (
    rawName === 'OverconstrainedError' ||
    rawName === 'ConstraintNotSatisfiedError' ||
    combined.includes('overconstrainederror') ||
    combined.includes('constraints')
  ) {
    return {
      type: 'overconstrained',
      title: 'Camera Constraints Unsupported',
      message: 'The requested camera lens setting (e.g. rear-facing lens) is not supported by your hardware.',
      tip: 'Click "Use Default Camera" below to automatically fall back to your available webcam.',
      actionLabel: 'Use Default Camera',
      canRetry: true
    };
  }

  // 5. Security Error / Insecure Context
  if (
    rawName === 'SecurityError' ||
    combined.includes('securityerror')
  ) {
    return {
      type: 'insecure_context',
      title: 'Camera Security Restriction',
      message: 'Camera access is blocked by browser security restrictions.',
      tip: 'Ensure the NCST system is running over trusted HTTPS or localhost with the development CA certificate installed.',
      actionLabel: 'Retry',
      canRetry: false
    };
  }

  // 6. Aborted
  if (
    rawName === 'AbortError' ||
    combined.includes('aborterror') ||
    combined.includes('aborted')
  ) {
    return {
      type: 'aborted',
      title: 'Camera Initialization Cancelled',
      message: 'The camera initialization request was interrupted.',
      tip: 'Click "Start Scanner" to restart camera initialization.',
      actionLabel: 'Start Scanner',
      canRetry: true
    };
  }

  // 7. Generic / Unknown Fallback (Preserve real error message, do not assume permission denial)
  return {
    type: 'unknown',
    title: 'Camera Initialization Failed',
    message: rawMsg || 'Unable to connect to video device.',
    tip: 'Check that your webcam is connected properly and browser permissions are allowed, or use "Upload QR Image" below.',
    actionLabel: 'Retry Camera',
    canRetry: true
  };
}

/**
 * Safely and thoroughly terminates all video media tracks associated with a video element or stream.
 * Turns off the hardware camera light/indicator immediately.
 */
export function stopMediaTracks(streamOrElement?: MediaStream | HTMLVideoElement | null): void {
  if (!streamOrElement) return;

  try {
    let stream: MediaStream | null = null;
    if (typeof (streamOrElement as HTMLVideoElement).srcObject !== 'undefined') {
      const video = streamOrElement as HTMLVideoElement;
      stream = video.srcObject as MediaStream;
      video.srcObject = null;
    } else if (streamOrElement instanceof MediaStream) {
      stream = streamOrElement;
    }

    if (stream && typeof stream.getTracks === 'function') {
      stream.getTracks().forEach(track => {
        try {
          track.stop();
        } catch {}
      });
    }
  } catch (err) {
    console.warn('Error stopping media tracks:', err);
  }
}
