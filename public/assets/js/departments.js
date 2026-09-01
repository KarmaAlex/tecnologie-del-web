document.addEventListener('DOMContentLoaded', function () {
	var modal = document.getElementById('department-modal');
	var dataElement = document.getElementById('department-doctors-data');
	var availableDoctors = document.getElementById('available-doctors');
	var assignedDoctors = document.getElementById('assigned-doctors');
	var noAssignedDoctors = document.getElementById('no-assigned-doctors');
	var departmentIdInput = document.getElementById('assignment-department-id');
	var doctorIdInput = document.getElementById('assignment-doctor-id');
	var actionInput = document.getElementById('assignment-action');
	var modalTitle = document.getElementById('department-modal-title');
	var modalMessage = document.getElementById('department-modal-message');
	var assignmentForm = document.querySelector('.department-assignment-form');
	var doctors;

	if (!modal || !dataElement) {
		return;
	}

	try {
		doctors = JSON.parse(dataElement.textContent || '[]');
	} catch (error) {
		doctors = [];
	}

	function renderDepartment(departmentId, departmentName) {
		var departmentDoctors = doctors.filter(function (doctor) {
			return Number(doctor.department_id) === Number(departmentId);
		});
		var availableDoctorsForAssignment = doctors.filter(function (doctor) {
			return Number(doctor.department_id) !== Number(departmentId);
		});

		modalTitle.textContent = 'Medici di ' + departmentName;
		departmentIdInput.value = departmentId;
		modalMessage.textContent = '';
		availableDoctors.innerHTML = '<option value="">Seleziona un medico</option>';
		availableDoctorsForAssignment.forEach(function (doctor) {
			var option = document.createElement('option');
			option.value = doctor.id;
			option.textContent = doctor.full_name + ' - ' + (doctor.specialization_name || 'Generale')
				+ (doctor.department_name ? ' (' + doctor.department_name + ')' : '');
			availableDoctors.appendChild(option);
		});

		assignedDoctors.innerHTML = '';
		departmentDoctors.forEach(function (doctor) {
			var item = document.createElement('li');
			var label = document.createElement('span');
			label.textContent = doctor.full_name + ' - ' + (doctor.specialization_name || 'Generale');
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'btn-action btn-danger';
			button.textContent = 'Rimuovi';
			button.addEventListener('click', function () {
				if (!window.confirm('Rimuovere questo medico dal reparto?')) {
					return;
				}
				doctorIdInput.value = doctor.id;
				actionInput.value = 'unassign_doctor';
				assignmentForm.submit();
			});
			item.appendChild(label);
			item.appendChild(button);
			assignedDoctors.appendChild(item);
		});
		noAssignedDoctors.hidden = departmentDoctors.length > 0;
		modal.showModal();
	}

	document.querySelectorAll('[data-department-modal-open]').forEach(function (button) {
		button.addEventListener('click', function () {
			renderDepartment(button.dataset.departmentModalOpen, button.dataset.departmentName);
		});
	});

	availableDoctors.addEventListener('change', function () {
		doctorIdInput.value = availableDoctors.value;
		actionInput.value = 'assign_doctor';
		document.querySelector('[data-assignment-submit]').textContent = 'Assegna';
	});

	document.querySelector('[data-department-modal-close]').addEventListener('click', function () {
		modal.close();
	});

	modal.addEventListener('click', function (event) {
		if (event.target === modal) {
			modal.close();
		}
	});
});