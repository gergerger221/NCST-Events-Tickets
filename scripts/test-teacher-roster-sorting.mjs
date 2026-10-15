import assert from 'assert';
import {
  sortAttendanceRoster
} from '../src/utils/nameUtils.ts';

console.log('=== RUNNING TEACHER ROSTER SORTING TEST SUITE ===\n');

// Mock Attendance Roster Dataset
const mockRoster = [
  {
    student: { studentId: '2024-00105', name: 'Maria Clara Santos', course: 'BSIT', section: '31M1' },
    isPresent: false,
    timeIn: null,
    attendanceStatus: 'not_entered'
  },
  {
    student: { studentId: '2024-00101', name: 'Juan Dela Cruz', course: 'BSIT', section: '31M1' },
    isPresent: true,
    timeIn: '2026-11-15T08:30:00Z',
    attendanceStatus: 'present'
  },
  {
    student: { studentId: '2024-00102', name: 'Zoren Zaide', course: 'BSIT', section: '31M1' },
    isPresent: true,
    timeIn: '2026-11-15T08:15:00Z',
    attendanceStatus: 'present'
  },
  {
    student: { studentId: '2024-00103', name: 'Benigno Aquino', course: 'BSIT', section: '31M1' },
    isPresent: false,
    timeIn: null,
    attendanceStatus: 'not_entered'
  },
  {
    student: { studentId: '2024-00104', name: 'Pedro De Los Santos', course: 'BSIT', section: '31M1' },
    isPresent: true,
    timeIn: '2026-11-15T09:00:00Z',
    attendanceStatus: 'present'
  }
];

// -----------------------------------------------------------------------------
// Test 1: Student Name (A–Z) — Default
// -----------------------------------------------------------------------------
console.log('1. Testing Student Name (A–Z) [Default: Surname A-Z, Given name tie-breaker]...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'name_asc');
  const names = sorted.map(i => i.student.name);

  // Surnames: Aquino, De Los Santos, Dela Cruz, Santos, Zaide
  assert.strictEqual(names[0], 'Benigno Aquino');
  assert.strictEqual(names[1], 'Pedro De Los Santos');
  assert.strictEqual(names[2], 'Juan Dela Cruz');
  assert.strictEqual(names[3], 'Maria Clara Santos');
  assert.strictEqual(names[4], 'Zoren Zaide');

  console.log('   ✓ Correctly sorted by surname A–Z with multi-word surname recognition.');
}

// -----------------------------------------------------------------------------
// Test 2: Student Name (Z–A)
// -----------------------------------------------------------------------------
console.log('2. Testing Student Name (Z–A)...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'name_desc');
  const names = sorted.map(i => i.student.name);

  // Surnames: Zaide, Santos, Dela Cruz, De Los Santos, Aquino
  assert.strictEqual(names[0], 'Zoren Zaide');
  assert.strictEqual(names[1], 'Maria Clara Santos');
  assert.strictEqual(names[2], 'Juan Dela Cruz');
  assert.strictEqual(names[3], 'Pedro De Los Santos');
  assert.strictEqual(names[4], 'Benigno Aquino');

  console.log('   ✓ Correctly sorted by surname Z–A.');
}

// -----------------------------------------------------------------------------
// Test 3: Student ID (Lowest first)
// -----------------------------------------------------------------------------
console.log('3. Testing Student ID (Lowest first)...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'id_asc');
  const ids = sorted.map(i => i.student.studentId);

  assert.deepStrictEqual(ids, [
    '2024-00101',
    '2024-00102',
    '2024-00103',
    '2024-00104',
    '2024-00105'
  ]);

  console.log('   ✓ Correctly sorted by Student ID in natural ascending order.');
}

// -----------------------------------------------------------------------------
// Test 4: Present Students First
// -----------------------------------------------------------------------------
console.log('4. Testing Present Students First (Tie-breaker: Surname A–Z)...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'status_present');
  const presentStatuses = sorted.map(i => i.isPresent);
  const names = sorted.map(i => i.student.name);

  // First 3 must be present, last 2 must be not entered
  assert.strictEqual(presentStatuses[0], true);
  assert.strictEqual(presentStatuses[1], true);
  assert.strictEqual(presentStatuses[2], true);
  assert.strictEqual(presentStatuses[3], false);
  assert.strictEqual(presentStatuses[4], false);

  // Present group tie-broken by surname: De Los Santos, Dela Cruz, Zaide
  assert.strictEqual(names[0], 'Pedro De Los Santos');
  assert.strictEqual(names[1], 'Juan Dela Cruz');
  assert.strictEqual(names[2], 'Zoren Zaide');

  // Not entered group tie-broken by surname: Aquino, Santos
  assert.strictEqual(names[3], 'Benigno Aquino');
  assert.strictEqual(names[4], 'Maria Clara Santos');

  console.log('   ✓ Present students placed first, with surname A–Z tie-breaking.');
}

