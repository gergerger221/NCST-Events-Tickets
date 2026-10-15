// NCST Institutional Academic Structure & Controlled Department Master Data

export interface NCSTDepartment {
  code: string; // Official department code (e.g. "CSD", "Eng", "CBA", "GenEd")
  name: string; // Full department title
  courses: Array<{
    code: string; // Course code (e.g. "BSIT", "BSCS")
    name: string; // Full course title
  }>;
}

export const NCST_DEPARTMENTS: NCSTDepartment[] = [
  {
    code: 'CSD',
    name: 'Computer Studies Department',
    courses: [
      { code: 'BSIT', name: 'BS in Information Technology' },
      { code: 'BSCS', name: 'BS in Computer Science' },
      { code: 'BSIS', name: 'BS in Information Systems' },
      { code: 'BSEMC', name: 'BS in Entertainment & Multimedia Computing' },
    ],
  },
  {
    code: 'Eng',
    name: 'College of Engineering & Architecture',
    courses: [
      { code: 'BSCpE', name: 'BS in Computer Engineering' },
      { code: 'BSEE', name: 'BS in Electrical Engineering' },
      { code: 'BSCE', name: 'BS in Civil Engineering' },
      { code: 'BSME', name: 'BS in Mechanical Engineering' },
      { code: 'BSECE', name: 'BS in Electronics Engineering' },
    ],
  },
  {
    code: 'CBA',
    name: 'College of Business Administration',
    courses: [
      { code: 'BSBA', name: 'BS in Business Administration' },
      { code: 'BSA', name: 'BS in Accountancy' },
      { code: 'BSACT', name: 'BS in Accounting Technology' },
      { code: 'BSMA', name: 'BS in Management Accounting' },
    ],
  },
  {
    code: 'GenEd',
    name: 'General Education Department',
    courses: [
      { code: 'GenEd', name: 'General Education Core Subjects' },
    ],
  },
  {
    code: 'Educ',
    name: 'College of Education',
    courses: [
      { code: 'BSED', name: 'Bachelor of Secondary Education' },
      { code: 'BEED', name: 'Bachelor of Elementary Education' },
    ],
  },
  {
    code: 'CCJ',
    name: 'College of Criminology & Justice',
    courses: [
      { code: 'BSCRIM', name: 'BS in Criminology' },
    ],
  },
  {
    code: 'CHTM',
    name: 'College of Hospitality & Tourism Management',
    courses: [
      { code: 'BSHM', name: 'BS in Hospitality Management' },
      { code: 'BSTM', name: 'BS in Tourism Management' },
    ],
  },
];

/**
 * Derives the standard academic year level from the NCST section code convention:
 * Format: [Year][Semester][Schedule][Section] (e.g. "11M1" -> "1st Year", "41M3" -> "4th Year")
 */
export const deriveYearLevelFromSection = (sectionCode?: string): string => {
  if (!sectionCode || typeof sectionCode !== 'string') return '1st Year';
  const clean = sectionCode.trim().toUpperCase().replace(/\s+/g, '');
  const firstChar = clean.charAt(0);
  switch (firstChar) {
    case '1': return '1st Year';
    case '2': return '2nd Year';
    case '3': return '3rd Year';
    case '4': return '4th Year';
    case '5': return '5th Year';
    default: return '1st Year';
  }
};

/**
 * Finds the department code matching a course code.
 */
export const getDepartmentCodeForCourse = (courseCode?: string): string => {
  if (!courseCode) return 'CSD';
  const upper = courseCode.toUpperCase().trim();
  for (const dept of NCST_DEPARTMENTS) {
    if (dept.courses.some(c => c.code.toUpperCase() === upper)) {
      return dept.code;
    }
  }
  return 'CSD';
};

/**
 * Returns the list of courses belonging to a department.
 */
export const getCoursesForDepartment = (deptCode?: string): Array<{ code: string; name: string }> => {
  if (!deptCode) return NCST_DEPARTMENTS[0].courses;
  const dept = NCST_DEPARTMENTS.find(d => d.code.toUpperCase() === deptCode.toUpperCase());
  return dept ? dept.courses : NCST_DEPARTMENTS[0].courses;
};

