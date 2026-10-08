import { readdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { execSync } from 'child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const allFiles = readdirSync(__dirname);
const testFiles = allFiles
  .filter(f => f.startsWith('test-') && f.endsWith('.mjs'))
  .sort();

console.log('========================================================');
console.log('  NCST FRONTEND LOGIC AUTOMATED TEST SUITE');
console.log(`  Discovered ${testFiles.length} test suites`);
console.log('========================================================\n');

let passedCount = 0;
let failedCount = 0;
const failures = [];

for (const testFile of testFiles) {
  const filePath = join(__dirname, testFile);
  const suiteName = testFile.replace('test-', '').replace('.mjs', '').replace(/-/g, ' ');

  try {
    const output = execSync(`node "${filePath}"`, {
      encoding: 'utf-8',
      stdio: ['ignore', 'pipe', 'pipe'],
      timeout: 15000,
    });

    console.log(`  [PASS] ${testFile} (${suiteName})`);
    passedCount++;
  } catch (err) {
    console.log(`  [FAIL] ${testFile} (${suiteName})`);
    const errorOutput = err.stderr || err.stdout || err.message;
    failures.push({ file: testFile, error: errorOutput });
    failedCount++;
  }
}

console.log('\n========================================================');
console.log(`  FRONTEND TEST SUMMARY: ${passedCount} / ${testFiles.length} Passed`);
if (failedCount === 0) {
  console.log('  STATUS: ALL FRONTEND TESTS PASSED (100% SUCCESS)');
  console.log('========================================================\n');
  process.exit(0);
} else {
  console.log(`  STATUS: ${failedCount} SUITES FAILED`);
  console.log('========================================================\n');
  for (const f of failures) {
    console.error(`\n--- Error in ${f.file} ---:\n${f.error.slice(0, 400)}...\n`);
  }
  process.exit(1);
}
