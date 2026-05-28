let weeks = [];

const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

function createWeekRow(week) {
  const tr = document.createElement('tr');

  const titleCell = document.createElement('td');
  titleCell.textContent = week.title;

  const startDateCell = document.createElement('td');
  startDateCell.textContent = week.start_date;

  const descriptionCell = document.createElement('td');
  descriptionCell.textContent = week.description;

  const actionsCell = document.createElement('td');

  const editButton = document.createElement('button');
  editButton.className = 'edit-btn';
  editButton.dataset.id = week.id;
  editButton.textContent = 'Edit';

  const deleteButton = document.createElement('button');
  deleteButton.className = 'delete-btn';
  deleteButton.dataset.id = week.id;
  deleteButton.textContent = 'Delete';

  actionsCell.appendChild(editButton);
  actionsCell.appendChild(deleteButton);

  tr.appendChild(titleCell);
  tr.appendChild(startDateCell);
  tr.appendChild(descriptionCell);
  tr.appendChild(actionsCell);

  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = '';

  for (const week of weeks) {
    weeksTbody.appendChild(createWeekRow(week));
  }
}

function getFormFields() {
  const title = document.getElementById('week-title').value.trim();
  const start_date = document.getElementById('week-start-date').value.trim();
  const description = document.getElementById('week-description').value.trim();
  const links = document.getElementById('week-links').value
    .split('\n')
    .map(link => link.trim())
    .filter(link => link !== '');

  return { title, start_date, description, links };
}

async function handleAddWeek(event) {
  event.preventDefault();

  const submitButton = document.getElementById('add-week');
  const fields = getFormFields();
  const editId = submitButton.dataset.editId;

  if (editId) {
    await handleUpdateWeek(editId, fields);
    return;
  }

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(fields),
  });

  const result = await response.json();

  if (result.success) {
    weeks.push({ id: result.id, ...fields });
    renderTable();
    weekForm.reset();
  }
}

async function handleUpdateWeek(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: Number(id), ...fields }),
  });

  const result = await response.json();

  if (result.success) {
    weeks = weeks.map(week => {
      if (Number(week.id) === Number(id)) {
        return { ...week, ...fields };
      }

      return week;
    });

    renderTable();
    weekForm.reset();

    const submitButton = document.getElementById('add-week');
    submitButton.textContent = 'Add Week';
    delete submitButton.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    const response = await fetch('./api/index.php?id=' + id, {
      method: 'DELETE',
    });

    const result = await response.json();

    if (result.success) {
      weeks = weeks.filter(week => Number(week.id) !== Number(id));
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const week = weeks.find(item => Number(item.id) === Number(id));

    if (!week) {
      return;
    }

    document.getElementById('week-title').value = week.title;
    document.getElementById('week-start-date').value = week.start_date;
    document.getElementById('week-description').value = week.description;
    document.getElementById('week-links').value = Array.isArray(week.links)
      ? week.links.join('\n')
      : '';

    const submitButton = document.getElementById('add-week');
    submitButton.textContent = 'Update Week';
    submitButton.dataset.editId = id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  weeks = result.success && Array.isArray(result.data) ? result.data : [];
  renderTable();

  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
