(function () {
	'use strict';

	const dialog = document.getElementById('fonik-api-key-dialog');
	const openButton = document.getElementById('fonik-api-key-open');
	const closeButton = document.getElementById('fonik-api-key-close');
	const copyButton = document.getElementById('fonik-api-key-copy');
	const input = document.getElementById('fonik-api-key-value');
	const feedback = document.getElementById('fonik-api-key-feedback');

	if (!dialog || !openButton || !closeButton || !copyButton || !input || !feedback) {
		return;
	}

	openButton.addEventListener('click', function () {
		feedback.textContent = '';
		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		}
		else {
			dialog.setAttribute('open', 'open');
		}
		input.focus();
		input.select();
	});

	closeButton.addEventListener('click', function () {
		dialog.close();
	});

	dialog.addEventListener('click', function (event) {
		if (event.target === dialog) {
			dialog.close();
		}
	});

	copyButton.addEventListener('click', async function () {
		try {
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(input.value);
			}
			else {
				input.focus();
				input.select();
				document.execCommand('copy');
			}
			feedback.textContent = 'API Key کپی شد.';
		}
		catch (error) {
			feedback.textContent = 'کپی خودکار انجام نشد؛ کلید را انتخاب و کپی کنید.';
		}
	});
}());
