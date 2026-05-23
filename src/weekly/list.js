const weekListSection = document.getElementById('week-list-section');

function createWeekArticle(week) {
  const article = document.createElement('article');

  const title = document.createElement('h2');
  title.textContent = week.title;

  const startDate = document.createElement('p');
  startDate.textContent = 'Starts on: ' + week.start_date;

  const description = document.createElement('p');
  description.textContent = week.description;

  const link = document.createElement('a');
  link.href = 'details.html?id=' + week.id;
  link.textContent = 'View Details & Discussion';

  article.appendChild(title);
  article.appendChild(startDate);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

async function loadWeeks() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  weekListSection.innerHTML = '';

  if (result.success && Array.isArray(result.data)) {
    for (const week of result.data) {
      weekListSection.appendChild(createWeekArticle(week));
    }
  }
}

loadWeeks();
