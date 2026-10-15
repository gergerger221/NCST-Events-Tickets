/**
 * Automated Regression Test Suite:
 * Philippine Time Formatting, Preformatted 12-Hour Times, and Workspace Resilience
 */

import assert from 'node:assert';
import {
  formatPhilippineTime,
  formatPhilippineDate,
  formatPhilippineFull,
  formatPhilippineExpiration,
  getPhilippineDateString,
  getPhilippineParts,
  parsePhilippineDateInput,
  PHILIPPINE_TIMEZONE,
} from '../src/utils/philippineTime.ts';

console.log('========================================================');
console.log('  PHILIPPINE TIME FORMATTING & WORKSPACE REGRESSION TESTS');
console.log('========================================================\n');

// -----------------------------------------------------------------------------
// Test 1: Valid ISO Timestamps
// -----------------------------------------------------------------------------
console.log('Test 1: Valid ISO timestamp parsing and PHT formatting');
// 2026-10-15T00:48:46.000Z in UTC is 08:48:46 AM PHT (+08:00)
const isoUtc = '2026-10-15T00:48:46.000Z';
const isoPhtTime = formatPhilippineTime(isoUtc);
assert.strictEqual(isoPhtTime, '08:48 AM', `Expected '08:48 AM', got '${isoPhtTime}'`);

const isoPhtDate = formatPhilippineDate(isoUtc);
assert.strictEqual(isoPhtDate, 'October 15, 2026', `Expected 'October 15, 2026', got '${isoPhtDate}'`);

const isoPhtFull = formatPhilippineFull(isoUtc);
assert.ok(isoPhtFull.includes('October 15, 2026'), 'Full format must include date');
assert.ok(isoPhtFull.includes('08:48:46 AM'), 'Full format must include exact 12-hour time with seconds');
console.log('  --> PASSED: Valid ISO timestamps render correctly in Philippine Time.\n');

// -----------------------------------------------------------------------------
// Test 2: Valid Supported SQL Datetime Strings (YYYY-MM-DD HH:mm:ss)
// -----------------------------------------------------------------------------
console.log('Test 2: Valid supported SQL datetime strings without timezone shift');
const sqlDatetime = '2026-10-15 08:48:46';
const sqlPhtTime = formatPhilippineTime(sqlDatetime);
assert.strictEqual(sqlPhtTime, '08:48 AM', `Expected '08:48 AM', got '${sqlPhtTime}'`);

const sqlPhtDate = formatPhilippineDate(sqlDatetime);
assert.strictEqual(sqlPhtDate, 'October 15, 2026', `Expected 'October 15, 2026', got '${sqlPhtDate}'`);

const sqlPhtFull = formatPhilippineFull(sqlDatetime);
assert.ok(sqlPhtFull.includes('October 15, 2026'), 'Full SQL format must include date');
assert.ok(sqlPhtFull.includes('08:48:46 AM'), 'Full SQL format must include exact 12-hour time');
console.log('  --> PASSED: SQL datetime strings parse as PHT and format without error.\n');

// -----------------------------------------------------------------------------
// Test 3: Preformatted 12-Hour Display Time (e.g. '08:48 AM')
// -----------------------------------------------------------------------------
console.log('Test 3: Preformatted display time (e.g. 08:48 AM) preserved without throwing');
const displayTime = '08:48 AM';
const timeFormatted = formatPhilippineTime(displayTime);
assert.strictEqual(timeFormatted, '08:48 AM', `Expected '08:48 AM', got '${timeFormatted}'`);

const fullFormatted = formatPhilippineFull(displayTime);
assert.strictEqual(fullFormatted, '08:48 AM PHT', `Expected '08:48 AM PHT', got '${fullFormatted}'`);

const dateFormattedFromTime = formatPhilippineDate(displayTime);
assert.strictEqual(dateFormattedFromTime, '—', `Time-only string must return neutral fallback for date, got '${dateFormattedFromTime}'`);
console.log('  --> PASSED: Preformatted 12-hour times handled safely without crashing new Date().\n');

