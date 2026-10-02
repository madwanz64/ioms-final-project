(function () {
  const { formatCurrency, toast, escapeHtml } = IOMS.Utils;
  let session;
  let editingOrder = null;
  let rowSeq = 0;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Sales']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    populateSelects();
    loadForEdit();
    if (document.querySelectorAll('#items-body tr').length === 0) addItemRow();
    bindEvents();
    recalcAll();
  });

  function populateSelects() {
    const customerSelect = document.getElementById('customer');
    IOMS.DataStore.get('customers')
      .filter((c) => c.active)
      .forEach((c) => customerSelect.appendChild(new Option(c.name, c.id)));

    const warehouseSelect = document.getElementById('warehouse');
    IOMS.DataStore.get('warehouses')
      .filter((w) => w.active)
      .forEach((w) => warehouseSelect.appendChild(new Option(w.name, w.id)));
  }

  function activeProductOptions(selectedSku) {
    const products = IOMS.DataStore.get('products').filter((p) => p.active);
    return (
      `<option value="">Pilih produk...</option>` +
      products.map((p) => `<option value="${p.sku}" ${p.sku === selectedSku ? 'selected' : ''}>${escapeHtml(p.name)}</option>`).join('')
    );
  }

  function stockFor(sku, warehouseId) {
    if (!sku || !warehouseId) return 0;
    const row = IOMS.DataStore.get('productStock').find((s) => s.sku === sku && String(s.warehouseId) === String(warehouseId));
    return row ? row.quantity : 0;
  }

  function addItemRow(item) {
    rowSeq += 1;
    const tr = document.createElement('tr');
    tr.dataset.rowId = rowSeq;
    tr.innerHTML = `
      <td><select class="f-product">${activeProductOptions(item?.sku)}</select></td>
      <td class="num f-stock">-</td>
      <td><input type="number" class="f-qty" min="1" value="${item?.qty || 1}"></td>
      <td class="num f-price">-</td>
      <td class="num f-subtotal">Rp 0</td>
      <td><button type="button" class="btn small danger f-remove">&times;</button></td>`;
    document.getElementById('items-body').appendChild(tr);

    tr.querySelector('.f-remove').addEventListener('click', () => {
      tr.remove();
      recalcAll();
    });
    tr.querySelector('.f-product').addEventListener('change', () => recalcRow(tr));
    tr.querySelector('.f-qty').addEventListener('input', () => recalcRow(tr));

    recalcRow(tr);
  }

  function recalcRow(tr) {
    const warehouseId = document.getElementById('warehouse').value;
    const sku = tr.querySelector('.f-product').value;
    const qty = Number(tr.querySelector('.f-qty').value) || 0;
    const product = sku ? IOMS.DataStore.findById('products', sku, 'sku') : null;
    const stock = stockFor(sku, warehouseId);

    tr.querySelector('.f-stock').textContent = sku ? stock : '-';
    tr.querySelector('.f-price').textContent = product ? formatCurrency(product.sellPrice) : '-';
    const subtotal = product ? product.sellPrice * qty : 0;
    tr.querySelector('.f-subtotal').textContent = formatCurrency(subtotal);

    tr.classList.toggle('has-error', Boolean(sku) && qty > stock);
    recalcTotal();
  }

  function recalcAll() {
    document.querySelectorAll('#items-body tr').forEach(recalcRow);
    recalcTotal();
  }

  function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach((tr) => {
      const sku = tr.querySelector('.f-product').value;
      const qty = Number(tr.querySelector('.f-qty').value) || 0;
      const product = sku ? IOMS.DataStore.findById('products', sku, 'sku') : null;
      if (product) total += product.sellPrice * qty;
    });
    document.getElementById('total-display').textContent = formatCurrency(total);
  }

  function bindEvents() {
    document.getElementById('add-item').addEventListener('click', () => addItemRow());
    document.getElementById('warehouse').addEventListener('change', recalcAll);
    document.getElementById('so-form').addEventListener('submit', handleSubmit);
  }

  function loadForEdit() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) return;

    const order = IOMS.DataStore.findById('salesOrders', id);
    if (!order) {
      toast('Sales Order tidak ditemukan.', 'error');
      window.location.href = 'sales-orders.html';
      return;
    }
    if (order.createdBy !== session.userId || order.status !== 'Draft') {
      window.location.href = `sales-order-detail.html?id=${order.id}`;
      return;
    }
    editingOrder = order;
    document.getElementById('form-title').textContent = `Lanjutkan Draft — ${order.orderNo}`;
    document.getElementById('editing-id').value = order.id;
    document.getElementById('customer').value = order.customerId;
    document.getElementById('warehouse').value = order.warehouseId;
    order.items.forEach((item) => addItemRow(item));
  }

  function validate() {
    clearErrors();
    let hasError = false;

    if (!document.getElementById('customer').value) {
      document.getElementById('field-customer').classList.add('has-error');
      hasError = true;
    }
    if (!document.getElementById('warehouse').value) {
      document.getElementById('field-warehouse').classList.add('has-error');
      hasError = true;
    }

    const rows = [...document.querySelectorAll('#items-body tr')];
    const items = [];
    let stockProblem = false;
    rows.forEach((tr) => {
      const sku = tr.querySelector('.f-product').value;
      const qty = Number(tr.querySelector('.f-qty').value) || 0;
      if (!sku || qty <= 0) return;
      const stock = stockFor(sku, document.getElementById('warehouse').value);
      if (qty > stock) stockProblem = true;
      items.push({ sku, qty });
    });

    if (items.length === 0) {
      hasError = true;
    }
    if (stockProblem) hasError = true;

    return { hasError, items, stockProblem, itemsEmpty: items.length === 0 };
  }

  function clearErrors() {
    document.getElementById('field-customer').classList.remove('has-error');
    document.getElementById('field-warehouse').classList.remove('has-error');
    document.getElementById('form-error').hidden = true;
  }

  function handleSubmit(e) {
    e.preventDefault();
    const action = e.submitter?.dataset.action || 'draft';
    const { hasError, items, stockProblem, itemsEmpty } = validate();

    if (hasError) {
      const box = document.getElementById('form-error');
      box.hidden = false;
      box.textContent = stockProblem
        ? 'Qty pada salah satu item melebihi stok tersedia di gudang asal. Perbaiki qty sebelum menyimpan.'
        : itemsEmpty
        ? 'Tambahkan minimal satu item dengan produk dan qty yang valid.'
        : 'Lengkapi Customer dan Gudang Asal terlebih dahulu.';
      return;
    }

    const customerId = Number(document.getElementById('customer').value);
    const warehouseId = Number(document.getElementById('warehouse').value);
    const products = IOMS.DataStore.get('products');
    const fullItems = items.map((i) => {
      const p = products.find((prod) => prod.sku === i.sku);
      return { sku: i.sku, qty: i.qty, price: p.sellPrice };
    });
    const status = action === 'submit' ? 'PendingApproval' : 'Draft';

    const orders = IOMS.DataStore.get('salesOrders');
    if (editingOrder) {
      const idx = orders.findIndex((o) => o.id === editingOrder.id);
      orders[idx] = { ...orders[idx], customerId, warehouseId, items: fullItems, status };
    } else {
      const id = IOMS.DataStore.nextId('salesOrders');
      orders.push({
        id,
        orderNo: `SO-2026-${String(id).padStart(4, '0')}`,
        customerId,
        warehouseId,
        createdBy: session.userId,
        approvedBy: null,
        status,
        createdAt: new Date().toISOString().slice(0, 10),
        items: fullItems,
      });
    }
    IOMS.DataStore.save('salesOrders', orders);

    toast(status === 'PendingApproval' ? 'Sales Order diajukan untuk approval.' : 'Sales Order disimpan sebagai Draft.', 'success');
    window.location.href = 'sales-orders.html';
  }
})();
