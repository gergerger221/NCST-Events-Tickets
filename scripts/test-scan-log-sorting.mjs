import assert from 'assert';
import {
  sortAttendanceLogs,
  extractLogTimestamp,
  SCAN_LOG_SORT_OPTIONS,
  isStudentScanLog,
  sortAlphabeticalByName,
  groupAndSortScanLogs
} from '../src/utils/scanLogSorting.ts';

console.log('🧪 Starting Recent Entrance Scans Sorting Test Suite...\n');

// Sample test dataset
const mockLogs = [
  {
    id: 'log-1',
    ticketId: 'TIX-00125',
    ticketNumber: 'TIX-00125',
    eventId: 'evt-juans-2026',
    eventTitle: 'The Juans Live Concert',
    attendeeName: 'Juan Dela Cruz',
    timestamp: '2026-11-05T18:42:00Z',
    timeIn: '6:42 PM',
    status: 'valid'
  },
  {
    id: 'log-2',
    ticketId: 'TIX-O-00215',
    ticketNumber: 'TIX-O-00215',
    eventId: 'evt-juans-2026',
    eventTitle: 'The Juans Live Concert',
    attendeeName: 'Maria Santos',
    timestamp: '2026-11-05T18:45:00Z',
    timeIn: '6:45 PM',
    status: 'valid'
  },
  {
    id: 'log-3',
    ticketId: 'TIX-00128',
    ticketNumber: 'TIX-00128',
    eventId: 'evt-fnd-2026',
    eventTitle: 'Annual Foundation Day',
    attendeeName: 'Alex Rivera',
    timestamp: '2026-11-05T18:30:00Z',
    timeIn: '6:30 PM',
    status: 'valid'
  },
  {
    id: 'log-4',
    ticketId: 'TIX-00130',
    ticketNumber: 'TIX-00130',
    eventId: 'evt-it-2026',
    eventTitle: 'IT Summit & Hackathon Expo',
    attendeeName: 'Carlos Garcia',
    timestamp: '2026-11-05T19:00:00Z',
    timeIn: '7:00 PM',
    status: 'valid'
  }
];

// --- TEST 1: Newest First Sorting (Default) ---
console.log('--- TEST 1: Newest First Sorting (Default) ---');
const newest = sortAttendanceLogs(mockLogs, 'newest');
assert.strictEqual(newest.length, 4, 'Should contain all 4 logs');
assert.strictEqual(newest[0].id, 'log-4', 'Carlos Garcia (7:00 PM) should be first');
assert.strictEqual(newest[1].id, 'log-2', 'Maria Santos (6:45 PM) should be second');
assert.strictEqual(newest[2].id, 'log-1', 'Juan Dela Cruz (6:42 PM) should be third');
assert.strictEqual(newest[3].id, 'log-3', 'Alex Rivera (6:30 PM) should be fourth');

// Default parameter test without passing sort option
const defaultSorted = sortAttendanceLogs(mockLogs);
assert.deepStrictEqual(defaultSorted.map(l => l.id), newest.map(l => l.id), 'Default should be newest first');
console.log('✅ TEST 1 PASSED: Newest-first sorting orders most recent scans at the top.\n');

// --- TEST 2: Oldest First Sorting ---
console.log('--- TEST 2: Oldest First Sorting ---');
const oldest = sortAttendanceLogs(mockLogs, 'oldest');
assert.strictEqual(oldest[0].id, 'log-3', 'Alex Rivera (6:30 PM) should be first');
assert.strictEqual(oldest[1].id, 'log-1', 'Juan Dela Cruz (6:42 PM) should be second');
assert.strictEqual(oldest[2].id, 'log-2', 'Maria Santos (6:45 PM) should be third');
assert.strictEqual(oldest[3].id, 'log-4', 'Carlos Garcia (7:00 PM) should be fourth');
console.log('✅ TEST 2 PASSED: Oldest-first sorting orders earliest scans at the top.\n');

