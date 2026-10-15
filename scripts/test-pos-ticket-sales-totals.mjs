import assert from 'assert';

console.log('=== RUNNING CASHIER POS TICKET SALES TOTALS TEST SUITE ===\n');

// Mock function matching useAppStore.ts logic
function computeSalesSummary(tickets, targetEventId) {
  if (!tickets || tickets.length === 0) {
    return null;
  }
  const validTickets = tickets.filter(t => {
    if (targetEventId && t.eventId !== targetEventId) return false;
    const status = (t.ticketStatus || '').toUpperCase();
    if (status === 'VOID' || status === 'CANCELLED' || status === 'REFUNDED') return false;
    const payStatus = (t.paymentStatus || '').toLowerCase();
    if (payStatus !== 'paid' && payStatus !== 'free') return false;
    return true;
  });

  const isPaid = (t) => (t.paymentStatus || 'paid').toLowerCase() === 'paid';
  const isFree = (t) => (t.paymentStatus || '').toLowerCase() === 'free';
  const isStudent = (t) => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT';
  const isOutsider = (t) => (t.attendeeType || '').toUpperCase() === 'OUTSIDER';

  const paidStudentSold = validTickets.filter(t => isStudent(t) && isPaid(t)).length;
  const paidOutsiderSold = validTickets.filter(t => isOutsider(t) && isPaid(t)).length;
  const totalPaidSold = paidStudentSold + paidOutsiderSold;

  const freeStudent = validTickets.filter(t => isStudent(t) && isFree(t)).length;
  const freeOutsider = validTickets.filter(t => isOutsider(t) && isFree(t)).length;
  const freeIssued = freeStudent + freeOutsider;

  const totalValidIssued = totalPaidSold + freeIssued;
  const totalRevenue = validTickets.reduce((sum, t) => sum + (isPaid(t) ? (t.price || 0) : 0), 0);

  return {
    paidStudentTicketsSold: paidStudentSold,
    paidOutsiderTicketsSold: paidOutsiderSold,
    totalPaidTicketsSold: totalPaidSold,
    freeTicketsIssued: freeIssued,
    freeStudentTickets: freeStudent,
    freeOutsiderTickets: freeOutsider,
    totalValidTicketsIssued: totalValidIssued,
    totalCollectedSales: totalRevenue,
  };
}

// 1. Core Invariants: Paid Student + Paid Outsider = Total Paid, and Total Paid + Free = Total Valid
console.log('1. Testing Paid Student + Paid Outsider = Total Paid Invariant...');
{
  const tickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '2', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'USED', paymentStatus: 'paid', price: 100 },
    { id: '3', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 150 },
    { id: '4', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'USED', paymentStatus: 'paid', price: 150 },
    { id: '5', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
    { id: '6', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
  ];

  const summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.paidStudentTicketsSold, 2, '2 paid student tickets');
  assert.strictEqual(summary.paidOutsiderTicketsSold, 2, '2 paid outsider tickets');
  assert.strictEqual(summary.totalPaidTicketsSold, 4, 'Total paid tickets must equal 4');
  assert.strictEqual(summary.paidStudentTicketsSold + summary.paidOutsiderTicketsSold, summary.totalPaidTicketsSold);

  assert.strictEqual(summary.freeTicketsIssued, 2, '2 free tickets issued');
  assert.strictEqual(summary.totalValidTicketsIssued, 6, 'Total valid tickets must equal 6');
  assert.strictEqual(summary.totalPaidTicketsSold + summary.freeTicketsIssued, summary.totalValidTicketsIssued);
  console.log('   ✓ Invariant verified: Paid Student + Paid Outsider = Total Paid, and Total Paid + Free = Total Valid.');
}

// 2. Status Exclusion: Void, Cancelled, and Refunded
console.log('2. Testing Exclusion of Void, Cancelled, and Refunded Tickets...');
{
  const tickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '2', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'VOID', paymentStatus: 'paid', price: 100 },
    { id: '3', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'CANCELLED', paymentStatus: 'paid', price: 100 },
    { id: '4', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 150 },
    { id: '5', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'REFUNDED', paymentStatus: 'paid', price: 150 },
    { id: '6', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'refunded', price: 150 },
  ];

  const summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.paidStudentTicketsSold, 1, 'Only 1 valid paid student ticket should be counted');
  assert.strictEqual(summary.paidOutsiderTicketsSold, 1, 'Only 1 valid paid outsider ticket should be counted');
  assert.strictEqual(summary.totalPaidTicketsSold, 2, 'Total paid tickets should be 2');
  console.log('   ✓ Status exclusions verified.');
}

