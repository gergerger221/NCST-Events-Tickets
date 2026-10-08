import { useAppStore } from './useAppStore';

/**
 * Domain Composable: Academic Student Roster & Master Records
 */
export function useStudentRoster() {
  const store = useAppStore();
  return {
    studentRecords: store.studentRecords,
    availableSections: store.availableSections,
    saveStudentRecord: store.saveStudentRecord,
    registerStudent: store.registerStudent,
    importStudents: store.importStudents,
    lookupStudentRecord: store.lookupStudentRecord,
    sendStudentVerificationCode: store.sendStudentVerificationCode,
    verifyStudentVerificationCode: store.verifyStudentVerificationCode,
  };
}
