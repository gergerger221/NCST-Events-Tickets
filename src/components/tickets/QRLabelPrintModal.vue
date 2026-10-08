<template>
  <div>
    <!-- ==================== MAIN PRINT PREVIEW & CONFIGURATION MODAL ==================== -->
    <Modal
      :is-open="isOpen && !showConfirmationDialog"
      @close="handleModalClose"
      :max-width="isSingleTicket ? '2xl' : '4xl'"
      :title="isSingleTicket ? (isReprint ? 'Single QR Sticker Reprint' : 'Single QR Sticker Print') : `Batch QR Print Station (${ticketsToPrint.length} Stickers)`"
      :subtitle="isSingleTicket ? 'NCST Compact Single-Label Layout: Optimized to prevent paper waste when printing one ticket' : 'NCST Sheet Packing: Automatically arrange QR labels into efficient rows and columns on print sheets'"
    >
      <div class="space-y-4">
        
        <!-- ==================== 1. SINGLE TICKET MODE BANNER & SETTINGS ==================== -->
        <div v-if="isSingleTicket" class="space-y-3">
          <!-- Reprint / Single Print Notice -->
          <div
            class="p-3.5 rounded-2xl border text-xs flex items-start justify-between gap-3 transition-colors"
            :class="isReprint
              ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800'
              : 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800'"
          >
            <div class="flex items-start gap-2.5">
              <Tag class="w-5 h-5 shrink-0 mt-0.5" :class="isReprint ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400'" />
              <div class="space-y-0.5">
                <p class="font-extrabold" :class="isReprint ? 'text-amber-900 dark:text-amber-200' : 'text-blue-900 dark:text-blue-200'">
                  {{ isReprint ? `Reprinting QR Sticker — Print #${(singleTicket?.qrPrintCount || 0) + 1}` : 'Single Ticket QR Sticker Print' }}
                </p>
                <p class="text-[11px] leading-relaxed" :class="isReprint ? 'text-amber-700 dark:text-amber-300' : 'text-blue-700 dark:text-blue-300'">
                  {{ isReprint ? 'Security verified: Existing ticket number and QR credentials will be preserved. No new ticket is generated.' : 'Compact label layout sends only one sticker to the printer to prevent paper waste.' }}
                </p>
              </div>
            </div>

            <!-- Monospace Ticket Tag -->
            <span class="px-2.5 py-1 rounded-xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] font-mono font-black text-xs text-slate-900 dark:text-white shrink-0 shadow-xs">
              {{ singleTicket?.ticketNumber || singleTicket?.id }}
            </span>
          </div>

          <!-- Single Label Printer Target Configuration Toolbar -->
          <div class="p-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl text-xs space-y-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <Sliders class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span class="font-extrabold text-slate-800 dark:text-white uppercase tracking-wider text-[11px]">
                  Single Label Print Settings
                </span>
              </div>
              <span class="text-[10px] text-slate-500 font-mono">
                Single Label Form Factor
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
              <!-- Printer Form Factor Option -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Printer & Label Format
                </label>
                <select
                  v-model="singleFormat"
                  class="w-full py-2 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option value="thermal">Direct Sticker / Thermal Label (50mm × 50mm - Zero Waste)</option>
                  <option value="sheet_corner">Standard Paper Sheet (Single Corner Cut Guide)</option>
                </select>
              </div>

              <!-- Cut Border Style -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Sticker Border Guide
                </label>
                <select
                  v-model="borderStyle"
                  class="w-full py-2 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option value="solid">Solid Boundary Line (Standard)</option>
                  <option value="dashed">Dashed Cut Line</option>
                  <option value="minimal">Subtle Border</option>
                </select>
              </div>
            </div>

            <!-- Printer Hardware Explanation Note -->
            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic pt-1">
              💡 <strong>Paper-Efficiency Note:</strong> Direct thermal sticker printers print 1 label with <strong>zero paper waste</strong>. On standard A4 sheet printers, single reprints print 1 corner sticker with cut guides; use <strong>Batch Print</strong> whenever possible to pack multiple labels per sheet.
            </p>
          </div>

          <!-- Single Label Visual Live Preview Card -->
          <div class="flex flex-col items-center justify-center p-6 bg-slate-200/80 dark:bg-[#121212] rounded-2xl border border-slate-300 dark:border-[#2e2e2e]">
            <div
              v-if="singleTicket"
              class="w-[200px] h-[200px] bg-white text-slate-900 rounded-xl p-3 flex flex-col items-center justify-between select-none shadow-xl transition-all relative overflow-hidden"
              :class="[
                borderStyle === 'solid' ? 'border-2 border-slate-900' :
                borderStyle === 'dashed' ? 'border-2 border-dashed border-slate-800' :
                'border border-slate-400 shadow-sm'
              ]"
            >
              <!-- Top Header -->
              <div class="w-full flex items-center justify-between border-b border-slate-300 pb-1">
                <span class="text-[8.5px] font-black uppercase tracking-widest text-slate-900">NCST PASS</span>
                <span class="text-[8px] font-mono font-bold text-slate-600 truncate max-w-[100px]" :title="singleTicket.eventTitle">
                  {{ singleTicket.eventTitle || 'Campus Event' }}
                </span>
              </div>

              <!-- Center QR Code -->
              <div class="my-auto py-1 flex items-center justify-center">
                <img
                  v-if="qrMap[singleTicket.id || singleTicket.ticketNumber || '']"
                  :src="qrMap[singleTicket.id || singleTicket.ticketNumber || '']"
                  :alt="`QR for ${singleTicket.ticketNumber || singleTicket.id}`"
                  class="w-24 h-24 object-contain"
                />
                <div v-else class="w-24 h-24 flex items-center justify-center text-[10px] text-slate-400">
                  Generating QR...
                </div>
              </div>

              <!-- Bottom Details -->
              <div class="w-full text-center border-t border-slate-300 pt-1">
                <span class="font-mono font-black text-xs tracking-widest text-slate-950 block">
                  {{ singleTicket.ticketNumber || singleTicket.id }}
                </span>
                <p class="text-[8px] font-bold text-slate-600 truncate max-w-full">
                  {{ singleTicket.currentAttendeeName }}
                  <span v-if="singleTicket.attendeeStudentId" class="text-blue-600 font-mono"> ({{ singleTicket.attendeeStudentId }})</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== 2. BATCH MULTI-TICKET MODE BANNER & SETTINGS ==================== -->
        <div v-else class="space-y-4">
          <!-- Multi-Label Efficiency Banner -->
          <div class="no-print p-3.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-2xl flex items-start justify-between gap-3 text-xs">
            <div class="flex items-start gap-2.5">
              <Tag class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
              <div class="space-y-0.5">
                <p class="font-extrabold text-blue-900 dark:text-blue-200">
                  Multi-Label Sheet Grid Optimization (A4 / Letter)
                </p>
                <p class="text-blue-700 dark:text-blue-300 text-[11px] leading-relaxed">
                  Labels are packed tightly (<strong>{{ labelsPerSheet }} labels per sheet</strong>) to minimize paper waste.
                  Each sticker is sized uniformly for easy cutting and affixing to physical tickets.
                </p>
              </div>
            </div>

            <!-- Paper Savings Metric Badge -->
            <div class="hidden sm:flex flex-col items-end shrink-0 bg-emerald-100 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-800 px-3 py-1.5 rounded-xl text-right">
              <span class="text-[9px] font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                Paper Conservation
              </span>
              <span class="text-xs font-black text-emerald-900 dark:text-emerald-200">
                {{ paperSavedCount > 0 ? `Saves ${paperSavedCount} Sheet(s)!` : 'Maximum Efficiency' }}
              </span>
              <span class="text-[10px] text-emerald-700 dark:text-emerald-400">
                {{ totalSheets }} sheet(s) instead of {{ ticketsToPrint.length }}
              </span>
            </div>
          </div>

          <!-- Batch Print Configuration Toolbar -->
          <div class="no-print p-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl text-xs space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Sliders class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span class="font-extrabold text-slate-800 dark:text-white uppercase tracking-wider text-[11px]">
                  Batch Print Sheet Settings
                </span>
              </div>

              <!-- Summary indicator -->
              <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                <span class="px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 font-bold">
                  {{ columns }} Cols × {{ rows }} Rows
                </span>
                <span>= <strong>{{ labelsPerSheet }}</strong> labels/sheet</span>
                <span class="text-slate-400">•</span>
                <span><strong>{{ totalSheets }}</strong> total sheet(s)</span>
              </div>
            </div>

            <!-- Controls Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
              <!-- Paper Size -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Paper Size
                </label>
                <select
                  v-model="paperSize"
                  class="w-full py-1.5 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option value="a4">A4 (210 × 297 mm)</option>
                  <option value="letter">US Letter (8.5 × 11 in)</option>
                </select>
              </div>

              <!-- Orientation -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Orientation
                </label>
                <select
                  v-model="orientation"
                  class="w-full py-1.5 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option value="portrait">Portrait (Vertical)</option>
                  <option value="landscape">Landscape (Horizontal)</option>
                </select>
              </div>

              <!-- Columns Grid Layout -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Columns per Sheet
                </label>
                <select
                  v-model.number="columns"
                  class="w-full py-1.5 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option :value="2">2 Columns (Large Labels)</option>
                  <option :value="3">3 Columns (Standard 21/Sheet - Recommended)</option>
                  <option :value="4">4 Columns (High Density)</option>
                </select>
              </div>

              <!-- Cut Border Guides -->
              <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 dark:text-[#a0a0a0] mb-1">
                  Cut Border Guide
                </label>
                <select
                  v-model="borderStyle"
                  class="w-full py-1.5 px-2.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-slate-200 text-xs focus:outline-none focus:border-blue-500"
                >
                  <option value="solid">Solid Boundary Line</option>
                  <option value="dashed">Dashed Cut Guide</option>
                  <option value="minimal">Subtle Border</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Sheet Tabs for Multi-Sheet Batch -->
          <div v-if="totalSheets > 1" class="no-print flex items-center justify-between gap-3 border-b border-slate-200 dark:border-[#2e2e2e] pb-2 text-xs">
            <div class="flex items-center gap-1.5 overflow-x-auto py-1">
              <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-1">
                Sheets:
              </span>
              <button
                type="button"
                @click="previewViewMode = 'all'"
                class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                :class="previewViewMode === 'all'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'bg-slate-100 dark:bg-[#282828] text-slate-600 dark:text-slate-400 hover:bg-slate-200'"
              >
                All Sheets ({{ totalSheets }})
              </button>
              <button
                v-for="(sheet, sIndex) in sheets"
                :key="'tab-' + sIndex"
                type="button"
                @click="selectSheetTab(sIndex)"
                class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1"
                :class="previewViewMode === 'single' && currentPreviewSheetIndex === sIndex
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'bg-slate-100 dark:bg-[#282828] text-slate-600 dark:text-slate-400 hover:bg-slate-200'"
              >
                <span>Sheet {{ sIndex + 1 }}</span>
                <span class="text-[10px] opacity-80">({{ sheet.length }})</span>
              </button>
            </div>

            <div class="text-[11px] text-slate-500 font-semibold shrink-0">
              Total: {{ ticketsToPrint.length }} QR Label(s)
            </div>
          </div>

          <!-- Batch Interactive Sheet Canvas -->
          <div class="no-print w-full max-h-[380px] overflow-y-auto p-4 bg-slate-200/80 dark:bg-[#121212] rounded-2xl border border-slate-300 dark:border-[#2e2e2e]">
            <div class="space-y-6 flex flex-col items-center">
              <div
                v-for="(sheetTickets, sIdx) in displayedPreviewSheets"
                :key="'preview-sheet-' + sIdx"
                class="w-full max-w-[760px] bg-white text-slate-900 rounded-2xl shadow-xl border border-slate-300 p-4 transition-all"
              >
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 mb-3 text-xs">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[10px] font-black uppercase tracking-wider">
                      Sheet {{ previewViewMode === 'single' ? currentPreviewSheetIndex + 1 : sIdx + 1 }} of {{ totalSheets }}
                    </span>
                    <span class="font-bold text-slate-600 text-[11px]">
                      {{ sheetTickets.length }} Sticker Label(s) on this sheet
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-400 font-mono">
                    {{ paperSize.toUpperCase() }} {{ orientation.toUpperCase() }} • {{ columns }}×{{ rows }} Grid
                  </div>
                </div>

                <div
                  class="grid gap-2.5"
                  :style="{
                    gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`
                  }"
                >
                  <div
                    v-for="(t, index) in sheetTickets"
                    :key="'preview-' + (t.id || index)"
                    class="bg-white text-slate-900 rounded-lg p-2 flex flex-col items-center justify-between select-none relative overflow-hidden"
                    :class="[
                      borderStyle === 'solid' ? 'border-2 border-slate-900' :
                      borderStyle === 'dashed' ? 'border-2 border-dashed border-slate-800' :
                      'border border-slate-400 shadow-xs',
                      columns === 2 ? 'h-[155px]' : columns === 4 ? 'h-[110px]' : 'h-[130px]'
                    ]"
                  >
                    <div class="w-full flex items-center justify-between border-b border-slate-300 pb-1">
                      <span class="text-[7.5px] font-black uppercase tracking-widest text-slate-800">NCST PASS</span>
                      <span class="text-[7.5px] font-mono font-bold text-slate-500 truncate max-w-[90px]">
                        {{ t.eventTitle || 'Campus Event' }}
                      </span>
                    </div>

                    <div class="my-auto py-0.5 flex items-center justify-center">
                      <img
                        v-if="qrMap[t.id || t.ticketNumber || '']"
                        :src="qrMap[t.id || t.ticketNumber || '']"
                        :alt="`QR for ${t.ticketNumber || t.id}`"
                        class="object-contain"
                        :class="columns === 2 ? 'w-20 h-20' : columns === 4 ? 'w-14 h-14' : 'w-16 h-16'"
                      />
                      <div v-else class="w-16 h-16 flex items-center justify-center text-[9px] text-slate-400">
                        QR...
                      </div>
                    </div>

                    <div class="w-full text-center border-t border-slate-300 pt-1 pb-0.5">
                      <span class="font-mono font-black tracking-widest text-slate-950 block" :class="columns === 4 ? 'text-[10px]' : 'text-xs'">
                        {{ t.ticketNumber || t.id }}
                      </span>
                    </div>
                  </div>

                  <!-- Empty slots on last sheet -->
                  <div
                    v-for="emptyIdx in (labelsPerSheet - sheetTickets.length)"
                    :key="'empty-' + emptyIdx"
                    class="border border-dashed border-slate-200 rounded-lg p-2 flex flex-col items-center justify-center text-slate-300 bg-slate-50/50"
                    :class="columns === 2 ? 'h-[155px]' : columns === 4 ? 'h-[110px]' : 'h-[130px]'"
                  >
                    <span class="text-[9px] font-mono text-slate-300 font-bold">[ Empty Slot ]</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== MODAL FOOTER ACTIONS ==================== -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-[#333333]">
          <div class="text-xs text-slate-500 dark:text-[#b3b3b3]">
            <span v-if="isSingleTicket">
              Single Sticker Ready: <strong class="text-slate-800 dark:text-slate-200">{{ singleTicket?.ticketNumber || singleTicket?.id }}</strong>
            </span>
            <span v-else>
              Batch Ready: <strong class="text-slate-800 dark:text-slate-200">{{ ticketsToPrint.length }} Sticker(s) on {{ totalSheets }} Sheet(s)</strong>
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="handleModalClose"
              class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
            >
              Cancel
            </button>

            <!-- SINGLE TICKET ACTION BUTTON -->
            <button
              v-if="isSingleTicket"
              type="button"
              @click="handleTriggerBrowserPrint"
              class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-md transition cursor-pointer"
            >
              <Printer class="w-4 h-4" />
              <span>{{ isReprint ? 'Reprint Single QR Label' : 'Print Single QR Label' }}</span>
            </button>

            <!-- BATCH ACTION BUTTON -->
            <button
              v-else
              type="button"
              @click="handleTriggerBrowserPrint"
              class="flex items-center gap-2 px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-md transition cursor-pointer"
            >
              <Printer class="w-4 h-4" />
              <span>
                {{ totalSheets > 1 ? `Print ${totalSheets} Sheets (${ticketsToPrint.length} Stickers)` : `Print ${ticketsToPrint.length} Stickers (1 Sheet)` }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- ==================== POST-PRINT CONFIRMATION MODAL ==================== -->
    <Modal
      :is-open="showConfirmationDialog"
      @close="handleKeepPending"
      max-width="md"
      title="Confirm QR Label Print Success"
      subtitle="NCST Queue Integrity Protection: Confirm physical printing before updating queue records"
    >
      <div class="space-y-4 text-xs">
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
          <Printer class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
          <div class="space-y-1">
            <p class="font-extrabold text-amber-900 dark:text-amber-200">
              {{ isSingleTicket ? 'Did the single QR sticker label print properly?' : 'Did the QR label sheet(s) print properly on paper?' }}
            </p>
            <p class="text-amber-700 dark:text-amber-300 text-[11px] leading-relaxed">
              If the printer jammed, was cancelled, or ran out of sticker paper, choose <strong>Keep as Pending</strong> so you can reprint without losing tickets.
            </p>
          </div>
        </div>

        <div class="bg-slate-50 dark:bg-[#242424] p-3 rounded-xl border border-slate-200 dark:border-[#333333] space-y-1 font-mono text-[11px]">
          <div class="flex justify-between text-slate-600 dark:text-slate-300">
            <span>Labels Processed:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ pendingPrintedIds.length }} Sticker(s)</span>
          </div>
          <div class="flex justify-between text-slate-600 dark:text-slate-300">
            <span>Format:</span>
            <span class="font-bold text-slate-900 dark:text-white">
              {{ isSingleTicket ? (singleFormat === 'thermal' ? '50mm × 50mm Thermal Sticker' : 'Single Corner Cut Label') : `${totalSheets} Sheet(s) (${paperSize.toUpperCase()})` }}
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-[#2e2e2e]">
          <button
            type="button"
            @click="handleKeepPending"
            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
          >
            Keep Pending (Reprint Later)
          </button>

          <button
            type="button"
            @click="handleConfirmPrinted"
            class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md transition cursor-pointer"
          >
            <CheckCircle2 class="w-4 h-4" />
            <span>Yes, Mark as Printed</span>
          </button>
        </div>
      </div>
    </Modal>

    <!-- ==================== TELEPORTED PRINTABLE CONTAINERS ==================== -->
    <Teleport to="body">
      <!-- CONTAINER 1: SINGLE TICKET PRINT CONTAINER (Zero paper waste on thermal printers / compact corner cut) -->
      <div
        v-if="(isOpen || showConfirmationDialog) && isSingleTicket && singleTicket"
        id="qr-single-label-printable"
        class="hidden"
      >
        <div
          class="single-label-card bg-white text-slate-900 p-2 flex flex-col items-center justify-between select-none"
          :style="{
            width: singleFormat === 'thermal' ? '48mm' : '52mm',
            height: singleFormat === 'thermal' ? '48mm' : '52mm',
            boxSizing: 'border-box'
          }"
          :class="[
            borderStyle === 'solid' ? 'border-2 border-black' :
            borderStyle === 'dashed' ? 'border-2 border-dashed border-black' :
            'border border-black'
          ]"
        >
          <!-- Top Micro Header -->
          <div class="w-full flex items-center justify-between border-b border-black pb-0.5">
            <span class="text-[7pt] font-black uppercase tracking-widest text-black">NCST PASS</span>
            <span class="text-[6.5pt] font-mono font-bold text-black truncate max-w-[100px]">
              {{ singleTicket.eventTitle || 'Campus Event' }}
            </span>
          </div>

          <!-- Middle High-Contrast QR Code -->
          <div class="my-auto py-0.5 flex items-center justify-center">
            <img
              v-if="qrMap[singleTicket.id || singleTicket.ticketNumber || '']"
              :src="qrMap[singleTicket.id || singleTicket.ticketNumber || '']"
              :alt="`QR for ${singleTicket.ticketNumber || singleTicket.id}`"
              class="w-20 h-20 object-contain"
            />
          </div>

          <!-- Bottom Monospace Ticket Number and Attendee -->
          <div class="w-full text-center border-t border-black pt-0.5">
            <span class="font-mono text-[8.5pt] font-black tracking-widest text-black block leading-tight">
              {{ singleTicket.ticketNumber || singleTicket.id }}
            </span>
            <p class="text-[6.5pt] font-bold text-black truncate max-w-full leading-tight">
              {{ singleTicket.currentAttendeeName }}
              <span v-if="singleTicket.attendeeStudentId"> ({{ singleTicket.attendeeStudentId }})</span>
            </p>
          </div>
        </div>
      </div>

      <!-- CONTAINER 2: BATCH SHEETS PRINT CONTAINER (Optimized Multi-Label Grid) -->
      <div
        v-if="(isOpen || showConfirmationDialog) && !isSingleTicket"
        id="qr-labels-printable-sheet"
        class="hidden"
      >
        <div
          v-for="(sheetTickets, sIdx) in sheets"
          :key="'print-sheet-' + sIdx"
          class="print-sheet-page bg-white text-slate-900"
          :style="{
            minHeight: paperSize === 'a4' ? (orientation === 'portrait' ? '285mm' : '200mm') : (orientation === 'portrait' ? '270mm' : '205mm'),
            boxSizing: 'border-box'
          }"
        >
          <div
            class="grid gap-2"
            :style="{
              gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`
            }"
          >
            <div
              v-for="(t, index) in sheetTickets"
              :key="'print-label-' + (t.id || index)"
              class="qr-sticker-print-cell bg-white text-slate-900 p-2 flex flex-col items-center justify-between select-none shrink-0 relative overflow-hidden"
              :class="[
                borderStyle === 'solid' ? 'border-2 border-black' :
                borderStyle === 'dashed' ? 'border-2 border-dashed border-black' :
                'border border-black',
                columns === 2 ? 'h-[50mm]' : columns === 4 ? 'h-[30mm]' : 'h-[36mm]'
              ]"
            >
              <div class="w-full flex items-center justify-between border-b border-black pb-0.5">
                <span class="text-[7.5pt] font-black uppercase tracking-widest text-black">NCST PASS</span>
                <span class="text-[7.5pt] font-mono font-bold text-black truncate max-w-[120px]">
                  {{ t.eventTitle || 'Campus Event' }}
                </span>
              </div>

              <div class="my-auto py-0.5 flex items-center justify-center">
                <img
                  v-if="qrMap[t.id || t.ticketNumber || '']"
                  :src="qrMap[t.id || t.ticketNumber || '']"
                  :alt="`QR for ${t.ticketNumber || t.id}`"
                  class="object-contain"
                  :class="columns === 2 ? 'w-20 h-20' : columns === 4 ? 'w-14 h-14' : 'w-16 h-16'"
                />
              </div>

              <div class="w-full text-center border-t border-black pt-1 pb-0.5">
                <span class="font-mono text-[9pt] font-black tracking-widest text-black block">
                  {{ t.ticketNumber || t.id }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import QRCode from 'qrcode';
import { Printer, Tag, Sliders, CheckCircle2 } from 'lucide-vue-next';
import { Ticket } from '../../types';
import { useAppStore } from '../../composables/useAppStore';
import Modal from '../common/Modal.vue';

const props = defineProps<{
  isOpen: boolean;
  ticket?: Ticket | null;
  tickets?: Ticket[];
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'printed', ticketIds: string[]): void;
}>();

