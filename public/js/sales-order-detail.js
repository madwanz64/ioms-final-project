(function () {
  const { formatCurrency, formatDate, badgeHtml, escapeHtml, toast, confirmDialog } = IOMS.Utils;
  let session;
  let order;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole('any');
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    load();
  });

  function load() {
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get('id'));
    order = IOMS.DataStore.findById('salesOrders', id);

    if (!order) {
      window.location.href = 'error-404.html';
      return;
    }
    render();
  }

  function render() {
    const customers = IOMS.DataStore.get('customers');
    const warehouses = IOMS.DataStore.get('warehouses');
    const users = IOMS.DataStore.get('users');
    const customer = customers.find((c) => c.id === order.customerId);
    const warehouse = warehouses.find((w) => w.id === order.warehouseId);
    const creator = users.find((u) => u.id === order.createdBy);

    document.getElementById('so-title').textContent = order.orderNo;
    document.getElementById('so-meta').textContent =
      `${customer?.name || '-'} · ${warehouse?.name || '-'} · Dibuat oleh ${creator?.name || '-'} pada ${formatDate(order.createdAt)}`;
    document.getElementById('status-badge-holder').innerHTML = badgeHtml(order.status);

    renderItems(customer);
    renderApprovalPanel(creator);
    renderIssuePanel(warehouse);
    renderCancelPanel();
  }

  function renderItems() {
    const products = IOMS.DataStore.get('products');
    let total = 0;
    document.getElementById('item-rows').innerHTML = order.items
      .map((item) => {
        const product = products.find((p) => p.sku === item.sku);
        const subtotal = item.qty * item.price;
        total += subtotal;
        return `
        <tr>
          <td>${escapeHtml(product ? product.name : item.sku)}</td>
          <td class="num">${item.qty}</td>
          <td class="num">${formatCurrency(item.price)}</td>
          <td class="num">${formatCurrency(subtotal)}</td>
        </tr>`;
      })
      .join('');
    document.getElementById('total-line').textContent = `Total: ${formatCurrency(total)}`;
  }

  function renderApprovalPanel(creator) {
    const panel = document.getElementById('approval-panel');
    const canSeePanel = session.role === 'Admin' && order.status === 'PendingApproval';
    panel.hidden = !canSeePanel;
    if (!canSeePanel) return;

    const isOwnOrder = order.createdBy === session.userId;
    const note = document.getElementById('approval-note');
    const approveBtn = document.getElementById('btn-approve');
    const rejectBtn = document.getElementById('btn-reject');

    if (isOwnOrder) {
      note.innerHTML = `<div class="alert error">Anda adalah pembuat order ini. Sesuai segregation of duties (§1.2, SO-01), Anda tidak dapat menyetujui order milik sendiri.</div>`;
      approveBtn.disabled = true;
      rejectBtn.disabled = true;
    } else {
      note.innerHTML = `<div class="alert info">Order ini dibuat oleh <strong>${escapeHtml(
        creator?.name || '-'
      )}</strong>. Anda login sebagai <strong>${escapeHtml(session.name)}</strong> (Admin) — diizinkan approve/reject.</div>`;
      approveBtn.disabled = false;
      rejectBtn.disabled = false;
    }

    approveBtn.onclick = async () => {
      const ok = await confirmDialog({ title: 'Setujui Sales Order', message: `Setujui ${order.orderNo}?`, confirmLabel: 'Setujui' });
      if (!ok) return;
      updateOrder({ status: 'Approved', approvedBy: session.userId });
      toast('Sales Order disetujui.', 'success');
      render();
    };
    rejectBtn.onclick = async () => {
      const ok = await confirmDialog({
        title: 'Tolak Sales Order',
        message: `Tolak ${order.orderNo}? Status akan menjadi Cancelled.`,
        confirmLabel: 'Tolak',
        danger: true,
      });
      if (!ok) return;
      updateOrder({ status: 'Cancelled' });
      toast('Sales Order ditolak.', 'success');
      render();
    };
  }

  function renderIssuePanel(warehouse) {
    const panel = document.getElementById('issue-panel');
    const canSeePanel = session.role === 'Warehouse Staff' && order.status === 'Approved';
    panel.hidden = !canSeePanel;
    if (!canSeePanel) return;

    const products = IOMS.DataStore.get('products');
    const stockRows = IOMS.DataStore.get('productStock');
    const tbody = document.querySelector('#issue-table tbody');

    function currentStock(sku) {
      const row = stockRows.find((s) => s.sku === sku && s.warehouseId === order.warehouseId);
      return row ? row.quantity : 0;
    }

    let insufficient = false;
    tbody.innerHTML = order.items
      .map((item) => {
        const product = products.find((p) => p.sku === item.sku);
        const available = currentStock(item.sku);
        const short = item.qty > available;
        if (short) insufficient = true;
        return `
        <tr class="${short ? 'has-error' : ''}">
          <td>${escapeHtml(product ? product.name : item.sku)}</td>
          <td class="num">${item.qty}</td>
          <td class="num">${available}</td>
          <td>${short ? '<span class="badge cancelled">Tidak Cukup</span>' : '<span class="badge active">Cukup</span>'}</td>
        </tr>`;
      })
      .join('');

    const alertBox = document.getElementById('issue-alert');
    const issueBtn = document.getElementById('btn-issue');
    if (insufficient) {
      alertBox.innerHTML = `<div class="alert error">Stok tidak mencukupi untuk memenuhi qty pesanan pada satu atau lebih item. Goods issue ditolak sampai stok mencukupi (SO-01).</div>`;
      issueBtn.disabled = true;
    } else {
      alertBox.innerHTML = '';
      issueBtn.disabled = false;
    }

    document.getElementById('btn-issue').onclick = async () => {
      const ok = await confirmDialog({
        title: 'Proses Goods Issue',
        message: `Kurangi stok dan tandai ${order.orderNo} sebagai Fulfilled?`,
        confirmLabel: 'Proses',
      });
      if (!ok) return;
      processGoodsIssue();
    };
  }

  function processGoodsIssue() {
    // Pola "baca ulang lalu tulis" tepat sebelum commit — inilah bagian yang di
    // backend PHP nanti WAJIB dibungkus transaksi DB (beginTransaction/commit)
    // agar benar-benar aman dari race condition antar request (ARCH-02).
    const stockRows = IOMS.DataStore.get('productStock');
    const ledger = IOMS.DataStore.get('stockLedger');
    let nextLedgerId = ledger.reduce((max, l) => Math.max(max, l.id), 0) + 1;

    for (const item of order.items) {
      const stockRow = stockRows.find((s) => s.sku === item.sku && s.warehouseId === order.warehouseId);
      if (!stockRow || stockRow.quantity < item.qty) {
        toast(`Stok ${item.sku} berubah dan tidak lagi mencukupi. Proses dibatalkan, silakan refresh.`, 'error');
        return;
      }
    }

    order.items.forEach((item) => {
      const stockRow = stockRows.find((s) => s.sku === item.sku && s.warehouseId === order.warehouseId);
      stockRow.quantity -= item.qty;
      stockRow.updatedAt = new Date().toISOString();
      ledger.push({
        id: nextLedgerId++,
        sku: item.sku,
        warehouseId: order.warehouseId,
        type: 'Issue',
        quantity: -item.qty,
        refType: 'SO',
        refId: order.orderNo,
        byUserId: session.userId,
        timestamp: new Date().toISOString(),
      });
    });

    IOMS.DataStore.save('productStock', stockRows);
    IOMS.DataStore.save('stockLedger', ledger);
    updateOrder({ status: 'Fulfilled' });
    toast('Goods issue berhasil diproses. Order Fulfilled.', 'success');
    render();
  }

  function renderCancelPanel() {
    const panel = document.getElementById('cancel-panel');
    const cancellable = ['Draft', 'PendingApproval', 'Approved'].includes(order.status);
    const allowed = session.role === 'Admin' || (session.role === 'Sales' && order.createdBy === session.userId);
    panel.hidden = !(cancellable && allowed);
    if (panel.hidden) return;

    document.getElementById('btn-cancel').onclick = async () => {
      const ok = await confirmDialog({
        title: 'Batalkan Sales Order',
        message: `Batalkan ${order.orderNo}? Tindakan ini tidak dapat diurungkan.`,
        confirmLabel: 'Batalkan',
        danger: true,
      });
      if (!ok) return;
      updateOrder({ status: 'Cancelled' });
      toast('Sales Order dibatalkan.', 'success');
      render();
    };
  }

  function updateOrder(patch) {
    const orders = IOMS.DataStore.get('salesOrders');
    const idx = orders.findIndex((o) => o.id === order.id);
    orders[idx] = { ...orders[idx], ...patch };
    IOMS.DataStore.save('salesOrders', orders);
    order = orders[idx];
  }
})();
