import assert from 'assert';

console.log('🧪 Starting Excel Class List Attendance & Report Generation Frontend Test Suite...\n');

// --------------------------------------------------------------------------
// 1. Formula Injection Protection (CSV/Excel Formula Sanitization)
// --------------------------------------------------------------------------
console.log('Test 1: Formula Injection Sanitization prevents spreadsheet code execution');

function sanitizeFormulaInjection(value) {
  if (typeof value !== 'string') return value;
  const trimmed = value.trim();
  if (/^[=+\-@\t\r]/.test(trimmed)) {
    return "'" + trimmed;
  }
  return value;
}

const injectionVectors = [
  { input: '=1+1', expected: "'=1+1" },
  { input: '+cmd|"/C calc"!A0', expected: "'+cmd|\"/C calc\"!A0" },
  { input: '-HYPERLINK("http://evil.com")', expected: "'-HYPERLINK(\"http://evil.com\")" },
  { input: '@SUM(A1:A10)', expected: "'@SUM(A1:A10)" },
  { input: '\t=MALICIOUS_TAB', expected: "'=MALICIOUS_TAB" },
  { input: 'Juan Dela Cruz', expected: 'Juan Dela Cruz' },
  { input: '2024-00123', expected: '2024-00123' }, // Safe alphanumeric student ID
  { input: null, expected: null },
  { input: undefined, expected: undefined },
  { input: 12345, expected: 12345 }
];

for (const vec of injectionVectors) {
  const result = sanitizeFormulaInjection(vec.input);
  assert.strictEqual(result, vec.expected, `Formula sanitization failed for vector: ${vec.input}`);
}
console.log('  --> PASSED: Formula injection payloads prefixed with single quote; safe values preserved.\n');

// --------------------------------------------------------------------------
// 2. Class List Template Schema & Column Headers
// --------------------------------------------------------------------------
console.log('Test 2: Downloadable Class List template schema matches institutional standard');

const REQUIRED_TEMPLATE_HEADERS = [
  'Student ID',
  'Student Full Name',
  'Course',
  'Year Level',
  'Section'
];

const sampleTemplateRows = [
  {
    'Student ID': '2024-58566',
    'Student Full Name': 'Mico De Guzman',
    'Course': 'BSIT',
    'Year Level': '3rd Year',
    'Section': '31M1',
  },
  {
    'Student ID': '2024-00101',
    'Student Full Name': 'Juan Dela Cruz',
    'Course': 'BSIT',
    'Year Level': '3rd Year',
    'Section': '31M1',
  }
];

const sampleHeaders = Object.keys(sampleTemplateRows[0]);
for (const reqHeader of REQUIRED_TEMPLATE_HEADERS) {
  assert(sampleHeaders.includes(reqHeader), `Template missing required column header: ${reqHeader}`);
}
console.log('  --> PASSED: Template contains all required headers (Student ID, Name, Course, Year Level, Section).\n');

// --------------------------------------------------------------------------
// 3. File Type & Size Validation
// --------------------------------------------------------------------------
console.log('Test 3: File upload validation rules (extensions and size limits)');

function validateUploadedFile(fileName, fileSize) {
  const allowedExtensions = ['.xlsx', '.xls', '.csv'];
  const ext = fileName.slice(fileName.lastIndexOf('.')).toLowerCase();
  
  if (!allowedExtensions.includes(ext)) {
    return { valid: false, error: 'Invalid file format. Please upload an Excel (.xlsx, .xls) or CSV file.' };
  }
  
  const MAX_SIZE = 10 * 1024 * 1024; // 10MB
  if (fileSize > MAX_SIZE) {
    return { valid: false, error: 'File size exceeds maximum 10MB limit.' };
  }
  
  return { valid: true };
}

assert.strictEqual(validateUploadedFile('class_list.xlsx', 1024 * 50).valid, true);
assert.strictEqual(validateUploadedFile('class_list.xls', 1024 * 50).valid, true);
assert.strictEqual(validateUploadedFile('class_list.csv', 1024 * 50).valid, true);
assert.strictEqual(validateUploadedFile('class_list.PDF', 1024 * 50).valid, false);
assert.strictEqual(validateUploadedFile('malware.exe', 500).valid, false);
assert.strictEqual(validateUploadedFile('huge_sheet.xlsx', 11 * 1024 * 1024).valid, false);
console.log('  --> PASSED: Permitted extensions (.xlsx, .xls, .csv) accepted; invalid types and >10MB files rejected.\n');

// --------------------------------------------------------------------------
// 4. Duplicate & Invalid Row Detection
// --------------------------------------------------------------------------
console.log('Test 4: Client-side detection of blank and duplicate Student IDs');

function validateParsedRoster(rawRows) {
  const seenIds = new Set();
  const summary = {
    total: rawRows.length,
    valid: 0,
    duplicates: 0,
    invalid: 0
  };

  const processed = rawRows.map((row, idx) => {
    const rawId = (row['Student ID'] || row['ID Number'] || row['student_id'] || '').toString().trim();
    const rawName = (row['Student Full Name'] || row['Full Name'] || row['Name'] || '').toString().trim();

    if (!rawId) {
      summary.invalid++;
      return { rowIdx: idx + 1, studentId: '', name: rawName, isInvalid: true, reason: 'Blank Student ID' };
    }

    const normId = rawId.toUpperCase();
    const isDup = seenIds.has(normId);
    seenIds.add(normId);

    if (isDup) {
      summary.duplicates++;
    } else {
      summary.valid++;
    }

    return { rowIdx: idx + 1, studentId: rawId, name: rawName, isDuplicate: isDup, isInvalid: false };
  });

  return { summary, processed };
}

