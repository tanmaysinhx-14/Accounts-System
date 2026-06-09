(function () {
  'use strict';

  window.openDialog = function (id) { document.getElementById(id).showModal(); };
  window.closeDialog = function (id) { document.getElementById(id).close(); };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('dialog.ci-dialog').forEach(function (dlg) {
      dlg.addEventListener('click', function (e) {
        const r = dlg.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right ||
          e.clientY < r.top || e.clientY > r.bottom) {
          dlg.close();
        }
      });
    });
  });

  window.initLiveSearch = function (opts) {
    var input = document.getElementById(opts.inputId);
    var noResults = document.getElementById(opts.noResultsId);
    var badge = document.getElementById(opts.badgeId);
    var singular = opts.singularLabel || 'Record';
    var plural = opts.pluralLabel || 'Records';

    if (!input) return;

    input.addEventListener('input', function () {
      var query = this.value.trim().toLowerCase();
      var rows = document.querySelectorAll('#' + opts.tableBodyId + ' tr');
      var visible = 0;

      rows.forEach(function (row) {
        var match = !query || opts.searchAttrs.some(function (attr) {
          return (row.dataset[attr] || '').includes(query);
        });
        row.classList.toggle('d-none', !match);
        if (match) visible++;
      });

      if (noResults) noResults.classList.toggle('d-none', visible > 0);
      if (badge) {
        badge.textContent = query
          ? visible + ' of ' + opts.total + ' ' + (opts.total === 1 ? singular : plural)
          : opts.total + ' ' + (opts.total === 1 ? singular : plural);
      }
    });
  };

})();