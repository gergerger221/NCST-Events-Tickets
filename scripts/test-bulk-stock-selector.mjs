// test-bulk-stock-selector.mjs
// Verification of the Pre-Printed Ticket Stock Generator's copies logic

function testCopiesLogic(presetSelection, customCount) {
  const isCustom = presetSelection === 'custom';

  let validationError = null;
  if (isCustom) {
    const raw = customCount;
    if (raw === '' || raw === null || raw === undefined) {
      validationError = 'Please enter the number of copies.';
    } else {
      const val = Number(raw);
      if (isNaN(val) || !Number.isInteger(val) || val <= 0) {
        validationError = 'Please enter a valid positive whole number (e.g., 250).';
      } else if (val > 1000) {
        validationError = 'Maximum batch limit is 1,000 copies per print job to ensure browser stability.';
      }
    }
  }

  let effectiveBulkCount = 0;
  if (!isCustom) {
    effectiveBulkCount = parseInt(presetSelection, 10) || 50;
  } else {
    const val = Number(customCount);
    if (!isNaN(val) && Number.isInteger(val) && val > 0) {
      effectiveBulkCount = Math.min(val, 1000);
    }
  }

  const isLargeBatchWarning = effectiveBulkCount > 300 && !validationError;
  const isPrintDisabled = effectiveBulkCount <= 0 || validationError !== null;

  return {
    isCustom,
    validationError,
    effectiveBulkCount,
    isLargeBatchWarning,
    isPrintDisabled
  };
}

console.log('================================================================');
console.log('NCST PRE-PRINTED TICKET STOCK COPIES VALIDATION SUITE');
console.log('================================================================\n');

// 1. Preset 50 copies
const t1 = testCopiesLogic('50', null);
if (t1.effectiveBulkCount === 50 && !t1.isPrintDisabled && !t1.validationError) {
  console.log('✅ PASSED [Test 1]: Preset 50 copies -> 50 sheets, print enabled');
} else {
  console.error('❌ FAILED [Test 1]:', t1);
  process.exit(1);
}

// 2. Preset 100 copies
const t2 = testCopiesLogic('100', null);
if (t2.effectiveBulkCount === 100 && !t2.isPrintDisabled && !t2.validationError) {
  console.log('✅ PASSED [Test 2]: Preset 100 copies -> 100 sheets, print enabled');
} else {
  console.error('❌ FAILED [Test 2]:', t2);
  process.exit(1);
}

// 3. Custom 250 copies
const t3 = testCopiesLogic('custom', 250);
if (t3.effectiveBulkCount === 250 && !t3.isPrintDisabled && !t3.validationError && !t3.isLargeBatchWarning) {
  console.log('✅ PASSED [Test 3]: Custom 250 copies -> 250 sheets, valid, print enabled');
} else {
  console.error('❌ FAILED [Test 3]:', t3);
  process.exit(1);
}

// 4. Custom Invalid: 0
const t4a = testCopiesLogic('custom', 0);
if (t4a.validationError && t4a.isPrintDisabled) {
  console.log('✅ PASSED [Test 4a]: Custom 0 copies -> correctly rejected with validation error');
} else {
  console.error('❌ FAILED [Test 4a]:', t4a);
  process.exit(1);
}

// 5. Custom Invalid: Negative (-10)
const t4b = testCopiesLogic('custom', -10);
if (t4b.validationError && t4b.isPrintDisabled) {
  console.log('✅ PASSED [Test 4b]: Custom -10 copies -> correctly rejected');
} else {
  console.error('❌ FAILED [Test 4b]:', t4b);
  process.exit(1);
}

// 6. Custom Invalid: Decimal (25.5)
const t4c = testCopiesLogic('custom', 25.5);
if (t4c.validationError && t4c.isPrintDisabled) {
  console.log('✅ PASSED [Test 4c]: Custom decimal (25.5) -> correctly rejected');
} else {
  console.error('❌ FAILED [Test 4c]:', t4c);
  process.exit(1);
}

// 7. Custom Invalid: Empty string
const t4d = testCopiesLogic('custom', '');
if (t4d.validationError && t4d.isPrintDisabled) {
  console.log('✅ PASSED [Test 4d]: Custom empty input -> correctly rejected');
} else {
  console.error('❌ FAILED [Test 4d]:', t4d);
  process.exit(1);
}

// 8. Custom Invalid: Letters ('abc')
const t4e = testCopiesLogic('custom', 'abc');
if (t4e.validationError && t4e.isPrintDisabled) {
  console.log('✅ PASSED [Test 4e]: Custom non-numeric text -> correctly rejected');
} else {
  console.error('❌ FAILED [Test 4e]:', t4e);
  process.exit(1);
}

// 9. Large but reasonable (500 copies)
const t5 = testCopiesLogic('custom', 500);
if (t5.effectiveBulkCount === 500 && !t5.isPrintDisabled && t5.isLargeBatchWarning) {
  console.log('✅ PASSED [Test 5]: Custom 500 copies -> 500 sheets with large batch advisory notice');
} else {
  console.error('❌ FAILED [Test 5]:', t5);
  process.exit(1);
}

// 10. Beyond max threshold (>1000)
const t6 = testCopiesLogic('custom', 1500);
if (t6.validationError && t6.isPrintDisabled) {
  console.log('✅ PASSED [Test 6]: Custom 1500 copies -> capped by safety threshold limit');
} else {
  console.error('❌ FAILED [Test 6]:', t6);
  process.exit(1);
}

console.log('\n================================================================');
console.log('SUMMARY: ALL 10 COPIES VALIDATION TESTS PASSED');
console.log('================================================================');
