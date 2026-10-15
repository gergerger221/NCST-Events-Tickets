export type UserRole = 'admin' | 'teacher' | 'student' | 'cashier' | 'event_staff' | 'outsider';
export type AttendeeType = 'STUDENT' | 'OUTSIDER';

export type AccountStatus = 'active' | 'pending_activation' | 'deactivated' | 'expired';
export type VerificationStatus = 'verified' | 'pending' | 'rejected';

export interface TeacherClassAssignment {
  department?: string; // e.g. "CSD", "Eng", "CBA", "GenEd"
  course: string; // e.g. "BSIT", "BSCS", "BSCpE"
  section: string; // e.g. "11M1", "31M1", "41M3", "21M2"
  yearLevel?: string; // e.g. "1st Year", "2nd Year", "3rd Year", "4th Year"
  subject?: string; // e.g. "Web Systems & Technologies"
}

export interface User {
  id: string;
  name: string;
  firstName?: string;
  middleName?: string;
  lastName?: string;
  suffix?: string;
  role: UserRole;
  password?: string;
  studentId?: string;
  employeeId?: string; // For Admin / Event Staff / Cashier / Teacher
  course?: string; // e.g. "BSIT", "BSCS", "BSCpE", "BSBA"
  yearLevel?: string; // e.g. "1st Year", "2nd Year", "3rd Year", "4th Year"
  section?: string; // Combined NCST section code (e.g. "11M1", "21M1", "31A1", "41M1")
  department?: string;
  photoUrl?: string;
  createdAt: string;
  assignedEventIds?: string[]; // For teachers / staff / multi-event outsiders
  eventId?: string; // Direct single event assignment for outsider
  isOutsider?: boolean; // Flag identifying temporary outsider/guest account
  attendeeType?: AttendeeType; // 'STUDENT' | 'OUTSIDER'
  assignedClasses?: TeacherClassAssignment[]; // Explicit authorized class assignments (Course + Section pairs)
  assignedCourses?: string[]; // Legacy fallback
  assignedYearLevels?: string[]; // Legacy fallback
  assignedSections?: string[]; // Legacy fallback
  isActive?: boolean; // Active or deactivated status
  accountStatus?: AccountStatus; // 'active' | 'pending_activation' | 'deactivated' | 'expired'
  verificationStatus?: VerificationStatus; // 'verified' | 'pending' | 'rejected'
  isVerified?: boolean; // Authoritative verification flag
  deactivationReason?: string; // e.g. "Temporary Outsider account expired following event conclusion"
  expiresAt?: string; // Calculated expiration ISO string
  expiredAt?: string; // Timestamp when account was auto-deactivated
  mustChangePassword?: boolean;
  temporaryPassword?: string;
  contactNumber?: string;
  hasRecoveryPin?: boolean;
}

// Authoritative Enrollment Roster Record (Identity distinct from Login Account)
export interface StudentRecord {
  id: string; // e.g. "rec-2026-00125"
  studentId: string; // e.g. "2026-00125"
  name: string;
  firstName?: string;
  middleName?: string;
  lastName?: string;
  suffix?: string;
  course: string;
  yearLevel: string;
  section: string;
  department?: string;
  contactNumber?: string;
  photoUrl?: string;
  createdAt: string;
  userId?: string; // Linked User login account ID (null/undefined if not yet registered)
}

export type EventStatus = 'upcoming' | 'ongoing' | 'completed' | 'archived' | 'cancelled';

export type EventCategory =
  | 'Academic'
  | 'Cultural'
  | 'Sports'
  | 'Technology'
  | 'Institutional'
  | 'Foundation'
  | 'Concert'
  | 'Battle of the bands'
  | string;

export type AcademicPeriodStatus = 'inactive' | 'active' | 'archived';