// --- TEST 3: Student/Attendee Name Alphabetical Sorting (A–Z & Z–A) ---
console.log('--- TEST 3: Student/Attendee Name Alphabetical Sorting (A–Z & Z–A) ---');
const nameAsc = sortAttendanceLogs(mockLogs, 'name_asc');
assert.deepStrictEqual(
  nameAsc.map(l => l.attendeeName),
  ['Alex Rivera', 'Carlos Garcia', 'Juan Dela Cruz', 'Maria Santos'],
  'Attendee names should be sorted alphabetically A-Z'
);

const nameDesc = sortAttendanceLogs(mockLogs, 'name_desc');
assert.deepStrictEqual(
  nameDesc.map(l => l.attendeeName),
  ['Maria Santos', 'Juan Dela Cruz', 'Carlos Garcia', 'Alex Rivera'],
  'Attendee names should be sorted in reverse alphabetical order Z-A'
);
console.log('✅ TEST 3 PASSED: Attendee name A-Z and Z-A sorting matches alphabetical collation.\n');

// --- TEST 4: Event Name Alphabetical Sorting (A–Z & Z–A) ---
console.log('--- TEST 4: Event Name Alphabetical Sorting (A–Z & Z–A) ---');
const eventAsc = sortAttendanceLogs(mockLogs, 'event_asc');
assert.deepStrictEqual(
  eventAsc.map(l => l.eventTitle),
  [
    'Annual Foundation Day',
    'IT Summit & Hackathon Expo',
    'The Juans Live Concert',
    'The Juans Live Concert'
  ],
  'Event titles should be sorted alphabetically A-Z'
);

const eventDesc = sortAttendanceLogs(mockLogs, 'event_desc');
assert.deepStrictEqual(
  eventDesc.map(l => l.eventTitle),
  [
    'The Juans Live Concert',
    'The Juans Live Concert',
    'IT Summit & Hackathon Expo',
    'Annual Foundation Day'
  ],
  'Event titles should be sorted in reverse alphabetical order Z-A'
);
console.log('✅ TEST 4 PASSED: Event name A-Z and Z-A sorting works correctly.\n');

// --- TEST 5: Records with Identical Timestamps (Stable Sort) ---
console.log('--- TEST 5: Records with Identical Timestamps ---');
const tieLogs = [
  { id: 'tie-1', attendeeName: 'First Attendee', timestamp: '2026-11-05T18:00:00Z' },
  { id: 'tie-2', attendeeName: 'Second Attendee', timestamp: '2026-11-05T18:00:00Z' },
  { id: 'tie-3', attendeeName: 'Third Attendee', timestamp: '2026-11-05T18:00:00Z' }
];
const tieNewest = sortAttendanceLogs(tieLogs, 'newest');
assert.deepStrictEqual(
  tieNewest.map(l => l.id),
  ['tie-1', 'tie-2', 'tie-3'],
  'Records with identical timestamps must preserve original relative order in newest-first'
);

const tieOldest = sortAttendanceLogs(tieLogs, 'oldest');
assert.deepStrictEqual(
  tieOldest.map(l => l.id),
  ['tie-1', 'tie-2', 'tie-3'],
  'Records with identical timestamps must preserve original relative order in oldest-first'
);
console.log('✅ TEST 5 PASSED: Deterministic stable sorting preserves original relative order on identical timestamps.\n');

// --- TEST 6: Missing or Invalid Timestamps ---
console.log('--- TEST 6: Missing or Invalid Timestamps Placed Last ---');
const undatedLogs = [
  { id: 'valid-old', attendeeName: 'A', timestamp: '2026-11-05T10:00:00Z' },
  { id: 'undated-1', attendeeName: 'B', timestamp: null },
  { id: 'valid-new', attendeeName: 'C', timestamp: '2026-11-05T12:00:00Z' },
  { id: 'undated-2', attendeeName: 'D', timestamp: 'invalid-date-string' },
  { id: 'undated-3', attendeeName: 'E' } // missing property
];