const { markQrAsPrinted } = useAppStore();

// Single Ticket Print Format Target
const singleFormat = ref<'thermal' | 'sheet_corner'>('thermal');

// Batch Sheet Settings Configuration
const paperSize = ref<'a4' | 'letter'>('a4');
const orientation = ref<'portrait' | 'landscape'>('portrait');
const columns = ref<number>(3); // Default: 3 columns (21 labels per A4 sheet)
const borderStyle = ref<'solid' | 'dashed' | 'minimal'>('solid');

// Preview View Mode
const previewViewMode = ref<'all' | 'single'>('all');
const currentPreviewSheetIndex = ref(0);

// Confirmation Dialog State
const showConfirmationDialog = ref(false);
const pendingPrintedIds = ref<string[]>([]);

const qrMap = ref<Record<string, string>>({});

const ticketsToPrint = computed<Ticket[]>(() => {
  if (props.tickets && props.tickets.length > 0) {
    return props.tickets;
  }
  if (props.ticket) {
    return [props.ticket];
  }
  return [];
});

const isSingleTicket = computed<boolean>(() => {
  return ticketsToPrint.value.length === 1;
});

const singleTicket = computed<Ticket | null>(() => {
  return ticketsToPrint.value[0] || null;
});

const isReprint = computed<boolean>(() => {
  return isSingleTicket.value && (singleTicket.value?.qrPrintCount || 0) > 0;
});