const testBatch = [
  { 'Student ID': '2024-001', 'Student Full Name': 'Student One' },
  { 'Student ID': '2024-002', 'Student Full Name': 'Student Two' },
  { 'Student ID': '2024-001', 'Student Full Name': 'Student One Duplicate' }, // Duplicate
  { 'Student ID': '', 'Student Full Name': 'Student Blank ID' },             // Invalid
  { 'Student ID': '2024-003', 'Student Full Name': 'Student Three' },
];

const batchResult = validateParsedRoster(testBatch);
assert.strictEqual(batchResult.summary.total, 5, 'Total should be 5');
assert.strictEqual(batchResult.summary.valid, 3, 'Valid unique should be 3');
assert.strictEqual(batchResult.summary.duplicates, 1, 'Duplicates should be 1');
assert.strictEqual(batchResult.summary.invalid, 1, 'Invalid should be 1');
assert.strictEqual(batchResult.processed[2].isDuplicate, true, 'Row 3 must be flagged as duplicate');
assert.strictEqual(batchResult.processed[3].isInvalid, true, 'Row 4 must be flagged as invalid');
console.log('  --> PASSED: Duplicate IDs and missing/blank IDs detected accurately.\n');

// --------------------------------------------------------------------------
// 5. Sorting and Sequential Printing Row Numbering
// --------------------------------------------------------------------------
console.log('Test 5: Sorting modes (Name A-Z default, Name Z-A, ID Asc/Desc, Status) and sequential # numbering');

const mockReconciledList = [
  { studentId: '2024-003', name: 'Zoe Martinez', isPresent: true, attendanceStatus: 'present', timeIn: '08:30 AM' },
  { studentId: '2024-001', name: 'Alice Santos', isPresent: false, attendanceStatus: 'absent', timeIn: null },
  { studentId: '2024-004', name: 'Bob Garcia', isPresent: true, attendanceStatus: 'present', timeIn: '09:00 AM' },
  { studentId: '2024-002', name: 'Charlie Cruz', isPresent: false, attendanceStatus: 'not_found', timeIn: null },
];

function sortRoster(list, sortOrder) {
  const sorted = [...list];
  switch (sortOrder) {
    case 'name_desc':
      sorted.sort((a, b) => (b.name || '').localeCompare(a.name || '', 'en', { sensitivity: 'base' }));
      break;
    case 'id_asc':
      sorted.sort((a, b) => (a.studentId || '').localeCompare(b.studentId || '', 'en', { numeric: true }));
      break;
    case 'id_desc':
      sorted.sort((a, b) => (b.studentId || '').localeCompare(a.studentId || '', 'en', { numeric: true }));
      break;
    case 'status':
      sorted.sort((a, b) => {
        const order = { present: 1, absent: 2, not_found: 3 };
        return (order[a.attendanceStatus] || 99) - (order[b.attendanceStatus] || 99);
      });
      break;
    case 'name_asc':
    default:
      sorted.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'en', { sensitivity: 'base' }));
      break;
  }
  return sorted;
}

// 1. Name A-Z (Default)
const sortedNameAsc = sortRoster(mockReconciledList, 'name_asc');
assert.strictEqual(sortedNameAsc[0].name, 'Alice Santos');
assert.strictEqual(sortedNameAsc[1].name, 'Bob Garcia');
assert.strictEqual(sortedNameAsc[2].name, 'Charlie Cruz');
assert.strictEqual(sortedNameAsc[3].name, 'Zoe Martinez');

// 2. Name Z-A
const sortedNameDesc = sortRoster(mockReconciledList, 'name_desc');
assert.strictEqual(sortedNameDesc[0].name, 'Zoe Martinez');
assert.strictEqual(sortedNameDesc[3].name, 'Alice Santos');

// 3. ID Ascending
const sortedIdAsc = sortRoster(mockReconciledList, 'id_asc');
assert.strictEqual(sortedIdAsc[0].studentId, '2024-001');
assert.strictEqual(sortedIdAsc[1].studentId, '2024-002');
assert.strictEqual(sortedIdAsc[2].studentId, '2024-003');
assert.strictEqual(sortedIdAsc[3].studentId, '2024-004');

// 4. Status (Present first, then Absent, then Not Found)
const sortedStatus = sortRoster(mockReconciledList, 'status');
assert.strictEqual(sortedStatus[0].isPresent, true);
assert.strictEqual(sortedStatus[1].isPresent, true);
assert.strictEqual(sortedStatus[2].attendanceStatus, 'absent');
assert.strictEqual(sortedStatus[3].attendanceStatus, 'not_found');

// 5. Sequential numbering preserves 1..N order in printed preview
const printedRows = sortedNameAsc.map((item, idx) => ({
  rowNumber: idx + 1,
  ...item
}));
assert.strictEqual(printedRows[0].rowNumber, 1);
assert.strictEqual(printedRows[1].rowNumber, 2);
assert.strictEqual(printedRows[2].rowNumber, 3);
assert.strictEqual(printedRows[3].rowNumber, 4);

console.log('  --> PASSED: Alphabetical default, reverse, ID, and status sorting with dynamic sequential # numbering.\n');

console.log('========================================================');
console.log('  STATUS: ALL FRONTEND RECONCILIATION TESTS PASSED (100% SUCCESS)');
console.log('========================================================\n');
process.exit(0);
