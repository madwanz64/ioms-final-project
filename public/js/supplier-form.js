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

    const supplier = IOMS.DataStore.findById('suppliers', id);
    if (!supplier) {
      toast('Supplier tidak ditemukan.', 'error');
      window.location.href = 'suppliers.html';
      return;
    }
    editingId = supplier.id;
    document.getElementById('form-title').textContent = 'Edit Supplier';
    document.getElementById('editing-id').value = supplier.id;
    document.getElementById('name').value = supplier.name;
    document.getElementById('contact').value = supplier.contact;
    document.getElementById('address').value = supplier.address;
    document.getElementById('status').value = String(supplier.active);
  }

  function setError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('has-error');
    const msg = field.querySelector('.error-msg');
    if (msg) {
      msg.textContent = message;
      msg.hidden = false;
    }
  }

  function clearErrors() {
    document.querySelectorAll('.field').forEach((f) => {
      f.classList.remove('has-error');
      const msg = f.querySelector('.error-msg');
      if (msg) msg.hidden = true;
    });
    document.getElementById('form-error').hidden = true;
  }

  function bindSubmit() {
    document.getElementById('supplier-form').addEventListener('submit', (e) => {
      e.preventDefault();
      clearErrors();

      const name = document.getElementById('name').value.trim();
      const contact = document.getElementById('contact').value.trim();
      const address = document.getElementById('address').value.trim();
      const active = document.getElementById('status').value === 'true';

      let hasError = false;
      if (!name) {
        setError('field-name', 'Nama supplier wajib diisi.');
        hasError = true;
      }
      if (!contact) {
        setError('field-contact', 'Kontak wajib diisi.');
        hasError = true;
      }
      if (!address) {
        setError('field-address', 'Alamat wajib diisi.');
        hasError = true;
      }
      if (hasError) {
        document.getElementById('form-error').hidden = false;
        document.getElementById('form-error').textContent = 'Validasi gagal: periksa kembali field yang ditandai.';
        return;
      }

      const suppliers = IOMS.DataStore.get('suppliers');
      if (editingId) {
        const idx = suppliers.findIndex((s) => s.id === editingId);
        suppliers[idx] = { ...suppliers[idx], name, contact, address, active };
      } else {
        suppliers.push({ id: IOMS.DataStore.nextId('suppliers'), name, contact, address, active });
      }
      IOMS.DataStore.save('suppliers', suppliers);

      toast(editingId ? 'Perubahan supplier disimpan.' : 'Supplier baru berhasil ditambahkan.', 'success');
      window.location.href = 'suppliers.html';
    });
  }
})();