// Dynamic Row Calculation based on paper, orientation, and column density
const rows = computed<number>(() => {
  if (orientation.value === 'portrait') {
    if (columns.value === 2) return 5; // 2 x 5 = 10 labels
    if (columns.value === 3) return 7; // 3 x 7 = 21 labels (NCST standard A4)
    if (columns.value === 4) return 9; // 4 x 9 = 36 labels
    return 7;
  } else {
    // Landscape
    if (columns.value === 2) return 3; // 2 x 3 = 6 labels
    if (columns.value === 3) return 4; // 3 x 4 = 12 labels
    if (columns.value === 4) return 5; // 4 x 5 = 20 labels
    return 4;
  }
});

const labelsPerSheet = computed<number>(() => {
  return columns.value * rows.value;
});

// Chunk tickets into sheets
const sheets = computed<Ticket[][]>(() => {
  const allTickets = ticketsToPrint.value;
  if (allTickets.length === 0) return [];
  const perSheet = labelsPerSheet.value;
  const chunkList: Ticket[][] = [];
  for (let i = 0; i < allTickets.length; i += perSheet) {
    chunkList.push(allTickets.slice(i, i + perSheet));
  }
  return chunkList;
});

const totalSheets = computed<number>(() => sheets.value.length || 1);

const paperSavedCount = computed<number>(() => {
  return Math.max(0, ticketsToPrint.value.length - totalSheets.value);
});