// -----------------------------------------------------------------------------
// Test 4: Null, Undefined, Empty, and Malformed Timestamps
// -----------------------------------------------------------------------------
console.log('Test 4: Null, undefined, empty, and malformed timestamps return neutral fallbacks');
const invalidInputs = [null, undefined, '', '   ', '---', '—', 'invalid-time', 'not_a_date', NaN, {}, []];

for (const input of invalidInputs) {
  assert.doesNotThrow(() => {
    const t = formatPhilippineTime(input);
    assert.strictEqual(t, '—', `formatPhilippineTime for ${input} must return '—', got '${t}'`);
  }, `formatPhilippineTime must not throw for ${input}`);

  assert.doesNotThrow(() => {
    const d = formatPhilippineDate(input);
    assert.strictEqual(d, '—', `formatPhilippineDate for ${input} must return '—', got '${d}'`);
  }, `formatPhilippineDate must not throw for ${input}`);

  assert.doesNotThrow(() => {
    const f = formatPhilippineFull(input);
    assert.strictEqual(f, '—', `formatPhilippineFull for ${input} must return '—', got '${f}'`);
  }, `formatPhilippineFull must not throw for ${input}`);
}
console.log('  --> PASSED: All invalid inputs return neutral fallbacks without throwing.\n');

// -----------------------------------------------------------------------------
// Test 5: Malformed Attendance Record Alongside Valid Records
// -----------------------------------------------------------------------------
console.log('Test 5: Malformed attendance record alongside valid records does not crash workspace');

const mockTickets = [
  {
    id: 'TIX-VALID-01',
    ticketNumber: 'TIX-VALID-01',
    eventId: 'evt-1',
    eventTitle: 'Valid Event',
    purchaseDate: '2026-10-15T00:00:00.000Z',
    ticketStatus: 'used',
    attendanceStatus: 'present',
    timeIn: '08:48 AM',
    timeInIso: '2026-10-15 08:48:46'
  },
  {
    id: 'TIX-MALFORMED-02',
    ticketNumber: 'TIX-MALFORMED-02',
    eventId: 'evt-1',
    eventTitle: 'Malformed Timestamp Event',
    purchaseDate: 'corrupt-date-string',
    ticketStatus: 'used',
    attendanceStatus: 'present',
    timeIn: 'completely_invalid_time_value',
    timeInIso: null
  },
  {
    id: 'TIX-VALID-03',
    ticketNumber: 'TIX-VALID-03',
    eventId: 'evt-2',
    eventTitle: 'Another Valid Event',
    purchaseDate: '2026-10-16',
    ticketStatus: 'issued',
    attendanceStatus: 'not_entered',
    timeIn: null,
    timeInIso: null
  }
];

const mockLogs = [
  {
    ticketId: 'TIX-VALID-01',
    status: 'valid',
    timeIn: '08:48 AM',
    timestamp: '2026-10-15 08:48:46'
  },
  {
    ticketId: 'TIX-MALFORMED-02',
    status: 'valid',
    timeIn: 'corrupt_log_time',
    timestamp: 'invalid_iso'
  }
];

function processStudentAttendanceRecords(tickets, logs) {
  return tickets.map(ticket => {
    try {
      const log = logs.find(l => l.ticketId === ticket.id && l.status === 'valid');
      const isPresent = !!log || (ticket.attendanceStatus === 'present' && !!ticket.timeIn);
      const recordedTimestamp = log?.timestamp || ticket.timeInIso || ticket.timeIn || log?.timeIn || null;
      const timeInFormatted = log?.timeIn ? formatPhilippineTime(log.timeIn) : (ticket.timeIn ? formatPhilippineTime(ticket.timeIn) : null);

      return {
        id: ticket.id,
        ticketNumber: ticket.ticketNumber,
        ticketStatus: ticket.ticketStatus,
        attendanceStatus: isPresent ? 'present' : 'not_entered',
        recordedTime: recordedTimestamp ? formatPhilippineFull(recordedTimestamp) : null,
        timeInFormatted,
      };
    } catch (err) {
      return {
        id: ticket?.id || 'err-tix',
        ticketNumber: ticket?.ticketNumber || '—',
        ticketStatus: ticket?.ticketStatus || 'issued',
        attendanceStatus: 'not_entered',
        recordedTime: null,
        timeInFormatted: null,
      };
    }
  });
}

