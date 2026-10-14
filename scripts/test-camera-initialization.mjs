import assert from 'assert';
import { parseCameraError, checkSecureContext, stopMediaTracks } from '../src/utils/cameraUtils.ts';

console.log('🧪 Starting Camera Initialization & Error Diagnosis Test Suite...\n');

// Test 1: Permission Denied (NotAllowedError / PermissionDeniedError)
const permError = new Error('Permission denied by user');
permError.name = 'NotAllowedError';
const parsedPerm = parseCameraError(permError);

assert.strictEqual(parsedPerm.type, 'permission_denied', 'Must categorize as permission_denied');
assert.strictEqual(parsedPerm.title, 'Camera Permission Denied', 'Must provide clear title');
assert.ok(parsedPerm.tip.includes('lock') || parsedPerm.tip.includes('Allow'), 'Must instruct user to check browser address bar permissions');
assert.strictEqual(parsedPerm.canRetry, true, 'Permission denial must allow retry');
console.log('  ✔ Test 1: NotAllowedError correctly diagnosed with browser settings instructions');

// Test 2: Camera Not Found (NotFoundError / DevicesNotFoundError)
const notFoundError = new Error('Requested device not found');
notFoundError.name = 'NotFoundError';
const parsedNotFound = parseCameraError(notFoundError);

assert.strictEqual(parsedNotFound.type, 'not_found', 'Must categorize as not_found');
assert.strictEqual(parsedNotFound.title, 'No Camera Detected', 'Must provide distinct no camera title');
assert.ok(!parsedPerm.tip.includes('No video input'), 'Permission error must not claim missing hardware');
assert.ok(parsedNotFound.tip.includes('webcam') || parsedNotFound.tip.includes('Upload'), 'Must suggest webcam or upload fallback');
console.log('  ✔ Test 2: NotFoundError correctly diagnosed without assuming permission failure');

// Test 3: Camera Busy / In Use (NotReadableError / TrackStartError)
const busyError = new Error('Could not start video source');
busyError.name = 'NotReadableError';
const parsedBusy = parseCameraError(busyError);

assert.strictEqual(parsedBusy.type, 'busy', 'Must categorize as busy');
assert.strictEqual(parsedBusy.title, 'Camera is Currently In Use', 'Must state camera is busy');
assert.ok(parsedBusy.tip.includes('Zoom') || parsedBusy.tip.includes('close other'), 'Must instruct user to close other apps');
console.log('  ✔ Test 3: NotReadableError correctly diagnosed as busy hardware');

// Test 4: Overconstrained / Constraints Not Supported
const overconstrainedError = new Error('Constraints could not be satisfied');
overconstrainedError.name = 'OverconstrainedError';
const parsedOverconstrained = parseCameraError(overconstrainedError);

assert.strictEqual(parsedOverconstrained.type, 'overconstrained', 'Must categorize as overconstrained');
assert.strictEqual(parsedOverconstrained.actionLabel, 'Use Default Camera', 'Must offer fallback to default camera');
console.log('  ✔ Test 4: OverconstrainedError correctly diagnosed with default camera fallback');

// Test 5: Insecure Context (SecurityError)
const securityError = new Error('Access is restricted to secure origins');
securityError.name = 'SecurityError';
const parsedSecurity = parseCameraError(securityError);

assert.strictEqual(parsedSecurity.type, 'insecure_context', 'Must categorize as insecure_context');
assert.strictEqual(parsedSecurity.title, 'Camera Security Restriction', 'Must cite security restriction');
console.log('  ✔ Test 5: SecurityError correctly diagnosed with HTTPS/localhost explanation');

// Test 6: Unknown Error does NOT falsely claim permission blocked
const genericError = new Error('GPU process crashed');
genericError.name = 'UnknownError';
const parsedGeneric = parseCameraError(genericError);

assert.strictEqual(parsedGeneric.type, 'unknown', 'Must categorize as unknown');
assert.strictEqual(parsedGeneric.title, 'Camera Initialization Failed', 'Must not claim permission denied');
assert.strictEqual(parsedGeneric.message, 'GPU process crashed', 'Must preserve original error message');
console.log('  ✔ Test 6: Generic/Unknown errors preserve real error details without false permission blame');

// Test 7: MediaStream track cleanup
let stoppedTrackCount = 0;
const mockTrack = {
  stop: () => { stoppedTrackCount++; }
};
const mockStream = {
  getTracks: () => [mockTrack, mockTrack]
};
const mockVideoElement = {
  srcObject: mockStream
};

stopMediaTracks(mockVideoElement);
assert.strictEqual(stoppedTrackCount, 2, 'Must stop all tracks in media stream');
assert.strictEqual(mockVideoElement.srcObject, null, 'Must clear video srcObject reference');
console.log('  ✔ Test 7: stopMediaTracks successfully stops all hardware tracks and clears video object');

console.log('\n🎉 ALL 7 CAMERA UTILITY & ERROR DIAGNOSIS TESTS PASSED SUCCESSFULLY!\n');