export interface AcademicPeriod {
  id: string; // e.g. "acad-2026-2027-sem1"
  academicYear: string; // e.g. "2026-2027"
  academic_year?: string;
  semester: string; // "1st Semester" | "2nd Semester"
  startDate: string; // YYYY-MM-DD
  start_date?: string;
  endDate: string; // YYYY-MM-DD
  end_date?: string;
  status: AcademicPeriodStatus;
  createdAt?: string;
  updatedAt?: string;
}

export interface EventItem {
  id: string;
  title: string;
  code: string;
  description: string;
  category: EventCategory;
  venue: string;
  date: string;
  startTime: string;
  endTime: string;
  capacity: number;
  ticketPrice: number; // 0 for free
  status: EventStatus;
  bannerUrl: string;
  ticketDesignUrl?: string; // Custom uploaded ticket background / artwork design (Image files only)
  ticketDesignOffsetY?: number; // Vertical alignment focal point (0 = top, 50 = center, 100 = bottom, default 20)
  organizer: string;
  academicYear?: string;
  academic_year?: string;
  semester?: string;
  isDeleted?: boolean;
  deletedAt?: string;
}

export type TicketType = 'digital' | 'physical';
// Standard NCST Lifecycle: ISSUED -> USED -> VOID (Non-refundable & Non-transferable)
export type TicketStatus = 'ISSUED' | 'USED' | 'VOID' | 'PENDING' | 'PAID' | 'CANCELLED' | 'active' | 'used' | 'cancelled' | 'void' | 'expired';
export type PaymentStatus = 'paid' | 'free' | 'pending';
export type AttendanceStatus = 'not_entered' | 'present';

export interface PurchaseTransaction {
  id: string; // e.g. "TXN-2026-99124"
  userId: string;
  userName: string;
  attendeeType?: AttendeeType;
  studentId?: string;
  outsiderId?: string;
  course?: string;
  yearLevel?: string;
  section?: string;
  contactNumber?: string;
  eventId: string;
  eventTitle: string;
  ticketId: string;
  ticketNumber?: string;
  amount: number;
  paymentStatus: PaymentStatus;
  purchaseDate: string;
  quantity: number;
  paymentMethod?: string;
  cashierName?: string;
  academicYear?: string;
  academic_year?: string;
  semester?: string;
}

export interface TransferRecord {
  id: string;
  timestamp: string;
  fromUserId: string;
  fromUserName: string;
  toUserId?: string;
  toUserName: string;
  toStudentId?: string;
  reason: string;
  verifiedBy: string;
}

export type QRPrintStatus = 'PENDING' | 'PRINTED' | 'FAILED' | 'ATTACHED';

export interface Ticket {
  id: string; // e.g. "EVT-2026-00125" or "TIX-00125"
  ticketNumber?: string; // e.g. "TIX-00125"
  qrToken?: string; // Unique token/reference stored inside QR code
  eventId: string;
  eventTitle?: string;
  ticketType: TicketType;
  
  // Classification
  attendeeType?: AttendeeType; // 'STUDENT' | 'OUTSIDER'
  
  // Student & Ownership details
  originalOwnerId: string;
  originalOwnerName: string;
  currentAttendeeId: string;
  currentAttendeeName: string;
  firstName?: string;
  middleName?: string;
  lastName?: string;
  attendeeStudentId?: string;
  outsiderId?: string;
  attendeeDepartment?: string;
  course?: string;
  yearLevel?: string;
  section?: string;
  contactNumber?: string;
  attendeePhotoUrl?: string;
  
  // Pricing & Status
  price: number;
  purchaseDate: string;
  ticketStatus: TicketStatus;
  paymentStatus: PaymentStatus;

  // Dynamic QR Sticker Print Lifecycle (Pre-Printed Physical Stock + Dynamic QR Sticker)
  qrPrintStatus?: QRPrintStatus;
  qrPrintedAt?: string;
  qrPrintCount?: number;
  
  // Attendance details
  attendanceStatus: AttendanceStatus;
  timeIn?: string;
  lastScannedBy?: string;
  
  // Transfer audit trail
  transferHistory: TransferRecord[];
  