// -----------------------------------------------------------------------------
// Test 5: Not Yet Entered First
// -----------------------------------------------------------------------------
console.log('5. Testing Not Yet Entered First (Tie-breaker: Surname A–Z)...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'status_not_entered');
  const presentStatuses = sorted.map(i => i.isPresent);
  const names = sorted.map(i => i.student.name);

  // First 2 must be not entered, last 3 must be present
  assert.strictEqual(presentStatuses[0], false);
  assert.strictEqual(presentStatuses[1], false);
  assert.strictEqual(presentStatuses[2], true);
  assert.strictEqual(presentStatuses[3], true);
  assert.strictEqual(presentStatuses[4], true);

  // Not entered tie-broken by surname: Aquino, Santos
  assert.strictEqual(names[0], 'Benigno Aquino');
  assert.strictEqual(names[1], 'Maria Clara Santos');

  // Present tie-broken by surname: De Los Santos, Dela Cruz, Zaide
  assert.strictEqual(names[2], 'Pedro De Los Santos');
  assert.strictEqual(names[3], 'Juan Dela Cruz');
  assert.strictEqual(names[4], 'Zoren Zaide');

  console.log('   ✓ Not yet entered students placed first, with surname A–Z tie-breaking.');
}

// -----------------------------------------------------------------------------
// Test 6: Time In (Earliest first)
// -----------------------------------------------------------------------------
console.log('6. Testing Time In (Earliest first; missing values placed consistently at end)...');
{
  const sorted = sortAttendanceRoster(mockRoster, 'time_in_earliest');
  const names = sorted.map(i => i.student.name);
  const times = sorted.map(i => i.timeIn);

  // 08:15 (Zaide) -> 08:30 (Dela Cruz) -> 09:00 (De Los Santos) -> Missing (Aquino, Santos)
  assert.strictEqual(names[0], 'Zoren Zaide');
  assert.strictEqual(times[0], '2026-11-15T08:15:00Z');

  assert.strictEqual(names[1], 'Juan Dela Cruz');
  assert.strictEqual(times[1], '2026-11-15T08:30:00Z');

  assert.strictEqual(names[2], 'Pedro De Los Santos');
  assert.strictEqual(times[2], '2026-11-15T09:00:00Z');

  assert.strictEqual(times[3], null);
  assert.strictEqual(times[4], null);
  // Missing records tie-broken by surname: Aquino, Santos
  assert.strictEqual(names[3], 'Benigno Aquino');
  assert.strictEqual(names[4], 'Maria Clara Santos');

  console.log('   ✓ Earliest scan time sorted first; missing time-in consistently placed at end.');
}

// -----------------------------------------------------------------------------
// Test 7: Integration with Search & Attendance Filters
// -----------------------------------------------------------------------------
console.log('7. Testing Integration of Sorting with Search and Status Filters...');
{
  // Filter present only
  const presentOnly = mockRoster.filter(i => i.isPresent);
  const sortedPresent = sortAttendanceRoster(presentOnly, 'id_asc');
  assert.strictEqual(sortedPresent.length, 3);
  assert.strictEqual(sortedPresent[0].student.studentId, '2024-00101');
  assert.strictEqual(sortedPresent[1].student.studentId, '2024-00102');
  assert.strictEqual(sortedPresent[2].student.studentId, '2024-00104');

  // Search filter matching 'Santos' (Maria Clara Santos, Pedro De Los Santos)
  const query = 'santos';
  const filtered = mockRoster.filter(i => i.student.name.toLowerCase().includes(query));
  const sortedQuery = sortAttendanceRoster(filtered, 'name_asc');
  assert.strictEqual(sortedQuery.length, 2);
  assert.strictEqual(sortedQuery[0].student.name, 'Pedro De Los Santos');
  assert.strictEqual(sortedQuery[1].student.name, 'Maria Clara Santos');

  console.log('   ✓ Sorting combines seamlessly with search and status filters.');
}

console.log('\n=== ALL TEACHER ROSTER SORTING TESTS PASSED (100% SUCCESS) ===\n');
