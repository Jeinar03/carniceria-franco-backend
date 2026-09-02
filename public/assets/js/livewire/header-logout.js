(function () {
  function bindHeaderLogout() {
    var btn = document.getElementById('btnLogoutTpp');
    if (!btn) return;

    var form = btn.closest('form');
    if (!form) return;

    var text = btn.querySelector('.btn-logout-text');
    var loader = btn.querySelector('.btn-logout-loader');

    form.addEventListener('submit', function () {
      btn.disabled = true;
      if (text) text.classList.add('d-none');
      if (loader) loader.classList.remove('d-none');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindHeaderLogout);
  } else {
    bindHeaderLogout();
  }
})();