const undatedNewest = sortAttendanceLogs(undatedLogs, 'newest');
assert.strictEqual(undatedNewest[0].id, 'valid-new', 'Valid new timestamp must be first');
assert.strictEqual(undatedNewest[1].id, 'valid-old', 'Valid old timestamp must be second');
assert.deepStrictEqual(
  undatedNewest.slice(2).map(l => l.id),
  ['undated-1', 'undated-2', 'undated-3'],
  'Undated and invalid records must be placed after valid records'
);

const undatedOldest = sortAttendanceLogs(undatedLogs, 'oldest');
assert.strictEqual(undatedOldest[0].id, 'valid-old', 'Valid old timestamp must be first in oldest sort');
assert.strictEqual(undatedOldest[1].id, 'valid-new', 'Valid new timestamp must be second in oldest sort');
assert.deepStrictEqual(
  undatedOldest.slice(2).map(l => l.id),
  ['undated-1', 'undated-2', 'undated-3'],
  'Undated records must also be placed after valid records in oldest-first'
);
console.log('✅ TEST 6 PASSED: Missing/invalid timestamps placed safely after valid records.\n');

// --- TEST 7: New Scans Arriving While Sorting is Active ---
console.log('--- TEST 7: New Scans Arriving Dynamically ---');
const reactiveLogs = [...mockLogs];
// Current sort is Attendee Name A-Z
let sortedA = sortAttendanceLogs(reactiveLogs, 'name_asc');
assert.strictEqual(sortedA[0].attendeeName, 'Alex Rivera');

// Simulate a new scan arriving at entrance: "Aaron Zuniga"
const incomingScan = {
  id: 'log-incoming',
  attendeeName: 'Aaron Zuniga',
  eventTitle: 'The Juans Live Concert',
  timestamp: '2026-11-05T19:30:00Z',
  timeIn: '7:30 PM',
  status: 'valid'
};
reactiveLogs.unshift(incomingScan);

let sortedB = sortAttendanceLogs(reactiveLogs, 'name_asc');
assert.strictEqual(sortedB[0].attendeeName, 'Aaron Zuniga', 'Incoming scan should automatically sort into first position A-Z');
assert.strictEqual(sortedB.length, 5, 'Total count should update to 5');

// In newest first, incoming scan at 7:30 PM should also be first
let sortedNewestWithIncoming = sortAttendanceLogs(reactiveLogs, 'newest');
assert.strictEqual(sortedNewestWithIncoming[0].id, 'log-incoming', 'Newest incoming scan should be at top');
console.log('✅ TEST 7 PASSED: New scans arriving automatically integrate into selected sort order.\n');

// --- TEST 8: Sorting Combined with Search / Filtering ---
console.log('--- TEST 8: Sorting Combined with Search / Filtering ---');
const searchQuery = 'juans';
const filtered = reactiveLogs.filter(l => 
  (l.eventTitle || '').toLowerCase().includes(searchQuery) ||
  (l.attendeeName || '').toLowerCase().includes(searchQuery)
);
const sortedFiltered = sortAttendanceLogs(filtered, 'name_desc');
assert.deepStrictEqual(
  sortedFiltered.map(l => l.attendeeName),
  ['Maria Santos', 'Juan Dela Cruz', 'Aaron Zuniga'],
  'Search filter and sort must work together seamlessly'
);
console.log('✅ TEST 8 PASSED: Search query filtering and sorting work cooperatively.\n');

// --- TEST 9: Empty Scan Lists & Large Datasets ---
console.log('--- TEST 9: Edge Cases (Empty, Single, 2,000+ Records) ---');
assert.deepStrictEqual(sortAttendanceLogs([], 'newest'), [], 'Empty list should return empty array');
assert.deepStrictEqual(sortAttendanceLogs(null, 'newest'), [], 'Null input should return empty array');

const single = [{ id: '1', attendeeName: 'Solo', timestamp: '2026-11-05T10:00:00Z' }];
assert.deepStrictEqual(sortAttendanceLogs(single, 'newest'), single, 'Single item list should return shallow copy');

// Benchmark 2,000 simulated logs
const largeList = [];
for (let i = 0; i < 2000; i++) {
  largeList.push({
    id: `log-${i}`,
    attendeeName: `Attendee ${2000 - i}`,
    eventTitle: `Event ${i % 5}`,
    timestamp: new Date(1762300000000 + (i * 1000)).toISOString()
  });
}
const startMs = Date.now();
const largeSorted = sortAttendanceLogs(largeList, 'newest');
const durationMs = Date.now() - startMs;

