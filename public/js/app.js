/**
 * Interaksi kecil sisi client (Vanilla JS). Semua aturan bisnis, otorisasi,
 * dan validasi tetap di server — script ini hanya kenyamanan UI, halaman
 * tetap berfungsi bila JavaScript dimatikan.
 */
(function () {
  'use strict';

  function toast(message, type) {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('role', 'status');
      document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = ('toast ' + (type || '')).trim();
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(function () {
      el.classList.add('leaving');
      setTimeout(function () { el.remove(); }, 250);
    }, 3200);
  }

  // Flash sukses dari server: tampilkan juga sebagai toast, lalu sembunyikan alert-nya.
  document.querySelectorAll('[data-flash].success').forEach(function (alert) {
    toast(alert.textContent, 'success');
    alert.hidden = true;
  });

  // Klik untuk menyalin akun demo di halaman login.
  document.querySelectorAll('.copyable').forEach(function (el) {
    el.addEventListener('click', function () {
      if (!navigator.clipboard) return;
      navigator.clipboard.writeText(el.textContent.trim()).then(function () {
        toast('Disalin: ' + el.textContent.trim(), 'success');
      });
    });
  });

  // Filter daftar produk: ganti pilihan langsung submit (tombol "Terapkan" tetap ada tanpa JS).
  document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
    select.addEventListener('change', function () {
      // Filter berubah -> mulai lagi dari halaman 1 (form GET tidak membawa ?page).
      if (select.form) select.form.submit();
    });
  });

  // Konfirmasi sebelum aksi yang tidak bisa dibatalkan (mis. batalkan PO).
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });

  // Baris item order: tambah/hapus baris, isi harga default dari produk terpilih.
  document.querySelectorAll('table[data-line-items]').forEach(function (table) {
    const body = table.tBodies[0];
    const maxLines = Number(table.dataset.maxLines) || 50;
    const addButton = table.closest('fieldset').querySelector('[data-add-line]');

    function reindex() {
      Array.prototype.forEach.call(body.rows, function (row, index) {
        row.querySelectorAll('[name]').forEach(function (field) {
          field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
          field.id = field.id.replace(/item-\d+-/, 'item-' + index + '-');
        });
        row.querySelectorAll('label[for]').forEach(function (label) {
          label.htmlFor = label.htmlFor.replace(/item-\d+-/, 'item-' + index + '-');
        });
      });
      if (addButton) addButton.disabled = body.rows.length >= maxLines;
    }

    body.addEventListener('change', function (event) {
      if (!event.target.matches('[data-line-product]')) return;
      const option = event.target.selectedOptions[0];
      const row = event.target.closest('tr');
      const price = row.querySelector('[data-line-price]');
      if (option && option.dataset.price && price && price.value === '') price.value = option.dataset.price;
      // SO: harga jual katalog hanya ditampilkan (server tetap memakai harga katalog).
      const priceText = row.querySelector('[data-line-price-text]');
      if (priceText) {
        priceText.textContent = option && option.dataset.price ? 'Rp ' + Number(option.dataset.price).toLocaleString('id-ID') : '';
      }
    });

    body.addEventListener('click', function (event) {
      const remove = event.target.closest('[data-remove-line]');
      if (!remove) return;
      if (body.rows.length === 1) {
        remove.closest('tr').querySelectorAll('input, select').forEach(function (f) { f.value = ''; });
        return;
      }
      remove.closest('tr').remove();
      reindex();
    });

    if (addButton) {
      addButton.addEventListener('click', function () {
        const row = body.rows[body.rows.length - 1].cloneNode(true);
        row.querySelectorAll('input, select').forEach(function (field) {
          field.value = '';
          field.removeAttribute('aria-invalid');
        });
        row.querySelectorAll('.error-msg').forEach(function (msg) { msg.remove(); });
        row.querySelectorAll('[data-line-price-text], [data-line-stock]').forEach(function (text) {
          text.textContent = '';
          text.classList.remove('insufficient');
        });
        body.appendChild(row);
        reindex();
        row.querySelector('select').focus();
      });
    }
    reindex();
  });

  // ---------------------------------------------------------------------
  // API-01: ketersediaan stok lewat Fetch API (JSON). Server tetap memvalidasi ulang.
  // ---------------------------------------------------------------------
  function fetchAvailability(sku) {
    return fetch('/api/products/' + encodeURIComponent(sku) + '/availability', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) {
          const error = new Error(body.message || 'Gagal memuat stok.');
          error.status = response.status;
          throw error;
        }
        return body;
      });
    });
  }

  // Form Sales Order: tampilkan stok tersedia di gudang asal untuk produk tiap baris.
  document.querySelectorAll('form[data-stock-check]').forEach(function (form) {
    const warehouseSelect = form.querySelector('[name="warehouse_id"]');

    function updateLine(row) {
      const hint = row.querySelector('[data-line-stock]');
      const sku = row.querySelector('[data-line-product]').value;
      const qty = Number(row.querySelector('[data-line-qty]').value || 0);
      if (!hint) return;
      if (!sku || !warehouseSelect.value) {
        hint.textContent = '';
        return;
      }
      hint.textContent = 'Memuat stok…';
      fetchAvailability(sku).then(function (data) {
        const warehouse = data.warehouses.find(function (w) { return String(w.id) === warehouseSelect.value; });
        const available = warehouse ? warehouse.quantity : 0;
        hint.textContent = 'Stok tersedia: ' + available + ' ' + data.unit;
        hint.classList.toggle('insufficient', qty > available);
      }).catch(function (error) {
        hint.textContent = error.status === 401 ? 'Sesi berakhir, silakan login ulang.' : error.message;
      });
    }

    function updateAll() {
      form.querySelectorAll('tr[data-line]').forEach(updateLine);
    }

    form.addEventListener('change', function (event) {
      if (event.target === warehouseSelect) {
        updateAll();
      } else if (event.target.matches('[data-line-product], [data-line-qty]')) {
        updateLine(event.target.closest('tr'));
      }
    });
    updateAll();
  });

  // Detail produk: muat ulang stok per gudang tanpa reload halaman.
  document.querySelectorAll('[data-refresh-stock]').forEach(function (button) {
    const status = document.querySelector('[data-refresh-status]');
    button.addEventListener('click', function () {
      button.disabled = true;
      fetchAvailability(button.dataset.refreshStock).then(function (data) {
        data.warehouses.forEach(function (w) {
          const cell = document.querySelector('[data-stock-warehouse="' + w.id + '"]');
          if (cell) cell.textContent = w.quantity;
        });
        const total = document.querySelector('[data-stock-total]');
        if (total) total.textContent = data.totalStock;
        if (status) status.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID') + (data.lowStock ? ' — di bawah reorder point.' : '.');
      }).catch(function (error) {
        toast(error.status === 401 ? 'Sesi berakhir, silakan login ulang.' : error.message, 'error');
      }).finally(function () {
        button.disabled = false;
      });
    });
  });

  // Upload gambar: cek ukuran & tipe lebih awal + preview. Server tetap memeriksa ulang dari isi file.
  document.querySelectorAll('input[type="file"][data-max-bytes]').forEach(function (input) {
    const preview = document.querySelector('[data-image-preview]');
    const maxBytes = Number(input.dataset.maxBytes);
    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file) return;
      if (['image/jpeg', 'image/png'].indexOf(file.type) === -1) {
        toast('Format gambar harus JPG atau PNG.', 'error');
        input.value = '';
        return;
      }
      if (file.size > maxBytes) {
        toast('Ukuran gambar melebihi batas maksimal.', 'error');
        input.value = '';
        return;
      }
      if (preview) {
        const img = document.createElement('img');
        img.alt = 'Preview gambar produk';
        img.src = URL.createObjectURL(file);
        preview.replaceChildren(img);
      }
    });
  });
})();