/**
 * Validates whether a section code conforms to official NCST format:
 * [Year 1-5][Semester 1-2][Schedule M/A/E][Section 1-9] (e.g. 11M1, 21M2, 31M1, 41M3, 31A2)
 */
export interface NCSTSectionValidation {
  isValid: boolean;
  yearLevel: string;
  semester: string;
  schedule: string;
  sectionNumber: string;
  description: string;
  error?: string;
}

export const validateNCSTSection = (sectionCode?: string): NCSTSectionValidation => {
  if (!sectionCode || typeof sectionCode !== 'string' || !sectionCode.trim()) {
    return {
      isValid: false,
      yearLevel: '',
      semester: '',
      schedule: '',
      sectionNumber: '',
      description: '',
      error: 'Section code is required.',
    };
  }

  const clean = sectionCode.trim().toUpperCase().replace(/\s+/g, '');
  const match = clean.match(/^([1-5])([1-2])([MAE])([1-9][0-9]?)$/);

  if (!match) {
    return {
      isValid: false,
      yearLevel: deriveYearLevelFromSection(clean),
      semester: '',
      schedule: '',
      sectionNumber: clean,
      description: 'Format: [Year][Sem][Schedule: M/A/E][Section#] (e.g. 11M1, 31M1, 41M3)',
      error: 'Invalid NCST section format. Format must be [Year][Sem][Schedule: M/A/E][Section#] (e.g. 11M1, 41M3).',
    };
  }

  const [_, y, s, sch, secNum] = match;
  const yearNames: Record<string, string> = {
    '1': '1st Year',
    '2': '2nd Year',
    '3': '3rd Year',
    '4': '4th Year',
    '5': '5th Year',
  };
  const semNames: Record<string, string> = {
    '1': '1st Semester',
    '2': '2nd Semester',
  };
  const schedNames: Record<string, string> = {
    'M': 'Morning',
    'A': 'Afternoon',
    'E': 'Evening',
  };

  const yearLevel = yearNames[y] || `${y}th Year`;
  const semester = semNames[s] || `${s} Sem`;
  const schedule = schedNames[sch] || sch;
  const description = `${yearLevel} • ${semester} • ${schedule} (Section ${secNum})`;

  return {
    isValid: true,
    yearLevel,
    semester,
    schedule,
    sectionNumber: `Sec ${secNum}`,
    description,
  };
};

export const NCST_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];

const INVALID_SECTION_KEYWORDS = ['YEAR', 'YR', 'GRADE', 'STUDENT', 'N/A', 'NONE', 'UNKNOWN', 'NULL', 'UNDEFINED', 'FIRST', 'SECOND', 'THIRD', 'FOURTH', 'FIFTH', 'COLLEGE', 'DEPT'];

/**
 * Official NCST Standard Section Master Catalogue (Sections 1 to 5 for Morning (M), Afternoon (A), Evening (E) across Years 1-5 and Semesters 1-2)
 * Format: [Year 1-5][Sem 1-2][Sched M/A/E][Sec 1-5] (e.g. 11M1..11M5, 12M1..12M5, 21M1..21M5, 22M1..22M5, 11A1..11A5, 31A1..31A5, etc.)
 */
export const NCST_MASTER_SECTIONS: string[] = (() => {
  const list: string[] = [];
  const schedules = ['M', 'A', 'E'];
  const sectionNumbers = ['1', '2', '3', '4', '5'];

  // NCST Year & Semester Format: [Year 1-5][Sem 1-2][Sched M/A/E][Sec 1-5] (e.g. 11M1..11M5, 12M1..12M5, 21M1..21M5, 22M1..22M5, 11A1..11A5, 31A1..31A5, etc.)
  for (let year = 1; year <= 5; year++) {
    for (let sem = 1; sem <= 2; sem++) {
      schedules.forEach(sched => {
        sectionNumbers.forEach(num => {
          list.push(`${year}${sem}${sched}${num}`);
        });
      });
    }
  }

  return list;
})();

export const NCST_STANDARD_SECTIONS = NCST_MASTER_SECTIONS;
export const COMMON_NCST_SECTIONS = NCST_MASTER_SECTIONS;

