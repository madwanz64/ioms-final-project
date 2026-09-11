(function () {
  const { toast } = IOMS.Utils;
  let editingSku = null;
  let uploadedImageDataUrl = null;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    populateCategories();
    bindImageUpload();
    loadForEdit();
    bindSubmit();
  });

  function populateCategories() {
    const select = document.getElementById('category');
    IOMS.DataStore.get('categories').forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      select.appendChild(opt);
    });
  }

  function bindImageUpload() {
    const input = document.getElementById('image-input');
    const preview = document.getElementById('img-preview');
    const placeholderText = document.getElementById('img-placeholder-text');

    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        toast('Ukuran gambar maksimal 2MB.', 'error');
        input.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        uploadedImageDataUrl = reader.result;
        preview.src = uploadedImageDataUrl;
        preview.hidden = false;
        placeholderText.hidden = true;
      };
      reader.readAsDataURL(file);
    });
  }

  function loadForEdit() {
    const params = new URLSearchParams(window.location.search);
    const sku = params.get('sku');
    if (!sku) return;

    const product = IOMS.DataStore.findById('products', sku, 'sku');
    if (!product) {
      toast('Produk tidak ditemukan.', 'error');
      window.location.href = 'products.html';
      return;
    }
    editingSku = sku;
    document.getElementById('form-title').textContent = 'Edit Produk';
    document.getElementById('original-sku').value = sku;
    document.getElementById('sku').value = product.sku;
    document.getElementById('name').value = product.name;
    document.getElementById('category').value = product.categoryId;
    document.getElementById('unit').value = product.unit;
    document.getElementById('buy-price').value = product.buyPrice;
    document.getElementById('sell-price').value = product.sellPrice;
    document.getElementById('reorder').value = product.reorderPoint;
    document.getElementById('status').value = String(product.active);
    if (product.imageUrl) {
      uploadedImageDataUrl = product.imageUrl;
      const preview = document.getElementById('img-preview');
      preview.src = product.imageUrl;
      preview.hidden = false;
      document.getElementById('img-placeholder-text').hidden = true;
    }
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
    document.getElementById('product-form').addEventListener('submit', (e) => {
      e.preventDefault();
      clearErrors();

      const sku = document.getElementById('sku').value.trim().toUpperCase();
      const name = document.getElementById('name').value.trim();
      const categoryId = Number(document.getElementById('category').value);
      const unit = document.getElementById('unit').value.trim();
      const buyPrice = Number(document.getElementById('buy-price').value);
      const sellPrice = Number(document.getElementById('sell-price').value);
      const reorderPoint = Number(document.getElementById('reorder').value);
      const active = document.getElementById('status').value === 'true';

      let hasError = false;
      const products = IOMS.DataStore.get('products');

      if (!sku) {
        setError('field-sku', 'SKU wajib diisi.');
        hasError = true;
      } else {
        const duplicate = products.find((p) => p.sku === sku && p.sku !== editingSku);
        if (duplicate) {
          setError('field-sku', 'SKU sudah dipakai produk lain.');
          hasError = true;
        }
      }
      if (!name) {
        setError('field-name', 'Nama produk wajib diisi.');
        hasError = true;
      }
      if (!unit) {
        setError('field-unit', 'Unit wajib diisi.');
        hasError = true;
      }
      if (!Number.isFinite(buyPrice) || buyPrice < 0) {
        setError('field-buy-price', 'Harga beli harus angka >= 0.');
        hasError = true;
      }
      if (!Number.isFinite(sellPrice) || sellPrice < 0) {
        setError('field-sell-price', 'Harga jual harus angka >= 0.');
        hasError = true;
      }
      if (!Number.isInteger(reorderPoint) || reorderPoint < 0) {
        setError('field-reorder', 'Reorder point harus bilangan bulat >= 0.');
        hasError = true;
      }

      if (hasError) {
        document.getElementById('form-error').hidden = false;
        document.getElementById('form-error').textContent =
          'Validasi gagal: periksa kembali field yang ditandai. Input yang sudah diisi tetap dipertahankan.';
        document.querySelector('.field.has-error input, .field.has-error select')?.focus();
        return;
      }

      const payload = {
        sku,
        name,
        categoryId,
        unit,
        buyPrice,
        sellPrice,
        reorderPoint,
        imageUrl: uploadedImageDataUrl,
        active,
      };

      if (editingSku) {
        const idx = products.findIndex((p) => p.sku === editingSku);
        products[idx] = payload;
        // Jika SKU diganti, ikutkan perubahan pada productStock agar relasi tetap valid.
        if (editingSku !== sku) {
          const stock = IOMS.DataStore.get('productStock');
          stock.forEach((s) => {
            if (s.sku === editingSku) s.sku = sku;
          });
          IOMS.DataStore.save('productStock', stock);
        }
      } else {
        products.push(payload);
        // Produk baru mulai dengan stok 0 di semua gudang (goods receipt via PO menyusul di slice berikutnya).
        const stock = IOMS.DataStore.get('productStock');
        IOMS.DataStore.get('warehouses').forEach((w) => {
          stock.push({ sku, warehouseId: w.id, quantity: 0, updatedAt: new Date().toISOString() });
        });
        IOMS.DataStore.save('productStock', stock);
      }
      IOMS.DataStore.save('products', products);

      toast(editingSku ? 'Perubahan produk disimpan.' : 'Produk baru berhasil ditambahkan.', 'success');
      window.location.href = 'products.html';
    });
  }
})();
