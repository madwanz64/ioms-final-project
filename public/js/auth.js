/**
 * Auth — simulasi session di sessionStorage (bukan localStorage) supaya
 * konsepnya dekat dengan session server: hilang saat tab ditutup, terpisah
 * dari "database" (localStorage) tempat DataStore menyimpan data.
 *
 * CATATAN KEAMANAN (jujur, sesuai kewajiban tech-debt brief):
 * Login di sini murni client-side (cocokkan email/password di JSON/localStorage).
 * Ini TIDAK aman dan hanya untuk kebutuhan prototype tampilan sebelum backend
 * PHP ada. Saat AUTH-01 diimplementasikan di server, ini wajib diganti dengan
 * password_hash()/password_verify() + session PHP yang sesungguhnya.
 */
(function (global) {
  const SESSION_KEY = 'ioms_session';

  function login(email, password) {
    const users = IOMS.DataStore.get('users');
    const user = users.find((u) => u.email.toLowerCase() === String(email).toLowerCase());
    if (!user || user.password !== password || !user.active) {
      return { ok: false };
    }
    const session = { userId: user.id, name: user.name, role: user.role, email: user.email };
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
    return { ok: true, session };
  }

  function logout() {
    sessionStorage.removeItem(SESSION_KEY);
  }

  function getSession() {
    const raw = sessionStorage.getItem(SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  }

  /**
   * Jaga halaman: redirect ke login.html jika belum login,
   * atau ke error-403.html jika role tidak diizinkan.
   * @param {string[]|'any'} allowedRoles
   * @returns {object|null} session, atau null jika di-redirect
   */
  function requireRole(allowedRoles) {
    const session = getSession();
    if (!session) {
      window.location.href = 'login.html';
      return null;
    }
    if (allowedRoles !== 'any' && Array.isArray(allowedRoles) && !allowedRoles.includes(session.role)) {
      window.location.href = 'error-403.html';
      return null;
    }
    return session;
  }

  global.IOMS = global.IOMS || {};
  global.IOMS.Auth = { login, logout, getSession, requireRole };
})(window);
