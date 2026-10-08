import { useAppStore } from './useAppStore';

/**
 * Domain Composable: Event Management & Scheduling
 */
export function useEvents() {
  const store = useAppStore();
  return {
    events: store.events,
    currentEvent: store.currentEvent,
    setCurrentEvent: store.setCurrentEvent,
    createEvent: store.createEvent,
    updateEvent: store.updateEvent,
    deleteEvent: store.deleteEvent,
    activeEvents: store.activeEvents,
    archivedEvents: store.archivedEvents,
    archiveEvent: store.archiveEvent,
    restoreArchivedEvent: store.restoreArchivedEvent,
  };
}
