(function () {
  const { toast } = IOMS.Utils;
  let editingId = null;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    loadForEdit();
    bindSubmit();
  });

  function loadForEdit() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) return;

    const warehouse = IOMS.DataStore.findById('warehouses', id);
    if (!warehouse) {
      toast('Gudang tidak ditemukan.', 'error');
      window.location.href = 'warehouses.html';
      return;
    }
    editingId = warehouse.id;
    document.getElementById('form-title').textContent = 'Edit Gudang';
    document.getElementById('editing-id').value = warehouse.id;
    document.getElementById('name').value = warehouse.name;
    document.getElementById('location').value = warehouse.location;
    document.getElementById('status').value = String(warehouse.active);
  }

  function setError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('has-error');
    const msg = field.querySelector('.error-msg');
    msg.textContent = message;
    msg.hidden = false;
  }

  function bindSubmit() {
    document.getElementById('warehouse-form').addEventListener('submit', (e) => {
      e.preventDefault();
      document.getElementById('field-name').classList.remove('has-error');
      document.getElementById('field-location').classList.remove('has-error');
      document.getElementById('form-error').hidden = true;

      const name = document.getElementById('name').value.trim();
      const location = document.getElementById('location').value.trim();
      const active = document.getElementById('status').value === 'true';

      let hasError = false;
      if (!name) {
        setError('field-name', 'Nama gudang wajib diisi.');
        hasError = true;
      }
      if (!location) {
        setError('field-location', 'Lokasi wajib diisi.');
        hasError = true;
      }
      if (hasError) {
        document.getElementById('form-error').hidden = false;
        document.getElementById('form-error').textContent = 'Validasi gagal: periksa kembali field yang ditandai.';
        return;
      }

      const warehouses = IOMS.DataStore.get('warehouses');
      if (editingId) {
        const idx = warehouses.findIndex((w) => w.id === editingId);
        warehouses[idx] = { ...warehouses[idx], name, location, active };
      } else {
        const id = IOMS.DataStore.nextId('warehouses');
        warehouses.push({ id, name, location, active });
        // Gudang baru butuh baris stok 0 untuk semua produk aktif yang sudah ada.
        const stock = IOMS.DataStore.get('productStock');
        IOMS.DataStore.get('products').forEach((p) => {
          stock.push({ sku: p.sku, warehouseId: id, quantity: 0, updatedAt: new Date().toISOString() });
        });
        IOMS.DataStore.save('productStock', stock);
      }
      IOMS.DataStore.save('warehouses', warehouses);

      toast(editingId ? 'Perubahan gudang disimpan.' : 'Gudang baru berhasil ditambahkan.', 'success');
      window.location.href = 'warehouses.html';
    });
  }
})();
