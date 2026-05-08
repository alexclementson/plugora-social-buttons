/* Plugora Floating Social Buttons — admin JS */
(function ($) {
	'use strict';
	if (typeof window.PlugoraSB === 'undefined') return;

	var $form = $('#plugora-sb-form');
	if (!$form.length) return;

	var $list = $('#plugora-sb-buttons');

	// Drag-to-reorder.
	if ($list.length && $.fn.sortable) {
		$list.sortable({
			handle: '.plugora-sb-row__handle',
			placeholder: 'plugora-sb-row is-dragging',
			tolerance: 'pointer',
			update: rebuildIndices
		});
	}

	// Add new button.
	$('#plugora-sb-add-button').on('click', function () {
		var key = $('#plugora-sb-add-platform').val();
		var p = window.PlugoraSB.platforms[key];
		if (!p) return;
		var tplHtml = $('#plugora-sb-row-template').html()
			.replace(/__INDEX__/g, $list.children().length)
			.replace(/__PLATFORM__/g, key);
		var $row = $(tplHtml);
		// Patch label/icon since template was rendered with a fake platform.
		$row.find('.plugora-sb-row__label').text(p.label);
		$row.find('.plugora-sb-row__icon').html(p.icon).css('color', p.brand_color);
		$row.find('.plugora-sb-row__value').attr('placeholder', p.placeholder);
		$row.find('.plugora-sb-row__custom-label').attr('placeholder', p.label);
		$row.find('.plugora-sb-row__color').attr('placeholder', p.brand_color);
		$row.attr('data-platform', key);
		$list.append($row);
		rebuildIndices();
	});

	// Remove button.
	$list.on('click', '.plugora-sb-row__remove', function () {
		if (!confirm(window.PlugoraSB.i18n.remove_confirm)) return;
		$(this).closest('.plugora-sb-row').remove();
		rebuildIndices();
	});

	// Reset to defaults.
	$('#plugora-sb-reset').on('click', function () {
		if (!confirm(window.PlugoraSB.i18n.reset_confirm)) return;
		$form.find('input[type=text],input[type=number],input[type=hidden]').each(function () {
			var name = this.name || '';
			if (!name.startsWith('plugora_sb_settings')) return;
			this.value = '';
		});
		$form.find('input[type=checkbox]').prop('checked', false);
	});

	// Re-number all input names so PHP receives a clean array.
	function rebuildIndices() {
		$list.children('.plugora-sb-row').each(function (i) {
			$(this).find('input,select,textarea').each(function () {
				if (!this.name) return;
				this.name = this.name.replace(/\[buttons\]\[\d+\]/, '[buttons][' + i + ']');
			});
		});
	}
})(jQuery);
