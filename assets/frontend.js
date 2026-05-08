/* Plugora Floating Social Buttons — front-end JS
   Tiny, dependency-free. Only handles the click-to-expand layout state. */
(function () {
	'use strict';
	var roots = document.querySelectorAll('.psb--layout-expandable, .psb--layout-popup');
	if (!roots.length) return;

	roots.forEach(function (root) {
		var btn = root.querySelector('.psb__toggle');
		if (!btn) return;
		var isClick = root.classList.contains('psb--trigger-click') || root.classList.contains('psb--layout-popup');

		function setOpen(open) {
			root.classList.toggle('is-open', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		btn.addEventListener('click', function () { setOpen(!root.classList.contains('is-open')); });

		// Close on outside click or Esc when in click mode.
		if (isClick) {
			document.addEventListener('click', function (e) {
				if (!root.contains(e.target)) setOpen(false);
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') setOpen(false);
			});
		}
	});
})();
