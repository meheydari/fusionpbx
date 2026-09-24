document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	var popupType = document.getElementById('popup_type');
	if (!popupType) {
		return;
	}

	function refreshPopupType() {
		document.querySelectorAll('[data-fonik-group="popup-api"]').forEach(function (field) {
			field.hidden = popupType.value !== 'API';
		});
		document.querySelectorAll('[data-fonik-group="popup-socket"]').forEach(function (field) {
			field.hidden = popupType.value !== 'SOCKET';
		});
	}

	popupType.addEventListener('change', refreshPopupType);
	refreshPopupType();
});
