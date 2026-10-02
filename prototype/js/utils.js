/**
 * Utils — helper format, badge class, toast notification, dan confirm modal.
 * Semua elemen dibuat lewat DOM API murni (tanpa library).
 */
(function (global) {
  function formatCurrency(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
  }

  function formatDate(isoDate) {
    if (!isoDate) return '-';
    const d = new Date(isoDate.length <= 10 ? isoDate + 'T00:00:00' : isoDate);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function formatDateTime(isoDate) {
    if (!isoDate) return '-';
    const d = new Date(isoDate);
    return (
      d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' ' +
      d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
    );
  }

  function badgeClass(status) {
    return String(status || '').toLowerCase().replace(/\s+/g, '');
  }

  function badgeHtml(status) {
    const cls = badgeClass(status);
    const pulse = cls === 'pendingapproval' ? '<span class="dot"></span>' : '';
    return `<span class="badge ${cls}">${pulse}${status}</span>`;
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }

  // ---------------------------------------------------------------------
  // Toast
  // ---------------------------------------------------------------------
  function ensureToastStack() {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }
    return stack;
  }

  function toast(message, type) {
    const stack = ensureToastStack();
    const el = document.createElement('div');
    el.className = `toast ${type || ''}`.trim();
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => {
      el.classList.add('leaving');
      setTimeout(() => el.remove(), 250);
    }, 3200);
  }

  // ---------------------------------------------------------------------
  // Confirm modal (pengganti window.confirm bawaan browser)
  // ---------------------------------------------------------------------
  function confirmDialog({ title, message, confirmLabel, danger }) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.innerHTML = `
        <div class="modal-card" role="dialog" aria-modal="true">
          <h3>${escapeHtml(title || 'Konfirmasi')}</h3>
          <p>${escapeHtml(message || '')}</p>
          <div class="modal-actions">
            <button type="button" class="btn ghost" data-cancel>Batal</button>
            <button type="button" class="btn ${danger ? 'danger' : 'primary'}" data-confirm>${escapeHtml(
        confirmLabel || 'Ya, lanjutkan'
      )}</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);

      function close(result) {
        overlay.remove();
        resolve(result);
      }
      overlay.querySelector('[data-cancel]').addEventListener('click', () => close(false));
      overlay.querySelector('[data-confirm]').addEventListener('click', () => close(true));
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close(false);
      });
      document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', escHandler);
          close(false);
        }
      });
    });
  }

  // ---------------------------------------------------------------------
  // Skeleton loading row untuk tabel yang masih memuat data
  // ---------------------------------------------------------------------
  function skeletonRows(colCount, rowCount) {
    const rows = [];
    for (let r = 0; r < (rowCount || 5); r++) {
      const cells = Array.from({ length: colCount }, () => '<td><div class="skeleton"></div></td>').join('');
      rows.push(`<tr class="skeleton-row">${cells}</tr>`);
    }
    return rows.join('');
  }

  // ---------------------------------------------------------------------
  // CSV export (REPORT-01) — dibuat via Blob, tanpa library.
  // ---------------------------------------------------------------------
  function toCsvCell(value) {
    const str = String(value ?? '');
    return /[",\n]/.test(str) ? '"' + str.replace(/"/g, '""') + '"' : str;
  }

  function downloadCsv(filename, headerRow, rows) {
    const lines = [headerRow, ...rows].map((row) => row.map(toCsvCell).join(','));
    const csv = '﻿' + lines.join('\r\n'); // BOM agar Excel membaca UTF-8 dengan benar
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  global.IOMS = global.IOMS || {};
  global.IOMS.Utils = {
    downloadCsv,
    formatCurrency,
    formatDate,
    formatDateTime,
    badgeClass,
    badgeHtml,
    escapeHtml,
    toast,
    confirmDialog,
    skeletonRows,
  };
})(window);