  // Physical metadata
  physicalSerialNumber?: string;
  ocrVerified?: boolean;
  notes?: string;

  // Strict Academic Period
  academicYear?: string;
  academic_year?: string;
  semester?: string;
}

export interface TicketStock {
  id: string;
  eventId?: string | null;
  stockType: string;
  totalQuantity: number;
  availableQuantity: number;
  reservedQuantity: number;
  usedQuantity: number;
  updatedAt?: string;
}


export interface AttendanceLog {
  id: string;
  ticketId: string;
  ticketNumber?: string;
  eventId: string;
  eventTitle: string;
  attendeeType?: AttendeeType; // 'STUDENT' | 'OUTSIDER'
  attendeeName: string;
  firstName?: string;
  middleName?: string;
  lastName?: string;
  studentId?: string;
  outsiderId?: string;
  course?: string;
  yearLevel?: string;
  section?: string;
  contactNumber?: string;
  ticketType: TicketType;
  timestamp: string;
  date?: string;
  timeIn?: string;
  verifiedById: string;
  verifiedByName: string;
  status: 'valid' | 'denied';
  denialReason?: string;
  isOfflineSync?: boolean;

  // Strict Academic Period
  academicYear?: string;
  academic_year?: string;
  semester?: string;
}

export interface QueuedScan {
  id: string;
  rawScanData: string;
  eventId?: string;
  scannedAt: string;
  scannerUserId: string;
  scannerName: string;
  ticketId?: string;
  ticketNumber?: string;
  attendeeName?: string;
  status: 'pending' | 'syncing' | 'synced' | 'failed';
  error?: string;
}

export interface ScanValidationResult {
  isValid: boolean;
  status: 'valid' | 'denied';
  reason?: string;
  attendeeType?: AttendeeType;
  ticket?: Ticket;
  event?: EventItem;
  timestamp: string;
  timeInFormatted?: string;
  isOfflineRecorded?: boolean;
}

export interface FilterState {
  eventId: string;
  searchQuery: string;
  attendeeType?: 'all' | 'STUDENT' | 'OUTSIDER';
  ticketStatus: 'all' | 'active' | 'cancelled' | 'refunded' | 'expired' | 'used';
  paymentStatus: 'all' | 'paid' | 'free' | 'refunded';
  attendanceStatus: 'all' | 'present' | 'not_entered';
  qrPrintStatus?: 'all' | 'PENDING' | 'PRINTED' | 'ATTACHED';
  ticketType: 'all' | 'digital' | 'physical';
  course?: string;
  department?: string;
  yearLevel?: string;
  section?: string;
  dateRange?: string;
}

export interface EventSalesBreakdown {
  eventId: string;
  eventTitle: string;
  eventCode: string;
  academicYear?: string;
  semester?: string;
  eventPrice: number;
  paidStudentTickets: number;
  paidOutsiderTickets: number;
  totalPaidTickets: number;
  freeTicketsIssued: number;
  totalValidPasses: number;
  totalCollectedSales: number;
}

export interface SectionSalesBreakdown {
  course: string;
  yearLevel: string;
  section: string;
  paidStudentTickets: number;
  freeStudentTickets: number;
  totalStudentPasses: number;
  totalCollectedSales: number;
}

export interface TicketSalesSummary {
  paidStudentTicketsSold: number;
  paidOutsiderTicketsSold: number;
  totalPaidTicketsSold: number;
  freeTicketsIssued: number;
  freeStudentTickets: number;
  freeOutsiderTickets: number;
  totalValidTicketsIssued: number;
  totalCollectedSales: number;
  totalStudentTickets: number;
  totalOutsiderTickets: number;
  studentTicketsSold?: number;
  outsiderTicketsSold?: number;
  totalTicketsSold?: number;
  paidTicketsSold?: number;
  freeTicketsSold?: number;
  totalRevenue?: number;
  breakdowns?: {
    byEvent?: EventSalesBreakdown[];
    bySection?: SectionSalesBreakdown[];
  };
}