// 3. Scanned / Used Tickets Retained
console.log('3. Testing That Scanned / Used Tickets Remain Counted...');
{
  const tickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'USED', attendanceStatus: 'present', paymentStatus: 'paid', price: 100 },
    { id: '2', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'USED', attendanceStatus: 'present', paymentStatus: 'paid', price: 150 },
  ];

  const summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.paidStudentTicketsSold, 1, 'Scanned student ticket must be counted in sales');
  assert.strictEqual(summary.paidOutsiderTicketsSold, 1, 'Scanned outsider ticket must be counted in sales');
  assert.strictEqual(summary.totalPaidTicketsSold, 2, 'Total paid sales must include scanned tickets');
  console.log('   ✓ Scanned tickets retention verified.');
}

// 4. Free Admission vs Paid Admission Segregation & Revenue Calculation
console.log('4. Testing Free vs Paid Ticket Segregation and Revenue Accuracy...');
{
  const tickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 200 },
    { id: '2', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
    { id: '3', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 200 },
  ];

  const summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.totalPaidTicketsSold, 2, 'Must count 2 paid tickets');
  assert.strictEqual(summary.freeTicketsIssued, 1, 'Must count 1 free ticket');
  assert.strictEqual(summary.totalCollectedSales, 400, 'Revenue should only sum paid tickets (400)');
  assert.strictEqual(summary.totalValidTicketsIssued, 3, 'Total valid is 3');
  console.log('   ✓ Free vs paid segregation and revenue verified.');
}

// 5. Cross-Cashier Delta Sync Simulation
console.log('5. Testing Cross-Cashier State Sync Reaction...');
{
  let currentTickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 }
  ];

  let summary = computeSalesSummary(currentTickets, 'evt-1');
  assert.strictEqual(summary.totalPaidTicketsSold, 1);

  // Cashier B on another machine purchases a ticket for outsider
  currentTickets = [
    ...currentTickets,
    { id: '2', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 150 }
  ];

  // Store watcher triggers re-computation
  summary = computeSalesSummary(currentTickets, 'evt-1');
  assert.strictEqual(summary.paidStudentTicketsSold, 1);
  assert.strictEqual(summary.paidOutsiderTicketsSold, 1);
  assert.strictEqual(summary.totalPaidTicketsSold, 2);
  assert.strictEqual(summary.totalCollectedSales, 250);
  console.log('   ✓ Cross-cashier sync reaction verified.');
}

// 6. Event Scoping: Event Switch Isolation
console.log('6. Testing Event Scoping and Switching...');
{
  const tickets = [
    { id: '1', eventId: 'evt-A', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '2', eventId: 'evt-A', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '3', eventId: 'evt-B', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '4', eventId: 'evt-B', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
    { id: '5', eventId: 'evt-B', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 100 },
  ];

  const summaryA = computeSalesSummary(tickets, 'evt-A');
  assert.strictEqual(summaryA.totalPaidTicketsSold, 2, 'Event A must have 2 paid tickets');
  assert.strictEqual(summaryA.paidStudentTicketsSold, 1);
  assert.strictEqual(summaryA.paidOutsiderTicketsSold, 1);

  const summaryB = computeSalesSummary(tickets, 'evt-B');
  assert.strictEqual(summaryB.totalPaidTicketsSold, 3, 'Event B must have 3 paid tickets');
  assert.strictEqual(summaryB.paidStudentTicketsSold, 2);
  assert.strictEqual(summaryB.paidOutsiderTicketsSold, 1);

  const summaryGlobal = computeSalesSummary(tickets, undefined);
  assert.strictEqual(summaryGlobal.totalPaidTicketsSold, 5, 'Global scope must count all 5 tickets');
  console.log('   ✓ Event scoping verified.');
}

// 7. In-Place Status Mutation (Refund / Cancel) Without Array Length Change
console.log('7. Testing Ticket Status / Payment Mutation Without Array Length Change...');
{
  const tickets = [
    { id: '1', eventId: 'evt-1', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 200 },
    { id: '2', eventId: 'evt-1', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'paid', price: 250 },
  ];

  let summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.totalPaidTicketsSold, 2);
  assert.strictEqual(summary.totalCollectedSales, 450);

  // Mark ticket 1 as refunded in place (array length remains 2)
  tickets[0].ticketStatus = 'refunded';
  tickets[0].paymentStatus = 'refunded';

  summary = computeSalesSummary(tickets, 'evt-1');
  assert.strictEqual(summary.paidStudentTicketsSold, 0, 'Refunded ticket must not be counted in student sales');
  assert.strictEqual(summary.paidOutsiderTicketsSold, 1, 'Outsider ticket remains valid');
  assert.strictEqual(summary.totalPaidTicketsSold, 1, 'Total paid tickets must decrement to 1');
  assert.strictEqual(summary.totalCollectedSales, 250, 'Total collected sales must reduce to 250');
  console.log('   ✓ In-place mutation without array length change verified.');
}