assert.strictEqual(largeSorted.length, 2000, 'All 2,000 records should be preserved');
assert.strictEqual(largeSorted[0].id, 'log-1999', 'Highest timestamp should be first in newest sort');
assert.strictEqual(largeSorted[1999].id, 'log-0', 'Lowest timestamp should be last in newest sort');
assert.ok(durationMs < 100, `Large sort must complete under 100ms (took ${durationMs}ms)`);
console.log(`✅ TEST 9 PASSED: Scalable sorting verified with 2,000 records (${durationMs}ms).\n`);

// --- TEST 10: Non-Mutation Integrity Check ---
console.log('--- TEST 10: Store Immutability Check ---');
const originalSnapshot = JSON.stringify(mockLogs);
sortAttendanceLogs(mockLogs, 'oldest');
sortAttendanceLogs(mockLogs, 'name_desc');
sortAttendanceLogs(mockLogs, 'event_asc');
assert.strictEqual(JSON.stringify(mockLogs), originalSnapshot, 'Original array must NEVER be mutated');
console.log('✅ TEST 10 PASSED: Original store array was not mutated by sorting operations.\n');

// --- TEST 11: Exported Sort Options Descriptor Check ---
console.log('--- TEST 11: Dropdown Option Descriptors ---');
assert.strictEqual(SCAN_LOG_SORT_OPTIONS.length, 6, 'Must define exactly 6 supported sort options');
assert.deepStrictEqual(
  SCAN_LOG_SORT_OPTIONS.map(o => o.value),
  ['newest', 'oldest', 'name_asc', 'name_desc', 'event_asc', 'event_desc'],
  'Sort option values match all required options'
);
console.log('✅ TEST 11 PASSED: Dropdown options accurately match requirements.\n');

// --- TEST 12: Authoritative Student vs Outsider Classification (Not relying on client label alone) ---
console.log('--- TEST 12: Authoritative Student vs Outsider Classification ---');
const mockRoster = [
  { studentId: '2026-00125', name: 'Juan Dela Cruz', course: 'BSIT' },
  { studentId: '2026-00126', name: 'Maria Santos', course: 'BSCS' }
];

const mockTickets = [
  { id: 'TIX-STUD-1', ticketNumber: 'TIX-STUD-1', attendeeType: 'STUDENT', attendeeStudentId: '2026-00125' },
  { id: 'TIX-OUT-1', ticketNumber: 'TIX-OUT-1', attendeeType: 'OUTSIDER', outsiderId: 'out-991' }
];

const mockUsers = [
  { id: 'u1', studentId: '2026-00125', role: 'student' },
  { id: 'u2', role: 'outsider', isOutsider: true }
];

// 1. Enrolled student in roster
const logStudent = {
  id: 'l-s1',
  ticketId: 'TIX-STUD-1',
  studentId: '2026-00125',
  attendeeName: 'Juan Dela Cruz'
};
assert.strictEqual(isStudentScanLog(logStudent, mockTickets, mockRoster, mockUsers), true, 'Enrolled student must classify as Student');

// 2. Outsider ticket must classify as Outsider even if client sent attendeeType: 'STUDENT'
const logManipulated = {
  id: 'l-manip',
  ticketId: 'TIX-OUT-1',
  attendeeName: 'Spoofed Student',
  attendeeType: 'STUDENT' // client tried to claim student, but ticket is outsider
};
assert.strictEqual(isStudentScanLog(logManipulated, mockTickets, mockRoster, mockUsers), false, 'Authoritative ticket takes precedence over client label');

// 3. Outsider with outsiderId
const logOutsider = {
  id: 'l-o1',
  ticketId: 'TIX-OTHER',
  outsiderId: 'out-123',
  attendeeName: 'Guest Person'
};
assert.strictEqual(isStudentScanLog(logOutsider, mockTickets, mockRoster, mockUsers), false, 'Explicit outsider ID must classify as Outsider');
console.log('✅ TEST 12 PASSED: Authoritative classification correctly distinguishes students from outsiders.\n');