const records = processStudentAttendanceRecords(mockTickets, mockLogs);
assert.strictEqual(records.length, 3, 'All 3 records must be processed without throwing');
assert.strictEqual(records[0].timeInFormatted, '08:48 AM', 'Valid record 1 timeInFormatted must be 08:48 AM');
assert.strictEqual(records[0].attendanceStatus, 'present', 'Valid record 1 must be present');

assert.strictEqual(records[1].timeInFormatted, '—', 'Malformed record 2 must degrade gracefully to neutral fallback');
assert.strictEqual(records[1].recordedTime, '—', 'Malformed record 2 recordedTime must degrade gracefully');

assert.strictEqual(records[2].timeInFormatted, null, 'Unscanned record 3 timeInFormatted must be null');
assert.strictEqual(records[2].attendanceStatus, 'not_entered', 'Unscanned record 3 must be not_entered');
console.log('  --> PASSED: Malformed attendance records do not crash or corrupt sibling records.\n');

// -----------------------------------------------------------------------------
// Test 6: Workspace Render with Missing or Invalid Optional Time Fields
// -----------------------------------------------------------------------------
console.log('Test 6: Workspace render with missing or invalid optional time fields');
const partialTicket = {
  id: 'TIX-EMPTY-04',
  ticketNumber: 'TIX-EMPTY-04'
  // Missing all time fields: purchaseDate, timeIn, timeInIso
};

const partialRecords = processStudentAttendanceRecords([partialTicket], []);
assert.strictEqual(partialRecords[0].id, 'TIX-EMPTY-04');
assert.strictEqual(partialRecords[0].recordedTime, null);
assert.strictEqual(partialRecords[0].timeInFormatted, null);
console.log('  --> PASSED: Records with omitted optional time fields render safely.\n');

// -----------------------------------------------------------------------------
// Test 7: Correct Philippine Time Display and No Timezone-Induced Time Shift
// -----------------------------------------------------------------------------
console.log('Test 7: Philippine Time formatting guarantees UTC+8 without time shift');
// Test 8:00 AM PHT explicitly
const morningPhtIso = '2026-10-15T00:00:00.000Z'; // 00:00 UTC = 08:00 AM PHT
const morningFormatted = formatPhilippineTime(morningPhtIso);
assert.strictEqual(morningFormatted, '08:00 AM', `Expected '08:00 AM', got '${morningFormatted}'`);

// Test 11:59 PM PHT explicitly
const nightPhtIso = '2026-10-15T15:59:00.000Z'; // 15:59 UTC = 11:59 PM PHT
const nightFormatted = formatPhilippineTime(nightPhtIso);
assert.strictEqual(nightFormatted, '11:59 PM', `Expected '11:59 PM', got '${nightFormatted}'`);

// Date-only string YYYY-MM-DD must never shift across calendar day boundaries
const calendarDate = '2026-10-15';
const calendarDateFormatted = formatPhilippineDate(calendarDate);
assert.strictEqual(calendarDateFormatted, 'October 15, 2026', `Expected 'October 15, 2026', got '${calendarDateFormatted}'`);

const calendarDatePhtString = getPhilippineDateString(calendarDate);
assert.strictEqual(calendarDatePhtString, '2026-10-15', `Expected '2026-10-15', got '${calendarDatePhtString}'`);
console.log('  --> PASSED: No timezone-induced shifts occur across calendar boundaries.\n');

console.log('========================================================');
console.log('  ALL 7 PHILIPPINE TIME REGRESSION TESTS PASSED (100%)');
console.log('========================================================');
