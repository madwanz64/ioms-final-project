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