const displayedPreviewSheets = computed<Ticket[][]>(() => {
  if (previewViewMode.value === 'single') {
    const single = sheets.value[currentPreviewSheetIndex.value];
    return single ? [single] : [];
  }
  return sheets.value;
});

const selectSheetTab = (idx: number) => {
  currentPreviewSheetIndex.value = idx;
  previewViewMode.value = 'single';
};

const generateAllQRs = async () => {
  const map: Record<string, string> = {};
  for (const t of ticketsToPrint.value) {
    const key = t.id || t.ticketNumber || '';
    const token = t.qrToken || t.ticketNumber || t.id;
    if (key && token) {
      try {
        map[key] = await QRCode.toDataURL(token, {
          width: 180,
          margin: 1,
          errorCorrectionLevel: 'H',
          color: {
            dark: '#000000',
            light: '#ffffff',
          },
        });
      } catch (err) {
        console.error('Failed to generate QR for ticket:', key, err);
      }
    }
  }
  qrMap.value = map;
};

watch(
  () => [props.isOpen, props.ticket, props.tickets],
  () => {
    if (props.isOpen) {
      generateAllQRs();
      showConfirmationDialog.value = false;
      previewViewMode.value = 'all';
      currentPreviewSheetIndex.value = 0;
    }
  },
  { immediate: true, deep: true }
);