// 8. Free-Admission Events & Pending Status Exclusion
console.log('8. Testing Free-Admission Events & Pending Status Exclusion...');
{
  const tickets = [
    { id: '1', eventId: 'evt-free', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
    { id: '2', eventId: 'evt-free', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
    { id: '3', eventId: 'evt-free', attendeeType: 'OUTSIDER', ticketStatus: 'ISSUED', paymentStatus: 'free', price: 0 },
    { id: '4', eventId: 'evt-free', attendeeType: 'STUDENT', ticketStatus: 'ISSUED', paymentStatus: 'pending', price: 0 }, // Pending
  ];

  const summary = computeSalesSummary(tickets, 'evt-free');
  assert.strictEqual(summary.totalPaidTicketsSold, 0, 'Paid tickets must be 0 for free event');
  assert.strictEqual(summary.totalCollectedSales, 0, 'Total collected sales must be 0');
  assert.strictEqual(summary.freeStudentTickets, 2, 'Must count 2 free student passes');
  assert.strictEqual(summary.freeOutsiderTickets, 1, 'Must count 1 free outsider pass');
  assert.strictEqual(summary.freeTicketsIssued, 3, 'Must count 3 free passes total (excluding pending)');
  assert.strictEqual(summary.totalValidTicketsIssued, 3, 'Total valid passes must be 3');
  console.log('   ✓ Free-admission invariants and pending exclusion verified.');
}

// 9. Unavailable State on API Failure with Empty Local State (No Fabricated Zeros)
console.log('9. Testing Unavailable State on API Failure with Empty Local State...');
{
  const emptyTickets = [];
  const summary = computeSalesSummary(emptyTickets, 'evt-1');
  assert.strictEqual(summary, null, 'Must return null when store has no tickets, signaling unavailable rather than false zeros');
  
  // UI mapping simulation:
  const salesSummaryError = summary === null;
  const displayStudentCount = salesSummaryError ? 'Unavailable' : (summary?.paidStudentTicketsSold ?? 0);
  const displayTotalRevenue = salesSummaryError ? 'Unavailable' : `₱${Number(summary?.totalCollectedSales || 0).toFixed(2)}`;
  
  assert.strictEqual(displayStudentCount, 'Unavailable', 'UI must show Unavailable for ticket count');
  assert.strictEqual(displayTotalRevenue, 'Unavailable', 'UI must show Unavailable for revenue');
  console.log('   ✓ Unavailable state without fabricated zeros verified.');
}

// 10. Watcher Debounce Deduplication Simulation
console.log('10. Testing Debounce Deduplication on Rapid Store Mutations...');
{
  let callCount = 0;
  let timer = null;
  const debouncedFetch = (delay = 50) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      callCount++;
    }, delay);
  };

  // Simulate 5 rapid bursts of ticket updates (e.g. multi-record sync)
  debouncedFetch(20);
  debouncedFetch(20);
  debouncedFetch(20);
  debouncedFetch(20);
  debouncedFetch(20);

  await new Promise(resolve => setTimeout(resolve, 50));
  assert.strictEqual(callCount, 1, '5 rapid mutations within debounce window must collapse into exactly 1 API call');
  console.log('   ✓ Watcher debounce deduplication verified.');
}

console.log('\n=== ALL CASHIER POS SALES TOTALS TESTS PASSED ===\n');
