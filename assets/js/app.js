(() => {
  'use strict';
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];

  const sidebar = $('#sidebar');
  const mobileOverlay = $('#mobileOverlay');
  const desktopToggle = $('#sidebarToggle');
  const mobileToggle = $('#mobileMenuToggle');
  const isMobile = () => window.innerWidth <= 900;

  const setSidebar = (collapsed) => {
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed', collapsed);
    localStorage.setItem('mediflow.sidebar.collapsed', collapsed ? '1' : '0');
  };
  if (sidebar && !isMobile()) setSidebar(localStorage.getItem('mediflow.sidebar.collapsed') === '1');
  desktopToggle?.addEventListener('click', () => setSidebar(!sidebar.classList.contains('collapsed')));
  const closeMobile = () => { sidebar?.classList.remove('mobile-open'); mobileOverlay?.classList.remove('open'); };
  mobileToggle?.addEventListener('click', () => { sidebar?.classList.toggle('mobile-open'); mobileOverlay?.classList.toggle('open'); });
  mobileOverlay?.addEventListener('click', closeMobile);
  window.addEventListener('resize', () => { if (!isMobile()) closeMobile(); });

  $$('[data-modal-open]').forEach(btn => btn.addEventListener('click', () => {
    const modal = document.getElementById(btn.dataset.modalOpen);
    modal?.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('input:not([type=hidden]),select,textarea', modal)?.focus(), 80);
  }));
  $$('[data-modal-close]').forEach(btn => btn.addEventListener('click', () => {
    btn.closest('.modal-backdrop')?.classList.remove('open');
    document.body.style.overflow = '';
  }));
  $$('.modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', e => {
    if (e.target === backdrop) { backdrop.classList.remove('open'); document.body.style.overflow = ''; }
  }));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { $$('.modal-backdrop.open').forEach(m => m.classList.remove('open')); document.body.style.overflow = ''; }
  });

  $$('[data-confirm]').forEach(el => el.addEventListener('click', e => {
    if (!window.confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  }));

  $$('[data-password-toggle]').forEach(btn => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.passwordToggle);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? 'Show' : 'Hide';
  }));

  const toastStack = $('#toastStack');
  window.MediFlow = window.MediFlow || {};
  window.MediFlow.toast = (message, type = 'info', title = 'MediFlow') => {
    if (!toastStack) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<div>●</div><div><strong>${escapeHtml(title)}</strong><p>${escapeHtml(message)}</p></div>`;
    toastStack.append(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(12px)'; setTimeout(() => toast.remove(), 220); }, 4500);
  };
  $$('.toast').forEach(toast => setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 220); }, 5000));

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  const appointmentForm = $('#appointmentForm');
  if (appointmentForm) {
    const dept = $('#department_id', appointmentForm);
    const doctor = $('#doctor_id', appointmentForm);
    const date = $('#appointment_date', appointmentForm);
    const slots = $('#slotGrid', appointmentForm);
    const startInput = $('#start_time', appointmentForm);
    const endInput = $('#end_time', appointmentForm);
    const slotMessage = $('#slotMessage', appointmentForm);
    const apiUrl = appointmentForm.dataset.api;

    const loadDoctors = async () => {
      if (!doctor || !dept) return;
      doctor.innerHTML = '<option value="">Loading...</option>';
      try {
        const response = await fetch(`${apiUrl}?action=doctors&department_id=${encodeURIComponent(dept.value)}`, {credentials:'same-origin'});
        const data = await response.json();
        doctor.innerHTML = '<option value="">Select doctor</option>' + (data.doctors || []).map(d => `<option value="${d.doctor_id}">${escapeHtml(d.full_name)} — ${escapeHtml(d.specialization)} (${escapeHtml(d.consultation_fee)})</option>`).join('');
      } catch { doctor.innerHTML = '<option value="">Could not load doctors</option>'; }
      slots.innerHTML = '';
      startInput.value = ''; endInput.value = '';
    };
    const loadSlots = async () => {
      slots.innerHTML = '';
      startInput.value = ''; endInput.value = '';
      if (!doctor.value || !date.value) { slotMessage.textContent = 'Choose a doctor and date to see available slots.'; return; }
      slotMessage.textContent = 'Loading available slots...';
      try {
        const response = await fetch(`${apiUrl}?action=slots&doctor_id=${encodeURIComponent(doctor.value)}&date=${encodeURIComponent(date.value)}`, {credentials:'same-origin'});
        const data = await response.json();
        const available = (data.slots || []).filter(s => s.available);
        slotMessage.textContent = data.message || (available.length ? 'Select one available time.' : 'No available slots for this date.');
        slots.innerHTML = (data.slots || []).map(s => `<button type="button" class="slot ${s.available ? '' : 'unavailable'}" ${s.available ? '' : 'disabled'} data-time="${s.time}" data-end="${s.end_time}">${escapeHtml(s.label)}</button>`).join('');
        $$('.slot:not(.unavailable)', slots).forEach(btn => btn.addEventListener('click', () => {
          $$('.slot', slots).forEach(b => b.classList.remove('selected'));
          btn.classList.add('selected'); startInput.value = btn.dataset.time; endInput.value = btn.dataset.end;
        }));
      } catch { slotMessage.textContent = 'Could not load appointment slots.'; }
    };
    dept?.addEventListener('change', loadDoctors);
    doctor?.addEventListener('change', loadSlots);
    date?.addEventListener('change', loadSlots);
  }

  const medicineRows = $('#medicineRows');
  if (medicineRows) {
    const template = $('#medicineRowTemplate');
    const addBtn = $('#addMedicineRow');
    const api = medicineRows.dataset.api;
    const patientId = medicineRows.dataset.patientId;

    const attachRow = row => {
      $('.remove-medicine', row)?.addEventListener('click', () => {
        const rows = $$('.medicine-row', medicineRows);
        if (rows.length > 1) row.remove(); else $$('input,select', row).forEach(i => i.value = '');
      });
      $('.medicine-select', row)?.addEventListener('change', async e => {
        const box = $('.medicine-warning', row);
        box.classList.add('hide'); box.innerHTML = '';
        if (!e.target.value) return;
        try {
          const response = await fetch(`${api}?action=medicine-warning&patient_id=${encodeURIComponent(patientId)}&medicine_id=${encodeURIComponent(e.target.value)}`, {credentials:'same-origin'});
          const data = await response.json();
          if (data.warnings?.length) {
            box.classList.remove('hide');
            box.innerHTML = `<strong>Allergy warning</strong><br>${data.warnings.map(w => escapeHtml(`${w.allergy_name} (${w.severity || 'severity not specified'}): ${w.warning_message || 'Potential conflict'}`)).join('<br>')}`;
            $('#overrideWrap')?.classList.remove('hide');
          }
        } catch {/* server validation remains authoritative */}
      });
    };
    $$('.medicine-row', medicineRows).forEach(attachRow);
    addBtn?.addEventListener('click', () => {
      const fragment = template.content.cloneNode(true);
      medicineRows.append(fragment);
      attachRow($('.medicine-row:last-child', medicineRows));
    });
  }

  $$('[data-auto-submit]').forEach(el => el.addEventListener('change', () => el.form?.submit()));

  const filterInput = $('[data-table-filter]');
  if (filterInput) {
    const tableId = filterInput.dataset.tableFilter;
    const rows = $$(`#${tableId} tbody tr`);
    filterInput.addEventListener('input', () => {
      const q = filterInput.value.toLowerCase().trim();
      rows.forEach(row => row.classList.toggle('hide', q && !row.textContent.toLowerCase().includes(q)));
    });
  }

  $$('[data-number]').forEach(el => {
    const target = Number(el.dataset.number || el.textContent || 0);
    if (!Number.isFinite(target) || target > 99999) return;
    const duration = 450, start = performance.now();
    const tick = now => {
      const p = Math.min(1, (now - start) / duration);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });

  const printButtons = $$('[data-print]');
  printButtons.forEach(btn => btn.addEventListener('click', () => window.print()));
})();