// --- TEST 13: Alphabetical Sorting by Full Name (Case-Insensitive A–Z) ---
console.log('--- TEST 13: Alphabetical Sorting by Full Name (Case-Insensitive A–Z) ---');
const mixedCaseLogs = [
  { id: '1', attendeeName: 'maria santos', timestamp: '2026-11-05T18:45:00Z', timeIn: '6:45 PM' },
  { id: '2', attendeeName: 'ALEX RIVERA', timestamp: '2026-11-05T18:30:00Z', timeIn: '6:30 PM' },
  { id: '3', attendeeName: 'Juan Dela Cruz', timestamp: '2026-11-05T18:42:00Z', timeIn: '6:42 PM' },
  { id: '4', attendeeName: 'carlos garcia', timestamp: '2026-11-05T19:00:00Z', timeIn: '7:00 PM' }
];

const sortedAlpha = sortAlphabeticalByName(mixedCaseLogs);
assert.deepStrictEqual(
  sortedAlpha.map(l => l.id),
  ['2', '4', '3', '1'], // ALEX RIVERA, carlos garcia, Juan Dela Cruz, maria santos
  'Must sort case-insensitively A to Z'
);
console.log('✅ TEST 13 PASSED: Full names sorted alphabetically A–Z case-insensitively.\n');

// --- TEST 14: Missing Name Handling in Alphabetical Sorting ---
console.log('--- TEST 14: Missing Name Handling in Alphabetical Sorting ---');
const missingNameLogs = [
  { id: 'named-z', attendeeName: 'Zoe Saldana' },
  { id: 'unnamed-1', attendeeName: '' },
  { id: 'named-a', attendeeName: 'Aaron Paul' },
  { id: 'unnamed-2', attendeeName: undefined }
];

const sortedMissing = sortAlphabeticalByName(missingNameLogs);
assert.strictEqual(sortedMissing[0].id, 'named-a', 'Aaron Paul must be first');
assert.strictEqual(sortedMissing[1].id, 'named-z', 'Zoe Saldana must be second');
assert.deepStrictEqual(
  sortedMissing.slice(2).map(l => l.id),
  ['unnamed-1', 'unnamed-2'],
  'Missing/empty names must be placed at the end of the group'
);
console.log('✅ TEST 14 PASSED: Missing names safely positioned at the end of the group.\n');

// --- TEST 15: Two Separate Groups (Students & Outsiders) Both Sorted A–Z ---
console.log('--- TEST 15: Two Separate Groups Both Sorted A–Z ---');
const mixedGroupLogs = [
  { id: 's-2', studentId: '2026-00126', attendeeName: 'Maria Santos (Student)', timestamp: '2026-11-05T18:45:00Z', timeIn: '6:45 PM' },
  { id: 'o-2', outsiderId: 'out-2', attendeeName: 'Carlos Guest (Outsider)', timestamp: '2026-11-05T18:50:00Z', timeIn: '6:50 PM' },
  { id: 's-1', studentId: '2026-00125', attendeeName: 'Alex Student', timestamp: '2026-11-05T18:30:00Z', timeIn: '6:30 PM' },
  { id: 'o-1', outsiderId: 'out-1', attendeeName: 'Beatriz Guest (Outsider)', timestamp: '2026-11-05T18:40:00Z', timeIn: '6:40 PM' }
];

const grouped = groupAndSortScanLogs(mixedGroupLogs, mockTickets, mockRoster, mockUsers);

assert.strictEqual(grouped.students.length, 2, 'Students group must contain 2 records');
assert.strictEqual(grouped.outsiders.length, 2, 'Outsiders group must contain 2 records');

assert.deepStrictEqual(
  grouped.students.map(l => l.attendeeName),
  ['Alex Student', 'Maria Santos (Student)'],
  'Students group must be sorted A-Z'
);

