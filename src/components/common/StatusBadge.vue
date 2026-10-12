<template>
  <Badge v-if="type === 'attendance'" :variant="attendanceVariant" :dot="attendanceDot" :size="size">
    {{ attendanceText }}
  </Badge>

  <Badge v-else-if="type === 'ticket'" :variant="ticketVariant" :size="size">
    {{ ticketText }}
  </Badge>

  <Badge v-else-if="type === 'payment'" :variant="paymentVariant" :size="size">
    {{ paymentText }}
  </Badge>

  <Badge v-else-if="type === 'role'" :variant="roleVariant" :size="size">
    {{ roleText }}
  </Badge>

  <Badge v-else :size="size">
    {{ status }}
  </Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Badge from './Badge.vue';

const props = withDefaults(
  defineProps<{
    type?: 'ticket' | 'attendance' | 'payment' | 'role';
    status?: string | null;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  {
    type: 'ticket',
    status: '',
    size: 'md',
  }
);

const attendanceVariant = computed(() => {
  const s = (props.status || '').toLowerCase().replace(/_/g, ' ');
  switch (s) {
    case 'present': return 'success';
    case 'absent': return 'danger';
    case 'invalid':
    case 'already used':
    case 'duplicate': return 'danger';
    case 'not yet entered':
    case 'not entered':
    default: return 'warning';
  }
});
const attendanceDot = computed(() => true);
const attendanceText = computed(() => {
  const s = (props.status || '').toLowerCase().replace(/_/g, ' ');
  switch (s) {
    case 'present': return 'PRESENT';
    case 'absent': return 'ABSENT';
    case 'invalid': return 'INVALID';
    case 'already used': return 'ALREADY USED';
    case 'not yet entered':
    case 'not entered':
    default: return 'NOT YET ENTERED';
  }
});

const ticketVariant = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'active':
    case 'issued': return 'success';
    case 'used': return 'info';
    case 'transferred': return 'neutral';
    case 'refunded': return 'warning';
    case 'cancelled':
    case 'void': return 'danger';
    case 'expired': return 'neutral';
    default: return 'neutral';
  }
});
const ticketText = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'active':
    case 'issued': return 'ISSUED';
    case 'used': return 'USED';
    case 'transferred': return 'TRANSFERRED';
    case 'refunded': return 'REFUNDED';
    case 'cancelled':
    case 'void': return 'VOID';
    case 'expired': return 'EXPIRED';
    default: return props.status ? props.status.toUpperCase() : 'ISSUED';
  }
});

const paymentVariant = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'paid': return 'success';
    case 'free': return 'info';
    case 'refunded': return 'warning';
    default: return 'neutral';
  }
});
const paymentText = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'paid': return 'Paid';
    case 'free': return 'Free';
    case 'refunded': return 'Refunded';
    default: return props.status || 'Pending';
  }
});

const roleVariant = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'admin': return 'info';
    case 'cashier': return 'warning';
    case 'event_staff': return 'info';
    case 'teacher': return 'info';
    case 'student': return 'success';
    default: return 'neutral';
  }
});
const roleText = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'admin': return 'Admin';
    case 'cashier': return 'Cashier / Staff';
    case 'event_staff': return 'Event Staff';
    case 'teacher': return 'Faculty / Teacher';
    case 'student': return 'Student';
    default: return props.status || 'User';
  }
});
</script>
