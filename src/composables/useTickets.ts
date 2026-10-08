import { useAppStore } from './useAppStore';

/**
 * Domain Composable: Ticketing, POS, QR Printing & Transactions
 */
export function useTickets() {
  const store = useAppStore();
  return {
    tickets: store.tickets,
    transactions: store.transactions,
    filters: store.filters,
    resetFilters: store.resetFilters,
    filteredTickets: store.filteredTickets,
    pendingQrTickets: store.pendingQrTickets,
    printedQrTickets: store.printedQrTickets,
    qrMetrics: store.qrMetrics,
    purchaseTicket: store.purchaseTicket,
    transferTicket: store.transferTicket,
    refundTicket: store.refundTicket,
    cancelTicket: store.cancelTicket,
    markQrAsPrinted: store.markQrAsPrinted,
    markQrAsPending: store.markQrAsPending,
    markQrAsAttached: store.markQrAsAttached,
    createPhysicalTicket: store.createPhysicalTicket,
    deleteTicket: store.deleteTicket,
    clearAllTickets: store.clearAllTickets,
    lookupTicketInfo: store.lookupTicketInfo,
  };
}