assert.deepStrictEqual(
  grouped.outsiders.map(l => l.attendeeName),
  ['Beatriz Guest (Outsider)', 'Carlos Guest (Outsider)'],
  'Outsiders group must be sorted A-Z'
);
// --- TEST 16: Preservation of Original Scan Timestamps and Fields ---
console.log('--- TEST 16: Preservation of Original Scan Timestamps and Fields ---');
const timestampLog = mixedGroupLogs[0];
assert.strictEqual(timestampLog.timestamp, '2026-11-05T18:45:00Z', 'Original ISO timestamp must remain unchanged');
assert.strictEqual(timestampLog.timeIn, '6:45 PM', 'Original formatted Time In must remain unchanged');
console.log('✅ TEST 16 PASSED: Timestamps and scan attendance metadata strictly preserved.\n');

// --- TEST 17: Unified Segmented View Filtering and Sorting ---
console.log('--- TEST 17: Unified Segmented View Filtering and Sorting ---');
function getDisplayedLogs(
  logs,
  filter,
  tickets = [],
  roster = [],
  users = []
) {
  let list = logs;
  if (filter === 'students') {
    list = list.filter(l => isStudentScanLog(l, tickets, roster, users));
  } else if (filter === 'outsiders') {
    list = list.filter(l => !isStudentScanLog(l, tickets, roster, users));
  }

  if (filter === 'students' || filter === 'outsiders') {
    return sortAlphabeticalByName(list);
  }
  return sortAttendanceLogs(list, 'newest');
}

// 1. "All" view displays all 4 logs, sorted newest first
const allView = getDisplayedLogs(mixedGroupLogs, 'all', mockTickets, mockRoster, mockUsers);
assert.strictEqual(allView.length, 4, 'All view must contain all 4 logs');
assert.deepStrictEqual(
  allView.map(l => l.id),
  ['o-2', 's-2', 'o-1', 's-1'], // 18:50, 18:45, 18:40, 18:30
  'All view must order newest scans first'
);

// 2. "Students" view displays only student scans sorted alphabetically A–Z
const studentsView = getDisplayedLogs(mixedGroupLogs, 'students', mockTickets, mockRoster, mockUsers);
assert.strictEqual(studentsView.length, 2, 'Students view must contain only students');
assert.deepStrictEqual(
  studentsView.map(l => l.attendeeName),
  ['Alex Student', 'Maria Santos (Student)'],
  'Students view must be sorted alphabetically A–Z'
);

// 3. "Outsiders" view displays only outsider scans sorted alphabetically A–Z
const outsidersView = getDisplayedLogs(mixedGroupLogs, 'outsiders', mockTickets, mockRoster, mockUsers);
assert.strictEqual(outsidersView.length, 2, 'Outsiders view must contain only outsiders');
assert.deepStrictEqual(
  outsidersView.map(l => l.attendeeName),
  ['Beatriz Guest (Outsider)', 'Carlos Guest (Outsider)'],
  'Outsiders view must be sorted alphabetically A–Z'
);

// 4. Arriving scan while on 'students' filter updates the list and maintains A-Z order
const newStudentLog = {
  id: 's-3',
  studentId: '2026-00127',
  attendeeName: 'Catherine Zeta',
  timestamp: '2026-11-05T19:05:00Z',
  timeIn: '7:05 PM'
};
const updatedWithNewScan = [...mixedGroupLogs, newStudentLog];
const studentsViewAfterNewScan = getDisplayedLogs(updatedWithNewScan, 'students', mockTickets, mockRoster, mockUsers);
assert.strictEqual(studentsViewAfterNewScan.length, 3, 'New student scan must be included');
assert.deepStrictEqual(
  studentsViewAfterNewScan.map(l => l.attendeeName),
  ['Alex Student', 'Catherine Zeta', 'Maria Santos (Student)'],
  'New student must be inserted in proper alphabetical A–Z order without resetting filter'
);
console.log('✅ TEST 17 PASSED: Unified segmented view filtering and presentational sorting verified.\n');

console.log('🎉 ALL 17 RECENT ENTRANCE SCANS SORTING & GROUPING TESTS PASSED SUCCESSFULLY!\n');

