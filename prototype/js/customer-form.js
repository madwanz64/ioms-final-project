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

    const customer = IOMS.DataStore.findById('customers', id);
    if (!customer) {
      toast('Customer tidak ditemukan.', 'error');
      window.location.href = 'customers.html';
      return;
    }
    editingId = customer.id;
    document.getElementById('form-title').textContent = 'Edit Customer';
    document.getElementById('editing-id').value = customer.id;
    document.getElementById('name').value = customer.name;
    document.getElementById('contact').value = customer.contact;
    document.getElementById('address').value = customer.address;
    document.getElementById('status').value = String(customer.active);
  }

  function setError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('has-error');
    const msg = field.querySelector('.error-msg');
    msg.textContent = message;
    msg.hidden = false;
  }

  function bindSubmit() {
    document.getElementById('customer-form').addEventListener('submit', (e) => {
      e.preventDefault();
      clearErrors();

      const name = document.getElementById('name').value.trim();
      const contact = document.getElementById('contact').value.trim();
      const address = document.getElementById('address').value.trim();
      const active = document.getElementById('status').value === 'true';

      let hasError = false;
      if (!name) {
        setError('field-name', 'Nama customer wajib diisi.');
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

      const customers = IOMS.DataStore.get('customers');
      if (editingId) {
        const idx = customers.findIndex((c) => c.id === editingId);
        customers[idx] = { ...customers[idx], name, contact, address, active };
      } else {
        customers.push({ id: IOMS.DataStore.nextId('customers'), name, contact, address, active });
      }
      IOMS.DataStore.save('customers', customers);

      toast(editingId ? 'Perubahan customer disimpan.' : 'Customer baru berhasil ditambahkan.', 'success');
      window.location.href = 'customers.html';
    });
  }

  function clearErrors() {
    document.querySelectorAll('.field').forEach((f) => {
      f.classList.remove('has-error');
      const msg = f.querySelector('.error-msg');
      if (msg) msg.hidden = true;
    });
    document.getElementById('form-error').hidden = true;
  }
})();
