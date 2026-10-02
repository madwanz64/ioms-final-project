(function () {
  const form = document.getElementById('login-form');
  const errorBox = document.getElementById('login-error');
  const submitBtn = document.getElementById('login-submit');
  const resetLink = document.getElementById('reset-data');

  const ROLE_HOME = {
    Admin: 'dashboard-admin.html',
    Sales: 'dashboard-sales.html',
    'Warehouse Staff': 'dashboard-warehouse.html',
  };

  // Jika sudah login, langsung lempar ke dashboard sesuai role.
  document.addEventListener('DOMContentLoaded', async () => {
    await IOMS.DataStore.ensureSeeded();
    const session = IOMS.Auth.getSession();
    if (session) {
      window.location.href = ROLE_HOME[session.role] || 'login.html';
      return;
    }
    bindCopyableDemoAccounts();
  });

  // Klik email/password akun demo -> salin ke clipboard, tanpa perlu blok manual.
  function bindCopyableDemoAccounts() {
    document.querySelectorAll('.copyable').forEach((el) => {
      el.addEventListener('click', async () => {
        const text = el.textContent;
        try {
          await navigator.clipboard.writeText(text);
        } catch (err) {
          // Fallback untuk browser lama / konteks non-secure: textarea sementara + execCommand.
          const temp = document.createElement('textarea');
          temp.value = text;
          temp.style.position = 'fixed';
          temp.style.opacity = '0';
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          temp.remove();
        }
        IOMS.Utils.toast(`Disalin: ${text}`, 'success');
        el.classList.add('copied');
        setTimeout(() => el.classList.remove('copied'), 900);
      });
    });
  }

  function setFieldError(hasError) {
    document.getElementById('field-email').classList.toggle('has-error', hasError);
    document.getElementById('field-password').classList.toggle('has-error', hasError);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.hidden = true;
    setFieldError(false);

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Memeriksa...';

    await IOMS.DataStore.ensureSeeded();
    // delay kecil biar terasa seperti request sungguhan (dan animasi spinner terlihat)
    await new Promise((r) => setTimeout(r, 350));

    const result = IOMS.Auth.login(email, password);
    if (!result.ok) {
      errorBox.textContent = 'Email atau password salah, atau akun tidak aktif.';
      errorBox.hidden = false;
      setFieldError(true);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Masuk';
      document.getElementById('password').value = '';
      document.getElementById('password').focus();
      return;
    }

    submitBtn.textContent = 'Berhasil, mengalihkan...';
    window.location.href = ROLE_HOME[result.session.role] || 'login.html';
  });

  resetLink.addEventListener('click', async (e) => {
    e.preventDefault();
    const ok = await IOMS.Utils.confirmDialog({
      title: 'Reset Data Demo',
      message: 'Semua perubahan yang tersimpan di browser ini (produk, order, dll) akan dikembalikan ke data awal. Lanjutkan?',
      confirmLabel: 'Ya, reset',
      danger: true,
    });
    if (!ok) return;
    await IOMS.DataStore.resetAllData();
    IOMS.Auth.logout();
    IOMS.Utils.toast('Data demo berhasil direset.', 'success');
  });
})();
