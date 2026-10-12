<template>
  <div class="space-y-6 max-w-7xl mx-auto py-2">
    <!-- ==================== 1. PAGE HEADER ==================== -->
    <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs transition-colors">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-1 min-w-0">
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
            User Accounts
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Manage administrators, event staff, cashiers, teachers, and student accounts.
          </p>
        </div>

        <button
          @click="openAddModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition cursor-pointer self-start sm:self-auto shrink-0"
        >
          <UserPlus class="w-4 h-4" />
          <span>+ Add Account</span>
        </button>
      </div>
    </div>

    <!-- ==================== 2. ROLE FILTERS & SEARCH ==================== -->
    <div class="space-y-3">
      <!-- Role Filter Tabs / Chips -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 custom-scrollbar">
        <button
          v-for="filter in roleFilters"
          :key="filter.id"
          @click="selectedRoleFilter = filter.id"
          class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 border"
          :class="selectedRoleFilter === filter.id
            ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
            : 'bg-white dark:bg-[#1a1a1a] border-slate-200 dark:border-[#2e2e2e] text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-[#252525]'"
        >
          <span>{{ filter.label }}</span>
          <span
            class="text-[11px] font-mono px-1.5 py-0.2 rounded-full"
            :class="selectedRoleFilter === filter.id ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-[#282828] text-slate-500 dark:text-slate-400'"
          >
            {{ filter.count }}
          </span>
        </button>
      </div>

      <!-- Search Input Bar -->
      <div class="relative w-full">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Search by name, email, ID..."
          class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition shadow-xs"
        />
      </div>
    </div>

    <!-- ==================== 3. ADMINISTRATIVE DATA TABLE ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-2xl overflow-hidden shadow-xs transition-colors flex flex-col">
      <!-- Desktop & Tablet Table View (Scrollable with Sticky Header) -->
      <div class="overflow-x-auto overflow-y-auto max-h-[480px] custom-scrollbar">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-[#222222] shadow-xs">
            <tr class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200 dark:border-[#2e2e2e] tracking-wider">
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#222222]">Name</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#222222]">Role</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#222222]">ID / Email</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#222222]">Academic / Department</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#222222]">Status</th>
              <th class="py-3 px-4 text-right bg-slate-50 dark:bg-[#222222]">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
            <tr
              v-for="u in filteredUsers"
              :key="u.id"
              @click="openDetailsDrawer(u)"
              class="hover:bg-slate-50/80 dark:hover:bg-[#222222] transition cursor-pointer group"
            >
              <!-- 1. Name & Avatar -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <UserAvatar :src="u.photoUrl" :alt="u.name" size="sm" shape="rounded" />
                  <div>
                    <p class="font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                      {{ u.name }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- 2. Role Badge (Subtle & Neutral) -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider inline-block"
                  :class="getRoleBadgeClass(u.role)"
                >
                  {{ formatRole(u.role) }}
                </span>
              </td>

              <!-- 3. ID / Email -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <div class="space-y-0.5">
                  <span v-if="u.studentId" class="font-mono font-bold text-blue-600 dark:text-blue-400 block text-xs">
                    {{ u.studentId }}
                  </span>
                  <span v-else-if="u.employeeId" class="font-mono font-bold text-purple-600 dark:text-purple-400 block text-xs">
                    {{ u.employeeId }}
                  </span>
                  <span v-if="u.role !== 'student'" class="text-slate-500 dark:text-slate-400 font-mono text-[11px] block truncate max-w-[200px]">
                    {{ u.email }}
                  </span>
                </div>
              </td>

              <!-- 4. Academic / Department -->
              <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 dark:text-slate-300 font-medium">
                <!-- For Student -->
                <span v-if="u.role === 'student'">
                  {{ u.course || 'BSIT' }} <span v-if="u.yearLevel">• {{ u.yearLevel }}</span> <span v-if="u.section">• Sec {{ u.section }}</span>
                </span>
                <!-- For Teacher -->
                <span v-else-if="u.role === 'teacher'">
                  <span class="font-bold text-purple-700 dark:text-purple-400">{{ u.department || 'CSD' }}</span>
                  <span v-if="u.assignedClasses && u.assignedClasses.length > 0" class="text-slate-500 text-[11px] ml-1">
                    ({{ u.assignedClasses.length }} {{ u.assignedClasses.length === 1 ? 'class' : 'classes' }})
                  </span>
                  <span v-else class="text-amber-500 text-[11px] ml-1">(No classes assigned)</span>
                </span>
                <!-- For Event Staff -->
                <span v-else-if="u.role === 'event_staff'">
                  {{ u.department || 'Event Staff' }}
                  <span v-if="u.assignedEventIds && u.assignedEventIds.length > 0" class="text-slate-400 text-[11px] ml-1">
                    ({{ u.assignedEventIds.length }} assigned {{ u.assignedEventIds.length === 1 ? 'event' : 'events' }})
                  </span>
                </span>
                <!-- For Cashier -->
                <span v-else-if="u.role === 'cashier'">
                  {{ u.department || 'University Finance & Ticket Counter' }}
                </span>
                <!-- For Admin -->
                <span v-else>
                  {{ u.department || 'System Governance & Operations' }}
                </span>
              </td>

              <!-- 5. Status Badge -->
              <td class="py-3.5 px-4 whitespace-nowrap">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase inline-block"
                    :class="u.isActive !== false
                      ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60'
                      : 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/60'"
                  >
                    {{ u.isActive !== false ? 'ACTIVE' : 'INACTIVE' }}
                  </span>
                </div>
              </td>

              <!-- 6. Compact Actions Menu -->
              <td class="py-3.5 px-4 text-right whitespace-nowrap" @click.stop>
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="u.isActive === false"
                    @click="handleReactivate(u)"
                    class="p-1.5 text-emerald-600 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition cursor-pointer"
                    title="Reactivate Account"
                  >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    @click="openEditModal(u)"
                    class="p-1.5 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 rounded-lg hover:bg-slate-100 dark:hover:bg-[#282828] transition cursor-pointer"
                    title="Edit Account / Update Role"
                  >
                    <Edit3 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    @click="toggleActiveStatus(u)"
                    class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-[#282828] transition cursor-pointer"
                    :class="u.isActive !== false ? 'text-amber-500 hover:text-amber-600' : 'text-emerald-500 hover:text-emerald-600'"
                    :title="u.isActive !== false ? 'Deactivate Account' : 'Activate Account'"
                  >
                    <Power class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="users.length > 1"
                    @click="handleDeleteUser(u)"
                    class="p-1.5 text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 rounded-lg hover:bg-slate-100 dark:hover:bg-[#282828] transition cursor-pointer"
                    title="Delete Account"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="filteredUsers.length === 0">
              <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500">
                <Users class="w-8 h-8 mx-auto mb-2 opacity-40" />
                <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No users found</p>
                <p class="text-xs mt-0.5">Try clearing your search query or selecting a different role filter.</p>
                <button
                  v-if="searchQuery || selectedRoleFilter !== 'all'"
                  @click="resetFilters"
                  class="mt-3 px-3 py-1.5 bg-slate-100 dark:bg-[#252525] hover:bg-slate-200 dark:hover:bg-[#303030] text-slate-700 dark:text-slate-300 rounded-lg text-xs font-bold transition cursor-pointer"
                >
                  Reset Filters
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Footer summary info -->
      <div v-if="filteredUsers.length > 0" class="px-4 py-2.5 bg-slate-50/80 dark:bg-[#1f1f1f] border-t border-slate-200 dark:border-[#2e2e2e] flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <span>Showing <strong class="text-slate-700 dark:text-slate-300">{{ filteredUsers.length }}</strong> of <strong class="text-slate-700 dark:text-slate-300">{{ users.length }}</strong> accounts</span>
        <span class="text-[10px] text-slate-400">Scrollable view</span>
      </div>
    </div>

    <!-- ==================== 4. PROGRESSIVE DISCLOSURE: USER DETAILS DRAWER ==================== -->
    <Modal
      :is-open="isDrawerOpen"
      @close="isDrawerOpen = false"
      title="User Details"
      subtitle="Complete profile, institutional identity, and role-specific account parameters"
      max-width="md"
    >
      <div v-if="selectedUserForDrawer" class="space-y-5 text-xs">
        <!-- Top Identity Header -->
        <div class="flex items-center gap-3.5 p-4 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e]">
          <UserAvatar :src="selectedUserForDrawer.photoUrl" :alt="selectedUserForDrawer.name" size="lg" shape="rounded" />
          <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-slate-900 dark:text-white truncate">
              {{ selectedUserForDrawer.name }}
            </h3>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
              <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" :class="getRoleBadgeClass(selectedUserForDrawer.role)">
                {{ formatRole(selectedUserForDrawer.role) }}
              </span>
              <span
                class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase"
                :class="selectedUserForDrawer.isActive !== false ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ selectedUserForDrawer.isActive !== false ? 'ACTIVE' : 'INACTIVE' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Role-Specific Parameters -->
        <div class="space-y-3">
          <!-- Common: Email (Displayed for Non-Student Personnel) -->
          <div v-if="selectedUserForDrawer.role !== 'student'" class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Institutional Email</span>
            <span class="font-mono text-slate-900 dark:text-white font-bold truncate max-w-[200px]">
              {{ selectedUserForDrawer.email }}
            </span>
          </div>

          <!-- Primary Identifier: Student ID vs Employee ID -->
          <div v-if="selectedUserForDrawer.studentId" class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Primary Student ID</span>
            <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
              {{ selectedUserForDrawer.studentId }}
            </span>
          </div>

          <div v-if="selectedUserForDrawer.employeeId" class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Primary Employee ID</span>
            <span class="font-mono font-bold text-purple-600 dark:text-purple-400">
              {{ selectedUserForDrawer.employeeId }}
            </span>
          </div>

          <!-- Student Specific Fields -->
          <template v-if="selectedUserForDrawer.role === 'student'">
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Course & Academic Year</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ selectedUserForDrawer.course }} • {{ selectedUserForDrawer.yearLevel }}
              </span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Class Section</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                Section {{ selectedUserForDrawer.section || '11M1' }}
              </span>
            </div>
          </template>

          <!-- Teacher Specific: Department and Explicit Assigned Classes -->
          <template v-if="selectedUserForDrawer.role === 'teacher'">
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Department</span>
              <span class="font-bold text-purple-700 dark:text-purple-400">
                {{ selectedUserForDrawer.department || 'CSD' }}
              </span>
            </div>
            <div class="p-3.5 rounded-xl bg-purple-50/30 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/40 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-slate-700 dark:text-slate-300 font-bold block text-[11px] uppercase tracking-wider">
                  Assigned Classes / Sections
                </span>
                <span class="text-[10px] text-slate-500">
                  {{ selectedUserForDrawer.assignedClasses?.length || 0 }} authorized
                </span>
              </div>
              <div v-if="selectedUserForDrawer.assignedClasses && selectedUserForDrawer.assignedClasses.length > 0" class="flex flex-wrap gap-1.5">
                <span
                  v-for="(cls, idx) in selectedUserForDrawer.assignedClasses"
                  :key="idx"
                  class="px-2.5 py-1 rounded-lg bg-purple-100 dark:bg-purple-900/60 text-purple-800 dark:text-purple-200 font-mono font-bold text-xs border border-purple-300 dark:border-purple-700 flex items-center gap-1.5"
                >
                  <BookOpen class="w-3 h-3 text-purple-600 dark:text-purple-400" />
                  <span>{{ cls.course }} — {{ cls.section }}</span>
                </span>
              </div>
              <p v-else class="text-amber-600 dark:text-amber-400 text-xs italic">
                No classes assigned by Admin yet. Teacher cannot view any student attendance.
              </p>
            </div>
          </template>

          <!-- Event Staff Specific -->
          <template v-if="selectedUserForDrawer.role === 'event_staff'">
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Department / Unit</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ selectedUserForDrawer.department || 'Event Operations' }}
              </span>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] space-y-2">
              <span class="text-slate-500 dark:text-slate-400 font-medium block text-[11px] uppercase tracking-wider">
                Assigned Events (Entrance Scanner Scope)
              </span>
              <div v-if="selectedUserForDrawer.assignedEventIds && selectedUserForDrawer.assignedEventIds.length > 0" class="flex flex-wrap gap-1.5">
                <span
                  v-for="evId in selectedUserForDrawer.assignedEventIds"
                  :key="evId"
                  class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 font-bold text-[10px] border border-blue-200 dark:border-blue-800"
                >
                  {{ getEventTitle(evId) }}
                </span>
              </div>
              <p v-else class="text-slate-400 text-xs italic">
                Authorized for all campus events.
              </p>
            </div>
          </template>

          <!-- Cashier Specific -->
          <template v-if="selectedUserForDrawer.role === 'cashier'">
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Department / Counter</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ selectedUserForDrawer.department || 'University Finance & Ticket Booth' }}
              </span>
            </div>
          </template>

          <!-- Admin Specific -->
          <template v-if="selectedUserForDrawer.role === 'admin'">
            <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-[#1a1a1a] border border-slate-100 dark:border-[#262626] flex justify-between">
              <span class="text-slate-500 dark:text-slate-400 font-medium">Administrative Role</span>
              <span class="font-semibold text-slate-900 dark:text-white">
                Full System Administration
              </span>
            </div>
          </template>
        </div>

        <!-- Drawer Action Buttons -->
        <div class="pt-4 flex items-center justify-between gap-2 border-t border-slate-200 dark:border-[#2e2e2e]">
          <button
            @click="toggleActiveStatus(selectedUserForDrawer)"
            class="px-3 py-2 rounded-xl text-xs font-bold transition cursor-pointer border"
            :class="selectedUserForDrawer.isActive !== false
              ? 'border-amber-200 dark:border-amber-900/60 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30'
              : 'border-emerald-200 dark:border-emerald-900/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
          >
            {{ selectedUserForDrawer.isActive !== false ? 'Deactivate' : 'Reactivate Account' }}
          </button>

          <div class="flex items-center gap-2">
            <button
              @click="openEditModal(selectedUserForDrawer); isDrawerOpen = false;"
              class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer"
            >
              Edit Account
            </button>
            <button
              @click="isDrawerOpen = false"
              class="px-3 py-2 bg-slate-100 dark:bg-[#252525] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-200 transition cursor-pointer"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- ==================== 5. ADD / CREATE ACCOUNT MODAL ==================== -->
    <Modal
      :is-open="isAddUserModalOpen"
      @close="isAddUserModalOpen = false"
      title="Add Account"
      subtitle="Register an account enforcing ONE PERSON = ONE ACCOUNT institutional policy"
      max-width="md"
    >
      <form @submit.prevent="handleCreateUser" class="space-y-4">
        <!-- 1. Role Selector (First to dynamically show fields) -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Role *</label>
          <select
            v-model="role"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="student">Student</option>
            <option value="event_staff">Event Staff (Entrance & Scanner)</option>
            <option value="cashier">Cashier / Ticket Staff</option>
            <option value="teacher">Teacher / Faculty</option>
            <option value="admin">Event Administrator</option>
          </select>
        </div>

        <!-- 2. Primary Identity Identifier: Student ID (for Student) vs Employee ID (for Personnel) -->
        <div v-if="role === 'student'">
          <div class="flex items-center justify-between mb-1">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
              Student ID Number * (Primary Unique Key)
            </label>
            <span v-if="studentId.trim() && !addConflict.hasConflict" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
              ✓ Identifier Available
            </span>
          </div>
          <input
            type="text"
            v-model="studentId"
            placeholder="e.g. 2026-00125"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none transition"
            :class="addConflict.hasConflict && (addConflict.conflictType === 'student_id')
              ? 'border-rose-500 ring-1 ring-rose-500/30'
              : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'"
            required
          />
        </div>

        <div v-else>
          <div class="flex items-center justify-between mb-1">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
              Employee ID * (Primary Unique Key)
            </label>
            <span v-if="employeeId.trim() && !addConflict.hasConflict" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
              ✓ Identifier Available
            </span>
          </div>
          <input
            type="text"
            v-model="employeeId"
            placeholder="e.g. EMP-00125"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none transition"
            :class="addConflict.hasConflict && (addConflict.conflictType === 'employee_id')
              ? 'border-rose-500 ring-1 ring-rose-500/30'
              : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'"
            required
          />
        </div>

        <!-- 3. Full Name -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
          <input
            type="text"
            v-model="name"
            placeholder="e.g. Juan Dela Cruz"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            required
          />
        </div>

        <!-- 4. Email Address (For Personnel Roles only; not required for Students) -->
        <div v-if="role !== 'student'">
          <div class="flex items-center justify-between mb-1">
            <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
              Institutional Email * (Secondary Unique Key)
            </label>
            <span v-if="email.trim() && !addConflict.hasConflict" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
              ✓ Email Available
            </span>
          </div>
          <input
            type="email"
            v-model="email"
            placeholder="e.g. juan.delacruz@school.edu.ph"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none transition"
            :class="addConflict.hasConflict && (addConflict.conflictType === 'email')
              ? 'border-rose-500 ring-1 ring-rose-500/30'
              : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'"
            :required="role !== 'student'"
          />
        </div>

        <!-- ========================================================================= -->
        <!-- REAL-TIME CONFLICT & SMART EXISTING PERSON DETECTION BANNER -->
        <!-- ========================================================================= -->

        <!-- CASE A: Hard Conflict Found (Active or Inactive) -->
        <div
          v-if="addConflict.hasConflict && addConflict.existingUser"
          class="p-3.5 rounded-2xl border transition-all animate-fade-in space-y-2.5"
          :class="addConflict.isInactive
            ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700/60 text-amber-900 dark:text-amber-200'
            : 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-700/60 text-rose-900 dark:text-rose-200'"
        >
          <div class="flex items-start gap-2.5">
            <AlertTriangle class="w-4 h-4 flex-shrink-0 mt-0.5" :class="addConflict.isInactive ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'" />
            <div class="min-w-0 flex-1">
              <p class="font-extrabold text-xs tracking-tight">
                ⚠️ {{ addConflict.title }}
              </p>
              <p class="text-[11px] mt-0.5 leading-relaxed opacity-90">
                {{ addConflict.message }}
              </p>

              <!-- Existing Person Card Summary -->
              <div class="mt-2 p-2 rounded-xl bg-white/70 dark:bg-black/30 border border-current/10 flex items-center justify-between text-[11px]">
                <div>
                  <p class="font-bold text-slate-900 dark:text-white">{{ addConflict.existingUser.name }}</p>
                  <p class="text-[10px] font-mono text-slate-500 dark:text-slate-400">
                    ID: {{ addConflict.existingUser.studentId || addConflict.existingUser.employeeId || addConflict.existingUser.id }}<span v-if="addConflict.existingUser.role !== 'student'"> • {{ addConflict.existingUser.email }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" :class="getRoleBadgeClass(addConflict.existingUser.role)">
                    {{ formatRole(addConflict.existingUser.role) }}
                  </span>
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase"
                    :class="addConflict.existingUser.isActive !== false ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'"
                  >
                    {{ addConflict.existingUser.isActive !== false ? 'ACTIVE' : 'INACTIVE' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Action Buttons for Conflict Resolution -->
          <div class="flex items-center justify-end gap-2 pt-1 border-t border-current/10">
            <button
              v-if="addConflict.isInactive"
              type="button"
              @click="handleReactivateFromConflict(addConflict.existingUser)"
              class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition cursor-pointer flex items-center gap-1"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>Reactivate Account</span>
            </button>
            <button
              type="button"
              @click="handleEditFromConflict(addConflict.existingUser)"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-xs transition cursor-pointer flex items-center gap-1"
            >
              <Edit3 class="w-3.5 h-3.5" />
              <span>Edit / Update Role</span>
            </button>
          </div>
        </div>

        <!-- CASE B: Soft Name Similarity Warning (Identical name, different ID) -->
        <div
          v-else-if="addConflict.isSameNameDifferentId && addConflict.similarNameUser"
          class="p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-2.5"
        >
          <Info class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
          <div>
            <p class="font-bold">⚠️ Similar Name Detected</p>
            <p class="text-[10px] mt-0.5 opacity-90">
              A user with the name <strong>"{{ addConflict.similarNameUser.name }}"</strong> already exists (Role: {{ addConflict.similarNameUser.role.toUpperCase() }} • ID: {{ addConflict.similarNameUser.studentId || addConflict.similarNameUser.employeeId }}). Registration is permitted since the institutional ID is distinct.
            </p>
          </div>
        </div>

        <!-- 5. Dynamic Role Fields: Student -->
        <template v-if="role === 'student'">
          <div class="p-3 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 rounded-xl space-y-3">
            <p class="text-[11px] font-bold text-blue-900 dark:text-blue-300 flex items-center gap-1.5">
              <GraduationCap class="w-3.5 h-3.5 text-blue-600" />
              <span>Student Academic Profile</span>
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Department</label>
                <select
                  v-model="department"
                  class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                >
                  <option v-for="d in NCST_DEPARTMENTS" :key="d.code" :value="d.code">
                    {{ d.code }} ({{ d.name }})
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Course *</label>
                <select
                  v-model="course"
                  class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2 py-1.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                >
                  <option v-for="c in getCoursesForDepartment(department)" :key="c.code" :value="c.code">
                    {{ c.code }} - {{ c.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Year Level</label>
                <select
                  v-model="yearLevel"
                  class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                >
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Section (NCST Format)</label>
                <input
                  type="text"
                  v-model="section"
                  placeholder="e.g. 11M1"
                  class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2 py-1.5 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                />
              </div>
            </div>
          </div>
        </template>

        <!-- 6. Dynamic Role Fields: Teacher (Faculty Department & Explicit Class Assignments) -->
        <template v-if="role === 'teacher'">
          <div class="p-3.5 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/40 rounded-xl space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-xs font-bold text-blue-900 dark:text-blue-300 flex items-center gap-1.5">
                <BookOpen class="w-4 h-4 text-blue-600" />
                <span>Teacher Academic Assignment</span>
              </p>
              <span class="text-[10px] text-blue-700 dark:text-blue-400 font-semibold">Admin Provisioned</span>
            </div>

            <!-- Controlled Department Selection -->
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                Department / Academic Division *
              </label>
              <select
                v-model="teacherDepartment"
                @change="onTeacherDeptChangeAdd"
                class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option v-for="d in NCST_DEPARTMENTS" :key="d.code" :value="d.code">
                  {{ d.code }} — {{ d.name }}
                </option>
              </select>
            </div>

            <!-- Assigned Classes Builder (Course + Section Pairs) -->
            <div class="space-y-2 pt-1">
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                Authorized Class Assignments (Course + Section)
              </label>
              <p class="text-[10px] text-slate-500 dark:text-slate-400">
                Each assignment is an independent Course + Section pair. Teachers only see students belonging to these exact classes.
              </p>

              <!-- Builder Input Row: Course + Unified Combobox Section + Add Button -->
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <select
                    v-model="newClassCourse"
                    class="w-44 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shrink-0"
                  >
                    <option v-for="c in getCoursesForDepartment(teacherDepartment)" :key="c.code" :value="c.code">
                      {{ c.code }} ({{ c.name }})
                    </option>
                  </select>

                  <!-- Unified Combobox: Section Input with Datalist & Dropdown Arrow -->
                  <div class="relative flex-1">
                    <input
                      type="text"
                      v-model="newClassSection"
                      list="ncst-sections-datalist-add"
                      placeholder="Select or type Section (e.g. 11M1)"
                      class="w-full bg-white dark:bg-[#1e1e1e] border rounded-lg px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none transition uppercase"
                      :class="!newClassSection.trim()
                        ? 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                        : (addClassSectionValidation.isValid && !isAddClassDuplicate ? 'border-emerald-500 ring-1 ring-emerald-500/20' : 'border-rose-400 ring-1 ring-rose-400/20')"
                      @keyup.enter.prevent="addTeacherClassToAddModal"
                    />
                    <datalist id="ncst-sections-datalist-add">
                      <option v-for="s in availablePresetSections" :key="s" :value="s">
                        {{ s }} ({{ validateNCSTSection(s).description }})
                      </option>
                    </datalist>
                  </div>

                  <button
                    type="button"
                    @click="addTeacherClassToAddModal"
                    :disabled="!canAddClassInAddModal"
                    class="px-4 py-2 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="canAddClassInAddModal ? 'bg-blue-600 hover:bg-blue-700 shadow-xs' : 'bg-slate-400 dark:bg-slate-700'"
                  >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Add Class</span>
                  </button>
                </div>

                <!-- Quick Selection Chips for Popular Sections -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 custom-scrollbar">
                  <span class="text-[10px] text-slate-400 font-semibold shrink-0">Quick presets:</span>
                  <button
                    v-for="s in ['11M1', '11M2', '12M1', '21M1', '21M2', '31M1', '31A1', '31A2', '41M1', '41M2', '41A1']"
                    :key="s"
                    type="button"
                    @click="newClassSection = s"
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border transition cursor-pointer shrink-0"
                    :class="newClassSection.toUpperCase() === s
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white dark:bg-[#222222] border-slate-200 dark:border-[#3a3a3a] text-slate-700 dark:text-slate-300 hover:border-blue-400'"
                  >
                    {{ s }}
                  </button>
                </div>

                <!-- Real-time Section Validation Feedback -->
                <div
                  v-if="newClassSection.trim()"
                  class="text-[11px] flex items-center gap-1.5 px-2.5 py-1 rounded-lg transition-all"
                  :class="isAddClassDuplicate
                    ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-900/40'
                    : (addClassSectionValidation.isValid
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/40'
                        : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900/40')"
                >
                  <span v-if="isAddClassDuplicate" class="font-semibold">
                    ⚠️ Already assigned: {{ newClassCourse }} — {{ newClassSection.toUpperCase() }} is in the list.
                  </span>
                  <span v-else-if="addClassSectionValidation.isValid" class="font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>✓ {{ addClassSectionValidation.description }}</span>
                  </span>
                  <span v-else class="font-semibold flex items-center gap-1">
                    <AlertTriangle class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>{{ addClassSectionValidation.error }}</span>
                  </span>
                </div>
              </div>

              <!-- List of Added Classes -->
              <div class="min-h-[48px] p-2 bg-white/70 dark:bg-black/30 border border-blue-200 dark:border-blue-900/40 rounded-xl">
                <div v-if="teacherAssignedClasses.length > 0" class="flex flex-wrap gap-1.5">
                  <span
                    v-for="(cls, idx) in teacherAssignedClasses"
                    :key="idx"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 text-xs font-bold font-mono border border-blue-200 dark:border-blue-800 shadow-xs"
                  >
                    <span>{{ cls.course }} — {{ cls.section }}</span>
                    <span v-if="cls.yearLevel" class="text-[10px] text-blue-600 dark:text-blue-300 font-sans font-normal">({{ cls.yearLevel }})</span>
                    <button
                      type="button"
                      @click="removeTeacherClassFromAddModal(idx)"
                      class="text-blue-500 hover:text-rose-600 transition ml-0.5"
                    >
                      <X class="w-3.5 h-3.5" />
                    </button>
                  </span>
                </div>
                <div v-else class="text-center py-2 text-xs text-slate-400 italic">
                  No classes assigned yet. Add Course + Section pairs above.
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- 7. Dynamic Role Fields: Event Staff -->
        <template v-if="role === 'event_staff'">
          <div class="p-3 bg-blue-50/40 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 rounded-xl space-y-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department / Operations Unit</label>
              <input
                type="text"
                v-model="department"
                placeholder="e.g. Campus Event Operations"
                class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Events Scope (Entrance Scanner)</label>
              <div class="space-y-1 max-h-32 overflow-y-auto p-2 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl custom-scrollbar">
                <label
                  v-for="ev in events"
                  :key="ev.id"
                  class="flex items-center gap-2 text-xs text-slate-800 dark:text-slate-200 cursor-pointer p-1 hover:bg-slate-100 dark:hover:bg-[#2c2c2c] rounded-lg"
                >
                  <input
                    type="checkbox"
                    :value="ev.id"
                    v-model="assignedEventIds"
                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="truncate font-medium">{{ ev.title }}</span>
                </label>
              </div>
            </div>
          </div>
        </template>

        <!-- 8. Dynamic Role Fields: Cashier -->
        <template v-if="role === 'cashier'">
          <div class="p-3.5 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 rounded-xl space-y-2 text-xs">
            <p class="font-bold text-amber-900 dark:text-amber-300">🏢 Cashier Operational Access Scope</p>
            <p class="text-[11px] text-amber-800 dark:text-amber-400">
              Cashier accounts are provisioned for POS Ticket Sales, Walk-in Registration, QR Printing, and Transaction Logs. No academic class assignments are required.
            </p>
          </div>
        </template>

        <!-- 9. Dynamic Role Fields: Admin -->
        <template v-if="role === 'admin'">
          <div class="p-3.5 bg-slate-100 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl space-y-2 text-xs">
            <p class="font-bold text-slate-900 dark:text-white">🛡️ System Administrator Scope</p>
            <p class="text-[11px] text-slate-600 dark:text-slate-400">
              Administrator accounts possess full governance, user account creation, system auditing, and event management capabilities.
            </p>
          </div>
        </template>

        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-[#333333]">
          <button
            type="button"
            @click="isAddUserModalOpen = false"
            class="px-4 py-2 bg-slate-100 dark:bg-[#2c2c2c] text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl hover:bg-slate-200 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="addConflict.hasConflict"
            class="px-5 py-2 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            :class="addConflict.hasConflict ? 'bg-slate-400 dark:bg-slate-700' : 'bg-blue-600 hover:bg-blue-700'"
          >
            Create Account
          </button>
        </div>
      </form>
    </Modal>

    <!-- ==================== 6. EDIT USER MODAL ==================== -->
    <Modal
      :is-open="isEditModalOpen"
      @close="isEditModalOpen = false"
      title="Edit User Account"
      subtitle="Update permissions, institutional role, and access scope without duplicating identities"
      max-width="md"
    >
      <form v-if="editingUser" @submit.prevent="handleSaveEdit" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
          <input
            type="text"
            v-model="editName"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            required
          />
        </div>

        <div v-if="editRole !== 'student'">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Institutional Email *</label>
          <input
            type="email"
            v-model="editEmail"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            :required="editRole !== 'student'"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Role * (Role Change Supported)</label>
          <select
            v-model="editRole"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="student">Student</option>
            <option value="event_staff">Event Staff</option>
            <option value="cashier">Cashier</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Administrator</option>
          </select>
        </div>

        <!-- Role-Specific Body: Student -->
        <template v-if="editRole === 'student'">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Student ID Number</label>
            <input
              type="text"
              v-model="editStudentId"
              placeholder="e.g. 2026-00125"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department</label>
            <select
              v-model="editDepartment"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            >
              <option v-for="d in NCST_DEPARTMENTS" :key="d.code" :value="d.code">
                {{ d.code }} — {{ d.name }}
              </option>
            </select>
          </div>
        </template>

        <!-- Role-Specific Body: Non-Student Employee ID -->
        <template v-else>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Employee ID Number</label>
            <input
              type="text"
              v-model="editEmployeeId"
              placeholder="e.g. EMP-00125"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>
        </template>

        <!-- Teacher Role: Controlled Department + Assigned Classes Builder -->
        <template v-if="editRole === 'teacher'">
          <div class="p-3.5 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/40 rounded-xl space-y-3">
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                Department / Faculty Division *
              </label>
              <select
                v-model="editDepartment"
                @change="onTeacherDeptChangeEdit"
                class="w-full bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option v-for="d in NCST_DEPARTMENTS" :key="d.code" :value="d.code">
                  {{ d.code }} — {{ d.name }}
                </option>
              </select>
            </div>

            <div class="space-y-2 pt-1">
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                Authorized Class Assignments (Course + Section)
              </label>

              <!-- Builder Input Row: Course + Unified Combobox Section + Add Button -->
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <select
                    v-model="editNewClassCourse"
                    class="w-44 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shrink-0"
                  >
                    <option v-for="c in getCoursesForDepartment(editDepartment)" :key="c.code" :value="c.code">
                      {{ c.code }} ({{ c.name }})
                    </option>
                  </select>

                  <!-- Unified Combobox: Section Input with Datalist & Dropdown Arrow -->
                  <div class="relative flex-1">
                    <input
                      type="text"
                      v-model="editNewClassSection"
                      list="ncst-sections-datalist-edit"
                      placeholder="Select or type Section (e.g. 11M1)"
                      class="w-full bg-white dark:bg-[#1e1e1e] border rounded-lg px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none transition uppercase"
                      :class="!editNewClassSection.trim()
                        ? 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                        : (editClassSectionValidation.isValid && !isEditClassDuplicate ? 'border-emerald-500 ring-1 ring-emerald-500/20' : 'border-rose-400 ring-1 ring-rose-400/20')"
                      @keyup.enter.prevent="addTeacherClassToEditModal"
                    />
                    <datalist id="ncst-sections-datalist-edit">
                      <option v-for="s in availablePresetSections" :key="s" :value="s">
                        {{ s }} ({{ validateNCSTSection(s).description }})
                      </option>
                    </datalist>
                  </div>

                  <button
                    type="button"
                    @click="addTeacherClassToEditModal"
                    :disabled="!canAddClassInEditModal"
                    class="px-4 py-2 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="canAddClassInEditModal ? 'bg-blue-600 hover:bg-blue-700 shadow-xs' : 'bg-slate-400 dark:bg-slate-700'"
                  >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Add Class</span>
                  </button>
                </div>

                <!-- Quick Selection Chips for Popular Sections -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 custom-scrollbar">
                  <span class="text-[10px] text-slate-400 font-semibold shrink-0">Quick presets:</span>
                  <button
                    v-for="s in ['11M1', '11M2', '12M1', '21M1', '21M2', '31M1', '31A1', '31A2', '41M1', '41M2', '41A1']"
                    :key="s"
                    type="button"
                    @click="editNewClassSection = s"
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold border transition cursor-pointer shrink-0"
                    :class="editNewClassSection.toUpperCase() === s
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white dark:bg-[#222222] border-slate-200 dark:border-[#3a3a3a] text-slate-700 dark:text-slate-300 hover:border-blue-400'"
                  >
                    {{ s }}
                  </button>
                </div>

                <!-- Real-time Section Validation Feedback -->
                <div
                  v-if="editNewClassSection.trim()"
                  class="text-[11px] flex items-center gap-1.5 px-2.5 py-1 rounded-lg transition-all"
                  :class="isEditClassDuplicate
                    ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-900/40'
                    : (editClassSectionValidation.isValid
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/40'
                        : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900/40')"
                >
                  <span v-if="isEditClassDuplicate" class="font-semibold">
                    ⚠️ Already assigned: {{ editNewClassCourse }} — {{ editNewClassSection.toUpperCase() }} is in the list.
                  </span>
                  <span v-else-if="editClassSectionValidation.isValid" class="font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>✓ {{ editClassSectionValidation.description }}</span>
                  </span>
                  <span v-else class="font-semibold flex items-center gap-1">
                    <AlertTriangle class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>{{ editClassSectionValidation.error }}</span>
                  </span>
                </div>
              </div>

              <!-- List of Added Classes -->
              <div class="min-h-[48px] p-2 bg-white/70 dark:bg-black/30 border border-blue-200 dark:border-blue-900/40 rounded-xl">
                <div v-if="editTeacherAssignedClasses.length > 0" class="flex flex-wrap gap-1.5">
                  <span
                    v-for="(cls, idx) in editTeacherAssignedClasses"
                    :key="idx"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 text-xs font-bold font-mono border border-blue-200 dark:border-blue-800 shadow-xs"
                  >
                    <span>{{ cls.course }} — {{ cls.section }}</span>
                    <span v-if="cls.yearLevel" class="text-[10px] text-blue-600 dark:text-blue-300 font-sans font-normal">({{ cls.yearLevel }})</span>
                    <button
                      type="button"
                      @click="removeTeacherClassFromEditModal(idx)"
                      class="text-blue-500 hover:text-rose-600 transition ml-0.5"
                    >
                      <X class="w-3.5 h-3.5" />
                    </button>
                  </span>
                </div>
                <div v-else class="text-center py-2 text-xs text-slate-400 italic">
                  No classes assigned yet. Add Course + Section pairs above.
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Event Staff Role: Assigned Events -->
        <template v-if="editRole === 'event_staff'">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department / Operations Unit</label>
            <input
              type="text"
              v-model="editDepartment"
              placeholder="e.g. Campus Event Operations"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Events</label>
            <div class="space-y-1 max-h-32 overflow-y-auto p-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl custom-scrollbar">
              <label
                v-for="ev in events"
                :key="ev.id"
                class="flex items-center gap-2 text-xs text-slate-800 dark:text-slate-200 cursor-pointer p-1 hover:bg-slate-100 dark:hover:bg-[#2c2c2c] rounded-lg"
              >
                <input
                  type="checkbox"
                  :value="ev.id"
                  v-model="editAssignedEventIds"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="truncate">{{ ev.title }}</span>
              </label>
            </div>
          </div>
        </template>

        <!-- Cashier / Admin Department -->
        <template v-if="editRole === 'cashier' || editRole === 'admin'">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Department / Office</label>
            <input
              type="text"
              v-model="editDepartment"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>
        </template>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Reset Password (Admin Assisted)
          </label>
          <input
            type="password"
            v-model="editPassword"
            placeholder="Leave blank to keep existing password"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
          />
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            Admins can set or reset passwords for employee and student accounts.
          </p>
        </div>

        <!-- Edit Form Conflict Alert -->
        <div
          v-if="editConflict.hasConflict"
          class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 flex-shrink-0" />
          <span>{{ editConflict.message }}</span>
        </div>

        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-[#333333]">
          <button
            type="button"
            @click="isEditModalOpen = false"
            class="px-4 py-2 bg-slate-100 dark:bg-[#2c2c2c] text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl hover:bg-slate-200 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="editConflict.hasConflict"
            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-50"
          >
            Save Changes
          </button>
        </div>
      </form>
    </Modal>

    <!-- ==================== 7. DELETE CONFIRMATION MODAL ==================== -->
    <ConfirmModal
      :is-open="!!userToDelete"
      title="Delete User Account"
      :message="`Are you sure you want to delete the account for ${userToDelete?.name} (${userToDelete?.studentId || userToDelete?.employeeId || userToDelete?.email})? This action cannot be undone.`"
      confirm-text="Delete Account"
      variant="danger"
      @confirm="confirmDeleteUser"
      @cancel="userToDelete = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  UserPlus,
  Trash2,
  Edit3,
  Power,
  Search,
  Users,
  AlertTriangle,
  AlertCircle,
  CheckCircle2,
  Info,
  Plus,
  X,
  GraduationCap,
  BookOpen
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { User, UserRole, TeacherClassAssignment } from '../../types';
import { checkUserIdentityConflict, normalizeEmail, normalizeId } from '../../utils/userValidation';
import {
  NCST_DEPARTMENTS,
  getCoursesForDepartment,
  deriveYearLevelFromSection,
  validateNCSTSection,
  extractActualSystemSections,
} from '../../utils/academicMasterData';
import UserAvatar from '../common/UserAvatar.vue';
import Modal from '../common/Modal.vue';
import ConfirmModal from '../common/ConfirmModal.vue';

const { users, events, studentRecords, addUser, updateUser, reactivateUser, deleteUser } = useAppStore();

// Filter & Search State
const selectedRoleFilter = ref<'all' | UserRole>('all');
const searchQuery = ref('');

// Modals & Drawer State
const isAddUserModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDrawerOpen = ref(false);
const selectedUserForDrawer = ref<User | null>(null);
const userToDelete = ref<User | null>(null);
const editingUser = ref<User | null>(null);

// Create Form State
const name = ref('');
const email = ref('');
const role = ref<UserRole>('student');
const studentId = ref('');
const employeeId = ref('');
const course = ref('BSIT');
const yearLevel = ref('3rd Year');
const section = ref('11M1');
const department = ref('CSD');
const assignedEventIds = ref<string[]>([]);

// Teacher-Specific Assignment State (Add Modal)
const teacherDepartment = ref('CSD');
const teacherAssignedClasses = ref<TeacherClassAssignment[]>([]);
const newClassCourse = ref('BSIT');
const newClassSection = ref('11M1');

// Edit Form State
const editName = ref('');
const editEmail = ref('');
const editRole = ref<UserRole>('student');
const editStudentId = ref('');
const editEmployeeId = ref('');
const editDepartment = ref('CSD');
const editPassword = ref('');
const editAssignedEventIds = ref<string[]>([]);
const editTeacherAssignedClasses = ref<TeacherClassAssignment[]>([]);
const editNewClassCourse = ref('BSIT');
const editNewClassSection = ref('11M1');

// Available Section presets computed strictly from authoritative existing system records
const availablePresetSections = computed(() => {
  return extractActualSystemSections(studentRecords.value, users.value);
});

// Section Real-time Validation (Add Modal)
const addClassSectionValidation = computed(() => {
  return validateNCSTSection(newClassSection.value);
});

const isAddClassDuplicate = computed(() => {
  const c = newClassCourse.value.trim().toUpperCase();
  const s = newClassSection.value.trim().toUpperCase();
  if (!c || !s) return false;
  return teacherAssignedClasses.value.some(
    item => item.course.toUpperCase() === c && item.section.toUpperCase() === s
  );
});

const canAddClassInAddModal = computed(() => {
  return (
    newClassCourse.value.trim() !== '' &&
    newClassSection.value.trim() !== '' &&
    addClassSectionValidation.value.isValid &&
    !isAddClassDuplicate.value
  );
});

// Section Real-time Validation (Edit Modal)
const editClassSectionValidation = computed(() => {
  return validateNCSTSection(editNewClassSection.value);
});

const isEditClassDuplicate = computed(() => {
  const c = editNewClassCourse.value.trim().toUpperCase();
  const s = editNewClassSection.value.trim().toUpperCase();
  if (!c || !s) return false;
  return editTeacherAssignedClasses.value.some(
    item => item.course.toUpperCase() === c && item.section.toUpperCase() === s
  );
});

const canAddClassInEditModal = computed(() => {
  return (
    editNewClassCourse.value.trim() !== '' &&
    editNewClassSection.value.trim() !== '' &&
    editClassSectionValidation.value.isValid &&
    !isEditClassDuplicate.value
  );
});

// Teacher Assignment Helpers
const addTeacherClassToAddModal = () => {
  const c = newClassCourse.value.trim().toUpperCase();
  const s = newClassSection.value.trim().toUpperCase();
  if (!canAddClassInAddModal.value) return;
  
  const validation = validateNCSTSection(s);
  teacherAssignedClasses.value.push({
    department: teacherDepartment.value,
    course: c,
    section: s,
    yearLevel: validation.yearLevel || deriveYearLevelFromSection(s),
  });
  newClassSection.value = '';
};

const removeTeacherClassFromAddModal = (index: number) => {
  teacherAssignedClasses.value.splice(index, 1);
};

const addTeacherClassToEditModal = () => {
  const c = editNewClassCourse.value.trim().toUpperCase();
  const s = editNewClassSection.value.trim().toUpperCase();
  if (!canAddClassInEditModal.value) return;
  
  const validation = validateNCSTSection(s);
  editTeacherAssignedClasses.value.push({
    department: editDepartment.value,
    course: c,
    section: s,
    yearLevel: validation.yearLevel || deriveYearLevelFromSection(s),
  });
  editNewClassSection.value = '';
};

const removeTeacherClassFromEditModal = (index: number) => {
  editTeacherAssignedClasses.value.splice(index, 1);
};

const onTeacherDeptChangeAdd = () => {
  const courses = getCoursesForDepartment(teacherDepartment.value);
  if (courses.length > 0) {
    newClassCourse.value = courses[0].code;
  }
};

const onTeacherDeptChangeEdit = () => {
  const courses = getCoursesForDepartment(editDepartment.value);
  if (courses.length > 0) {
    editNewClassCourse.value = courses[0].code;
  }
};

// Real-time Candidate Conflict Computation for Add User Form
const addConflict = computed(() => {
  if (!name.value && !email.value && !studentId.value && !employeeId.value) {
    return {
      hasConflict: false,
      conflictType: 'none' as const,
      isInactive: false,
      isCrossRole: false,
      isSameNameDifferentId: false,
      title: 'NO CONFLICT',
      message: '',
      actionSuggestion: 'allow' as const,
    };
  }

  return checkUserIdentityConflict(
    {
      name: name.value,
      email: role.value === 'student' ? undefined : email.value,
      role: role.value,
      studentId: role.value === 'student' ? studentId.value : undefined,
      employeeId: role.value !== 'student' ? (employeeId.value || studentId.value) : undefined,
    },
    users.value
  );
});

// Real-time Candidate Conflict Computation for Edit User Form
const editConflict = computed(() => {
  if (!editingUser.value) {
    return {
      hasConflict: false,
      conflictType: 'none' as const,
      isInactive: false,
      isCrossRole: false,
      isSameNameDifferentId: false,
      title: 'NO CONFLICT',
      message: '',
      actionSuggestion: 'allow' as const,
    };
  }

  return checkUserIdentityConflict(
    {
      id: editingUser.value.id,
      name: editName.value,
      email: editRole.value === 'student' ? undefined : editEmail.value,
      role: editRole.value,
      studentId: editRole.value === 'student' ? editStudentId.value : undefined,
      employeeId: editRole.value !== 'student' ? editEmployeeId.value : undefined,
    },
    users.value
  );
});

// Role Filter counts
const roleFilters = computed(() => [
  { id: 'all' as const, label: 'All', count: users.value.length },
  { id: 'admin' as const, label: 'Admin', count: users.value.filter(u => u.role === 'admin').length },
  { id: 'event_staff' as const, label: 'Event Staff', count: users.value.filter(u => u.role === 'event_staff').length },
  { id: 'cashier' as const, label: 'Cashier', count: users.value.filter(u => u.role === 'cashier').length },
  { id: 'teacher' as const, label: 'Teacher', count: users.value.filter(u => u.role === 'teacher').length },
  { id: 'student' as const, label: 'Student', count: users.value.filter(u => u.role === 'student').length },
]);

// Filtered Users computation
const filteredUsers = computed(() => {
  return users.value.filter(u => {
    // 1. Role filter
    if (selectedRoleFilter.value !== 'all' && u.role !== selectedRoleFilter.value) {
      return false;
    }

    // 2. Search query filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchName = u.name.toLowerCase().includes(q);
      const matchEmail = u.email.toLowerCase().includes(q);
      const matchStudentId = (u.studentId || '').toLowerCase().includes(q);
      const matchEmployeeId = (u.employeeId || '').toLowerCase().includes(q);
      const matchDept = (u.department || '').toLowerCase().includes(q);
      const matchCourse = (u.course || '').toLowerCase().includes(q);
      if (!matchName && !matchEmail && !matchStudentId && !matchEmployeeId && !matchDept && !matchCourse) {
        return false;
      }
    }

    return true;
  });
});

const formatRole = (r: UserRole) => {
  switch (r) {
    case 'admin': return 'Admin';
    case 'event_staff': return 'Event Staff';
    case 'cashier': return 'Cashier';
    case 'teacher': return 'Teacher';
    case 'student': return 'Student';
    default: return r;
  }
};

const getRoleBadgeClass = (r: UserRole) => {
  switch (r) {
    case 'admin': return 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700';
    case 'event_staff': return 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
    case 'cashier': return 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
    case 'teacher': return 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800';
    case 'student': return 'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
    default: return 'bg-slate-100 text-slate-700';
  }
};

const getEventTitle = (eventId: string) => {
  const ev = events.value.find(e => e.id === eventId);
  return ev ? ev.title : eventId;
};

const resetFilters = () => {
  selectedRoleFilter.value = 'all';
  searchQuery.value = '';
};

// Drawer (Progressive Disclosure)
const openDetailsDrawer = (u: User) => {
  selectedUserForDrawer.value = u;
  isDrawerOpen.value = true;
};

// Add Modal Handlers
const openAddModal = () => {
  name.value = '';
  email.value = '';
  role.value = selectedRoleFilter.value !== 'all' ? selectedRoleFilter.value : 'student';
  studentId.value = '';
  employeeId.value = '';
  course.value = 'BSIT';
  yearLevel.value = '1st Year';
  section.value = '11M1';
  department.value = 'CSD';
  assignedEventIds.value = [];
  teacherDepartment.value = 'CSD';
  teacherAssignedClasses.value = [];
  newClassCourse.value = 'BSIT';
  newClassSection.value = '11M1';
  isAddUserModalOpen.value = true;
};

const handleCreateUser = () => {
  if (!name.value.trim()) return;
  if (role.value !== 'student' && !email.value.trim()) return;
  if (role.value === 'student' && !studentId.value.trim()) return;

  const fallbackEmail = `${normalizeId(studentId.value).toLowerCase()}@student.ncst.edu.ph`;
  const assignedEmail = role.value === 'student'
    ? (email.value.trim() || fallbackEmail)
    : email.value.trim();

  const userPayload: Partial<User> = {
    name: name.value.trim(),
    email: assignedEmail,
    role: role.value,
    isActive: true,
  };

  if (role.value === 'student') {
    userPayload.studentId = studentId.value.trim() || undefined;
    userPayload.department = department.value.trim() || 'CSD';
    userPayload.course = course.value;
    userPayload.yearLevel = yearLevel.value;
    userPayload.section = section.value.trim() || '11M1';
  } else if (role.value === 'teacher') {
    userPayload.employeeId = employeeId.value.trim() || undefined;
    userPayload.department = teacherDepartment.value;
    userPayload.assignedClasses = teacherAssignedClasses.value.length > 0 ? [...teacherAssignedClasses.value] : [];
  } else if (role.value === 'event_staff') {
    userPayload.employeeId = employeeId.value.trim() || undefined;
    userPayload.department = department.value.trim() || 'Event Operations';
    userPayload.assignedEventIds = assignedEventIds.value.length > 0 ? assignedEventIds.value : undefined;
  } else if (role.value === 'cashier') {
    userPayload.employeeId = employeeId.value.trim() || undefined;
    userPayload.department = department.value.trim() || 'University Finance & Ticket Counter';
  } else if (role.value === 'admin') {
    userPayload.employeeId = employeeId.value.trim() || undefined;
    userPayload.department = department.value.trim() || 'System Governance & Operations';
  }

  const result = addUser(userPayload as any);

  if (result.success) {
    isAddUserModalOpen.value = false;
  }
};

// Edit Modal Handlers
const openEditModal = (u: User) => {
  editingUser.value = u;
  editName.value = u.name;
  editEmail.value = u.email;
  editRole.value = u.role;
  editStudentId.value = u.studentId || '';
  editEmployeeId.value = u.employeeId || '';
  editDepartment.value = u.department || 'CSD';
  editPassword.value = '';
  editAssignedEventIds.value = u.assignedEventIds ? [...u.assignedEventIds] : [];
  editTeacherAssignedClasses.value = u.assignedClasses ? JSON.parse(JSON.stringify(u.assignedClasses)) : [];
  const courses = getCoursesForDepartment(editDepartment.value);
  editNewClassCourse.value = courses.length > 0 ? courses[0].code : 'BSIT';
  editNewClassSection.value = '11M1';
  isEditModalOpen.value = true;
};

const handleEditFromConflict = (existing: User) => {
  isAddUserModalOpen.value = false;
  openEditModal(existing);
};

const handleReactivateFromConflict = (existing: User) => {
  reactivateUser(existing.id);
  isAddUserModalOpen.value = false;
  openDetailsDrawer(existing);
};

const handleSaveEdit = () => {
  if (!editingUser.value) return;

  const fallbackEmail = `${normalizeId(editStudentId.value || editingUser.value.studentId || '').toLowerCase()}@student.ncst.edu.ph`;
  const assignedEmail = editRole.value !== 'student'
    ? editEmail.value.trim()
    : (editingUser.value.email || fallbackEmail);

  const updatePayload: Partial<User> = {
    name: editName.value.trim(),
    email: assignedEmail,
    role: editRole.value,
    studentId: editRole.value === 'student' ? editStudentId.value.trim() || undefined : undefined,
    employeeId: editRole.value !== 'student' ? editEmployeeId.value.trim() || undefined : undefined,
    department: editDepartment.value.trim() || undefined,
    assignedEventIds: editRole.value === 'event_staff' ? editAssignedEventIds.value : undefined,
    assignedClasses: editRole.value === 'teacher' ? editTeacherAssignedClasses.value : undefined,
    ...(editPassword.value.trim() ? { password: editPassword.value.trim() } : {}),
  };

  const result = updateUser(editingUser.value.id, updatePayload);

  if (!result.success) return;

  // Update drawer if currently inspecting this user
  if (selectedUserForDrawer.value && selectedUserForDrawer.value.id === editingUser.value.id) {
    selectedUserForDrawer.value = {
      ...selectedUserForDrawer.value,
      ...updatePayload,
    };
  }

  isEditModalOpen.value = false;
  editingUser.value = null;
  editPassword.value = '';
};

const toggleActiveStatus = (u: User) => {
  const current = u.isActive !== false;
  if (!current) {
    // Reactivate preserving everything
    reactivateUser(u.id);
  } else {
    updateUser(u.id, { isActive: false });
  }

  if (selectedUserForDrawer.value && selectedUserForDrawer.value.id === u.id) {
    selectedUserForDrawer.value.isActive = !current;
  }
};

const handleReactivate = (u: User) => {
  reactivateUser(u.id);
};

const handleDeleteUser = (u: User) => {
  userToDelete.value = u;
};

const confirmDeleteUser = () => {
  if (userToDelete.value) {
    deleteUser(userToDelete.value.id);
    if (selectedUserForDrawer.value && selectedUserForDrawer.value.id === userToDelete.value.id) {
      isDrawerOpen.value = false;
      selectedUserForDrawer.value = null;
    }
    userToDelete.value = null;
  }
};
</script>
