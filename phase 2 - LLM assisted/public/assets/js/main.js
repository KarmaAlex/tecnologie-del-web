document.addEventListener('DOMContentLoaded', function () {
	var toggle = document.getElementById('menuToggle');
	var menu = document.getElementById('quickMenu');

	if (!toggle || !menu) {
		return;
	}

	toggle.addEventListener('click', function () {
		var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
		toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
		menu.hidden = isExpanded;
	});
});
