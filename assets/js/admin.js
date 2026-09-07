document.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss flash alerts after a few seconds.
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4000);
  });
});
