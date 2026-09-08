(function () {
  function initPicker(root) {
    var form = root.querySelector('[data-generate-form]');
    var orderList = root.querySelector('[data-order-list]');
    var orderFields = root.querySelector('[data-order-fields]');
    var countEl = root.querySelector('[data-selected-count]');
    var btn = root.querySelector('[data-generate-btn]');
    var statusEl = root.querySelector('[data-generate-status]');
    var search = root.querySelector('[data-docs-search]');
    var selectedLabel = root.getAttribute('data-selected-label') || '%d documents selected';
    var generatingLabel = root.getAttribute('data-generating') || 'Generating…';
    var filter = 'all';
    var dragKey = null;

    function titleForKey(key) {
      var row = root.querySelector('.app-docs-row[data-key="' + CSS.escape(key) + '"]');
      if (!row) return key;
      var t = row.querySelector('.app-docs-title');
      return t ? t.textContent.trim() : key;
    }

    function selectedKeys() {
      var keys = [];
      orderList.querySelectorAll('[data-order-key]').forEach(function (li) {
        keys.push(li.getAttribute('data-order-key'));
      });
      return keys;
    }

    function ensureInOrder(key) {
      if (orderList.querySelector('[data-order-key="' + CSS.escape(key) + '"]')) return;
      var li = document.createElement('li');
      li.className = 'app-docs-order-item';
      li.setAttribute('data-order-key', key);
      li.setAttribute('draggable', 'true');
      li.innerHTML =
        '<span class="app-docs-drag" aria-hidden="true">☰</span>' +
        '<span class="app-docs-order-label"></span>';
      li.querySelector('.app-docs-order-label').textContent = titleForKey(key);
      orderList.appendChild(li);
    }

    function removeFromOrder(key) {
      var li = orderList.querySelector('[data-order-key="' + CSS.escape(key) + '"]');
      if (li) li.remove();
    }

    function syncFromCheckboxes() {
      var checked = {};
      root.querySelectorAll('[data-item-key]').forEach(function (cb) {
        var key = cb.getAttribute('data-item-key');
        if (cb.checked) {
          checked[key] = true;
          ensureInOrder(key);
        } else {
          removeFromOrder(key);
        }
      });
      orderList.querySelectorAll('[data-order-key]').forEach(function (li) {
        var key = li.getAttribute('data-order-key');
        if (!checked[key]) li.remove();
      });
      refreshMeta();
    }

    function refreshMeta() {
      var keys = selectedKeys();
      var n = keys.length;
      countEl.textContent = selectedLabel.replace('%d', String(n));
      btn.disabled = n === 0;
      orderFields.innerHTML = '';
      keys.forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order[]';
        input.value = key;
        orderFields.appendChild(input);
      });
    }

    function applyFilter() {
      var q = (search && search.value ? search.value : '').trim().toLowerCase();
      root.querySelectorAll('.app-docs-row').forEach(function (row) {
        var group = row.getAttribute('data-group') || '';
        var name = row.getAttribute('data-name') || '';
        var groupOk = filter === 'all' || group === 'core' || group === filter;
        var searchOk = !q || name.indexOf(q) !== -1;
        row.hidden = !(groupOk && searchOk);
      });
      var empty = root.querySelector('.app-docs-empty-inline');
      if (empty) empty.hidden = filter !== 'all' && !!q;
    }

    root.querySelectorAll('.app-docs-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        root.querySelectorAll('.app-docs-chip').forEach(function (c) {
          c.classList.remove('is-active');
        });
        chip.classList.add('is-active');
        filter = chip.getAttribute('data-filter') || 'all';
        applyFilter();
      });
    });

    if (search) {
      search.addEventListener('input', applyFilter);
    }

    root.querySelectorAll('[data-item-key]').forEach(function (cb) {
      cb.addEventListener('change', syncFromCheckboxes);
    });

    orderList.addEventListener('dragstart', function (e) {
      var li = e.target.closest('[data-order-key]');
      if (!li) return;
      dragKey = li.getAttribute('data-order-key');
      li.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    orderList.addEventListener('dragend', function (e) {
      var li = e.target.closest('[data-order-key]');
      if (li) li.classList.remove('is-dragging');
      dragKey = null;
      refreshMeta();
    });
    orderList.addEventListener('dragover', function (e) {
      e.preventDefault();
      var over = e.target.closest('[data-order-key]');
      if (!over || !dragKey || over.getAttribute('data-order-key') === dragKey) return;
      var dragging = orderList.querySelector('[data-order-key="' + CSS.escape(dragKey) + '"]');
      if (!dragging) return;
      var rect = over.getBoundingClientRect();
      var before = e.clientY < rect.top + rect.height / 2;
      orderList.insertBefore(dragging, before ? over : over.nextSibling);
    });

    form.addEventListener('submit', function () {
      refreshMeta();
      if (btn.disabled) return false;
      btn.disabled = true;
      statusEl.hidden = false;
      statusEl.textContent = generatingLabel;
    });

    syncFromCheckboxes();
    applyFilter();
  }

  document.querySelectorAll('[data-app-docs-picker]').forEach(initPicker);
})();
