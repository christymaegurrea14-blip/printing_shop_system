// ============================================================
// assets/js/app.js — Printing Shop Management System
// ============================================================

'use strict';

// ── Sidebar Toggle ──────────────────────────────────────────
const sidebar        = document.getElementById('sidebar');
const sidebarToggle  = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
  sidebar?.classList.add('open');
  sidebarOverlay?.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeSidebar() {
  sidebar?.classList.remove('open');
  sidebarOverlay?.classList.remove('active');
  document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', () => {
  sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
});

sidebarOverlay?.addEventListener('click', closeSidebar);

// ── Auto-calculate total in order form ─────────────────────
const qtyInput   = document.getElementById('quantity');
const priceInput = document.getElementById('unit_price');
const totalField = document.getElementById('total_amount');

function calcTotal() {
  if (!qtyInput || !priceInput || !totalField) return;
  const qty   = parseFloat(qtyInput.value)   || 0;
  const price = parseFloat(priceInput.value) || 0;
  totalField.value = (qty * price).toFixed(2);
}

qtyInput?.addEventListener('input',   calcTotal);
priceInput?.addEventListener('input', calcTotal);

// ── Print receipt ───────────────────────────────────────────
function printReceipt(receiptId) {
  const el = document.getElementById(receiptId);
  if (!el) return;
  const win = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head>
    <title>Receipt</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      body { font-family: 'JetBrains Mono', monospace; padding: 2rem; font-size: .85rem; }
      .receipt-header { text-align:center; border-bottom: 2px dashed #999; padding-bottom:1rem; margin-bottom:1rem; }
      .receipt-row { display:flex; justify-content:space-between; padding:.2rem 0; }
      .receipt-total { border-top: 2px dashed #999; margin-top:.75rem; padding-top:.75rem; font-weight:700; font-size:1rem; }
      h2 { font-size: 1.1rem; margin: 0 0 .25rem; }
      small { color: #555; }
    </style></head><body>
    ${el.innerHTML}
  </body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); win.close(); }, 400);
}

// ── Auto-dismiss flash alerts ───────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 300);
  }, 4000);
});

// ── Confirm before delete ───────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) {
      e.preventDefault();
    }
  });
});

// ── Search filter on table ──────────────────────────────────
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// Initialize search boxes if present
initTableSearch('searchOrders',    'ordersTable');
initTableSearch('searchCustomers', 'customersTable');
initTableSearch('searchPayments',  'paymentsTable');

// ── Price presets by print type ─────────────────────────────
const PRICE_PRESETS = {
  'black_white': 3,
  'colored':     10,
  'photo_print': 25,
  'tarpaulin':   180,
  'id_picture':  50,
};

const printTypeSelect = document.getElementById('print_type');
printTypeSelect?.addEventListener('change', function() {
  const preset = PRICE_PRESETS[this.value];
  if (preset && priceInput && !priceInput.value) {
    priceInput.value = preset.toFixed(2);
    calcTotal();
  }
});

// ── Report date range quick-fill ────────────────────────────
function setDateRange(type) {
  const startEl = document.getElementById('date_start');
  const endEl   = document.getElementById('date_end');
  if (!startEl || !endEl) return;

  const today = new Date();
  let start = new Date();

  if (type === 'today') {
    start = new Date();
  } else if (type === 'week') {
    start.setDate(today.getDate() - 6);
  } else if (type === 'month') {
    start = new Date(today.getFullYear(), today.getMonth(), 1);
  }

  startEl.value = start.toISOString().split('T')[0];
  endEl.value   = today.toISOString().split('T')[0];
}

// ── Tooltip init ────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
  new bootstrap.Tooltip(el);
});