onMounted(() => {
  if (props.isOpen) {
    generateAllQRs();
  }
});

const handleTriggerBrowserPrint = () => {
  const ids = ticketsToPrint.value.map(t => t.id || t.ticketNumber || '').filter(Boolean);
  pendingPrintedIds.value = ids;

  if (isSingleTicket.value) {
    document.body.classList.add('is-printing-single-label');
    setTimeout(() => {
      window.print();
      setTimeout(() => {
        document.body.classList.remove('is-printing-single-label');
        showConfirmationDialog.value = true;
      }, 400);
    }, 50);
  } else {
    document.body.classList.add('is-printing-qr-label');
    setTimeout(() => {
      window.print();
      setTimeout(() => {
        document.body.classList.remove('is-printing-qr-label');
        showConfirmationDialog.value = true;
      }, 400);
    }, 50);
  }
};

const handleConfirmPrinted = () => {
  markQrAsPrinted(pendingPrintedIds.value);
  emit('printed', pendingPrintedIds.value);
  showConfirmationDialog.value = false;
  emit('close');
};

const handleKeepPending = () => {
  // Retain tickets as PENDING without marking as printed
  showConfirmationDialog.value = false;
  emit('close');
};

const handleModalClose = () => {
  showConfirmationDialog.value = false;
  emit('close');
};
</script>

<style scoped>
.qr-sticker-print-cell {
  page-break-inside: avoid;
  break-inside: avoid;
}
</style>
