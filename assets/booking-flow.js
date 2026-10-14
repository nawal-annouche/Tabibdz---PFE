import { state, goStep, setUpdatePaymentSummary } from "./booking-flow-core.js";
import {
  handlePatientChange,
  toggleChip,
  setVisit,
  toggleSms,
  handleFiles,
  validateStep2,
} from "./booking-flow-patient.js";
import {
  selectPaymentMethod,
  updatePaymentSummary,
  validatePaymentStep,
} from "./booking-flow-payment.js";
import {
  initBookingFlow,
  pickSlot,
  prevWeek,
  nextWeek,
  confirmRDV,
  addToGoogleCal,
  addToAppleCal,
  goBackToSlots,
} from "./booking-flow-appointments.js";

setUpdatePaymentSummary(updatePaymentSummary);

Object.assign(window, {
  state,
  goStep,
  handlePatientChange,
  toggleChip,
  setVisit,
  toggleSms,
  handleFiles,
  validateStep2,
  selectPaymentMethod,
  validatePaymentStep,
  pickSlot,
  prevWeek,
  nextWeek,
  confirmRDV,
  addToGoogleCal,
  addToAppleCal,
  goBackToSlots,
});

document.addEventListener("DOMContentLoaded", initBookingFlow);
