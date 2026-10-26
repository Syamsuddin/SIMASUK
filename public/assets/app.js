// SIMASUK — interaksi kecil tanpa pustaka (docs/09): tombol memuat, kerangka tabel saat filter,
// dialog konfirmasi, toast, laci navigasi. Tanpa inline script (CSP docs/21).
'use strict';

(() => {
  const setLoading = (button) => {
    const label = button.dataset.loadingLabel;
    if (!label) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.querySelector('[data-spinner]')?.removeAttribute('hidden');
    button.querySelector('[data-icon]')?.setAttribute('hidden', '');
    const text = button.querySelector('[data-label]');
    if (text) {
      text.dataset.original = text.textContent;
      text.textContent = label;
    }
  };

  const resetLoading = () => {
    document.querySelectorAll('button[aria-busy="true"]').forEach((button) => {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.querySelector('[data-spinner]')?.setAttribute('hidden', '');
      button.querySelector('[data-icon]')?.removeAttribute('hidden');
      const text = button.querySelector('[data-label]');
      if (text && text.dataset.original) text.textContent = text.dataset.original;
    });
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    const button = event.submitter ?? form.querySelector('button[type="submit"]');
    if (button instanceof HTMLButtonElement) setLoading(button);

    const target = form.dataset.skeleton;
    if (target) {
      const body = document.getElementById(`${target}-body`);
      const skeleton = document.getElementById(`${target}-skeleton`);
      if (body && skeleton instanceof HTMLTemplateElement) {
        body.replaceChildren(skeleton.content.cloneNode(true));
      }
    }
  });

  // Kembali dari bfcache: pulihkan tombol yang sempat dinonaktifkan.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) resetLoading();
  });

  const drawer = document.querySelector('[data-drawer]');
  const overlay = document.querySelector('[data-drawer-overlay]');
  const opener = document.querySelector('[data-drawer-open]');
  const setDrawer = (open) => {
    if (!drawer || !overlay) return;
    drawer.classList.toggle('hidden', !open);
    drawer.classList.toggle('flex', open);
    overlay.hidden = !open;
    opener?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (!open) opener?.focus();
  };

  document.addEventListener('click', (event) => {
    const el = event.target instanceof Element ? event.target : null;
    if (!el) return;

    const dialogOpener = el.closest('[data-dialog-open]');
    if (dialogOpener) {
      const dialog = document.getElementById(dialogOpener.dataset.dialogOpen);
      if (dialog instanceof HTMLDialogElement) {
        dialog.returnFocusTo = dialogOpener;
        dialog.showModal();
      }
      return;
    }
    const dialogCloser = el.closest('[data-dialog-close]');
    if (dialogCloser) {
      dialogCloser.closest('dialog')?.close();
      return;
    }
    const back = el.closest('[data-history-back]');
    if (back && window.history.length > 1) {
      event.preventDefault();
      window.history.back();
      return;
    }
    if (el.closest('[data-toast-close]')) {
      el.closest('[data-toast]')?.remove();
      return;
    }
    if (el.closest('[data-drawer-open]')) setDrawer(true);
    else if (el.closest('[data-drawer-close]') || el.closest('[data-drawer-overlay]')) setDrawer(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && overlay && !overlay.hidden) setDrawer(false);
  });

  // Esc pada <dialog> ditangani peramban; fokus dikembalikan ke tombol pemicu (docs/26).
  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => {
      dialog.returnFocusTo?.focus();
    });
  });

  document.querySelectorAll('[data-toast]').forEach((toast) => {
    window.setTimeout(() => toast.remove(), 5000);
  });
})();
