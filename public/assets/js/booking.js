document.addEventListener('DOMContentLoaded', function () {
	var prescriptionsUrl = 'view_prescriptions.php';
	var prescriptionsLabel = 'Le tue prescrizioni';

	var navRight = document.querySelector('.nav-right');
	if (navRight && !navRight.querySelector('[data-nav-prescriptions]')) {
		var navLink = document.createElement('a');
		navLink.className = 'btn btn-outline';
		navLink.href = prescriptionsUrl;
		navLink.textContent = prescriptionsLabel;
		navLink.setAttribute('data-nav-prescriptions', '1');

		var primaryAction = navRight.querySelector('.btn-primary');
		if (primaryAction) {
			navRight.insertBefore(navLink, primaryAction);
		} else {
			navRight.appendChild(navLink);
		}
	}

	var quickMenu = document.getElementById('quickMenu');
	if (quickMenu && !quickMenu.querySelector('[data-nav-prescriptions]')) {
		var mobileLink = document.createElement('a');
		mobileLink.href = prescriptionsUrl;
		mobileLink.textContent = prescriptionsLabel;
		mobileLink.setAttribute('data-nav-prescriptions', '1');
		quickMenu.insertBefore(mobileLink, quickMenu.firstChild);
	}

	var filterForm = document.getElementById('booking-filter-form');
	if (!filterForm) {
		return;
	}

	var departmentSelect = document.getElementById('department_id');
	var doctorSelect = document.getElementById('doctor_id');
	var scheduleSelect = document.getElementById('schedule_id');

	if (departmentSelect) {
		departmentSelect.addEventListener('change', function () {
			if (this.value === '') {
				doctorSelect.value = '';
				if (scheduleSelect) {
					scheduleSelect.value = '';
				}
				return;
			}
			filterForm.submit();
		});
	}

	if (doctorSelect) {
		doctorSelect.addEventListener('change', function () {
			if (this.value === '') {
				if (scheduleSelect) {
					scheduleSelect.value = '';
				}
				return;
			}
			filterForm.submit();
		});
	}
});