/**
 * Extracts distinct, valid NCST sections present in actual authoritative system records,
 * student rosters, registered users, teacher class assignments, and the NCST Master Sections (max 1 to 5).
 */
export const extractActualSystemSections = (
  studentRecords?: Array<{ section?: string }>,
  users?: Array<{ section?: string; assignedClasses?: Array<{ section?: string }> }>,
  tickets?: Array<{ section?: string }>
): string[] => {
  const set = new Set<string>(NCST_MASTER_SECTIONS);

  const isLegitSectionCode = (s: string) => {
    if (!s || typeof s !== 'string') return false;
    const clean = s.trim().toUpperCase();
    if (clean.length < 4 || clean.length > 8) return false;
    if (INVALID_SECTION_KEYWORDS.some(kw => clean.includes(kw))) return false;
    // Section codes must not contain spaces
    if (/\s/.test(clean)) return false;
    // Must conform to NCST format: [Year 1-5][Sem 1-2][Sched M/A/E][Section 1-5]
    return /^[1-5][1-2][MAE][0-9]+$/.test(clean);
  };

  studentRecords?.forEach(r => {
    if (r.section && typeof r.section === 'string') {
      const clean = r.section.trim().toUpperCase();
      if (isLegitSectionCode(clean)) set.add(clean);
    }
  });

  users?.forEach(u => {
    if (u.section && typeof u.section === 'string') {
      const clean = u.section.trim().toUpperCase();
      if (isLegitSectionCode(clean)) set.add(clean);
    }
    u.assignedClasses?.forEach(c => {
      if (c.section && typeof c.section === 'string') {
        const clean = c.section.trim().toUpperCase();
        if (isLegitSectionCode(clean)) set.add(clean);
      }
    });
  });

  tickets?.forEach(t => {
    if (t.section && typeof t.section === 'string') {
      const clean = t.section.trim().toUpperCase();
      if (isLegitSectionCode(clean)) set.add(clean);
    }
  });

  return Array.from(set).sort((a, b) => {
    if (a.length !== b.length) return a.length - b.length;
    return a.localeCompare(b);
  });
};

export const isValidNCSTSection = (val?: string, availableList?: string[]): boolean => {
  if (!val || typeof val !== 'string') return false;
  const clean = val.trim().toUpperCase();
  if (!clean) return false;

  if (Array.isArray(availableList) && availableList.length > 0) {
    return availableList.some(s => s.toUpperCase() === clean);
  }

  // Format validation if available list is empty: [Year 1-5][Sem 1-2][Sched M/A/E][Section 1-5]
  if (/^[1-5][1-2][MAE][1-5]$/.test(clean)) return true;
  return false;
};

/**
 * Matches a student's academic identity with an authorized teacher class assignment.
 * Authoritative class identity check: Department + Course + Year Level + Section.
 * e.g. CSD + BSIT + 3rd Year + 31M1 must NOT match 2nd Year 21M1 or 3rd Year 31A1.
 */
export const matchTeacherClassAssignment = (
  assignment: { department?: string; course: string; yearLevel?: string; section: string },
  student: { department?: string; course?: string; yearLevel?: string; section?: string }
): boolean => {
  if (!assignment || !student) return false;

  // 1. Course Match
  const aCourse = (assignment.course || '').toUpperCase().trim();
  const sCourse = (student.course || '').toUpperCase().trim();
  if (aCourse && sCourse && aCourse !== sCourse) return false;

  // 2. Section Match
  const aSection = (assignment.section || '').toUpperCase().trim();
  const sSection = (student.section || '').toUpperCase().trim();
  if (aSection && sSection && aSection !== sSection) return false;

  // 3. Year Level Match (Strict: 3rd Year 31M1 must not match 2nd Year 21M1)
  if (assignment.yearLevel && student.yearLevel) {
    const aYear = assignment.yearLevel.toLowerCase().trim();
    const sYear = student.yearLevel.toLowerCase().trim();
    if (aYear !== sYear) return false;
  }

  // 4. Department Match
  if (assignment.department && student.department) {
    const aDept = assignment.department.toUpperCase().trim();
    const sDept = student.department.toUpperCase().trim();
    if (aDept !== sDept && !sDept.includes(aDept) && !aDept.includes(sDept)) {
      return false;
    }
  }

  return true;
};

