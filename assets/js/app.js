// Uppercase callsign inputs
document.querySelectorAll('.text-uppercase').forEach(el => {
  el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
});

// Confirm dialogs
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});
