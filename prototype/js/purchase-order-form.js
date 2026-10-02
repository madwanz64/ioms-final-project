(function () {
  const { formatCurrency, toast, escapeHtml } = IOMS.Utils;
  let session;
  let rowSeq = 0;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Admin', 'Warehouse Staff']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    populateSelects();
    document.getElementById('order-date').value = new Date().toISOString().slice(0, 10);
    addItemRow();
    bindEvents();
  });

  function populateSelects() {
    const supplierSelect = document.getElementById('supplier');
    IOMS.DataStore.get('suppliers')
      .filter((s) => s.active)
      .forEach((s) => supplierSelect.appendChild(new Option(s.name, s.id)));

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

  function addItemRow() {
    rowSeq += 1;
    const tr = document.createElement('tr');
    tr.dataset.rowId = rowSeq;
    tr.innerHTML = `
      <td><select class="f-product">${activeProductOptions()}</select></td>
      <td><input type="number" class="f-qty" min="1" value="1"></td>
      <td><input type="number" class="f-price" min="0" value="0"></td>
      <td class="num f-subtotal">Rp 0</td>
      <td><button type="button" class="btn small danger f-remove">&times;</button></td>`;
    document.getElementById('items-body').appendChild(tr);

    tr.querySelector('.f-remove').addEventListener('click', () => {
      tr.remove();
      recalcTotal();
    });
    tr.querySelector('.f-product').addEventListener('change', () => {
      const product = IOMS.DataStore.findById('products', tr.querySelector('.f-product').value, 'sku');
      if (product) tr.querySelector('.f-price').value = product.buyPrice;
      recalcRow(tr);
    });
    tr.querySelector('.f-qty').addEventListener('input', () => recalcRow(tr));
    tr.querySelector('.f-price').addEventListener('input', () => recalcRow(tr));
  }

  function recalcRow(tr) {
    const qty = Number(tr.querySelector('.f-qty').value) || 0;
    const price = Number(tr.querySelector('.f-price').value) || 0;
    tr.querySelector('.f-subtotal').textContent = formatCurrency(qty * price);
    recalcTotal();
  }

  function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach((tr) => {
      const qty = Number(tr.querySelector('.f-qty').value) || 0;
      const price = Number(tr.querySelector('.f-price').value) || 0;
      total += qty * price;
    });
    document.getElementById('total-display').textContent = formatCurrency(total);
  }

  function bindEvents() {
    document.getElementById('add-item').addEventListener('click', addItemRow);
    document.getElementById('po-form').addEventListener('submit', handleSubmit);
  }

  function handleSubmit(e) {
    e.preventDefault();
    const action = e.submitter?.dataset.action || 'draft';

    document.getElementById('field-supplier').classList.remove('has-error');
    document.getElementById('field-warehouse').classList.remove('has-error');
    document.getElementById('form-error').hidden = true;

    let hasError = false;
    if (!document.getElementById('supplier').value) {
      document.getElementById('field-supplier').classList.add('has-error');
      hasError = true;
    }
    if (!document.getElementById('warehouse').value) {
      document.getElementById('field-warehouse').classList.add('has-error');
      hasError = true;
    }

    const items = [];
    document.querySelectorAll('#items-body tr').forEach((tr) => {
      const sku = tr.querySelector('.f-product').value;
      const qty = Number(tr.querySelector('.f-qty').value) || 0;
      const buyPrice = Number(tr.querySelector('.f-price').value) || 0;
      if (sku && qty > 0) items.push({ sku, qty, receivedQty: 0, buyPrice });
    });

    if (items.length === 0) hasError = true;

    if (hasError) {
      const box = document.getElementById('form-error');
      box.hidden = false;
      box.textContent = items.length === 0
        ? 'Tambahkan minimal satu item dengan produk dan qty yang valid.'
        : 'Lengkapi Supplier dan Gudang Tujuan terlebih dahulu.';
      return;
    }

    const orders = IOMS.DataStore.get('purchaseOrders');
    const id = IOMS.DataStore.nextId('purchaseOrders');
    orders.push({
      id,
      orderNo: `PO-2026-${String(id).padStart(4, '0')}`,
      supplierId: Number(document.getElementById('supplier').value),
      warehouseId: Number(document.getElementById('warehouse').value),
      status: action === 'order' ? 'Ordered' : 'Draft',
      createdAt: document.getElementById('order-date').value,
      items,
    });
    IOMS.DataStore.save('purchaseOrders', orders);

    toast(action === 'order' ? 'Purchase Order dikirim ke supplier.' : 'Purchase Order disimpan sebagai Draft.', 'success');
    window.location.href = 'purchase-orders.html';
  }
})();
