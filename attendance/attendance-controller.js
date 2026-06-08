/* STUDENTS */
(() => {
  'use strict';

  if (!window.ATTENDANCE_CONFIG) {
    console.error('Attendance configuration not found');
    return;
  }

  const {
    attendanceDates,
    attendanceStats,
    currentMonth: initialMonth,
    currentYear: initialYear
  } = window.ATTENDANCE_CONFIG;

  const attendanceMap = {};
  attendanceDates.forEach(d => attendanceMap[d] = true);

  let currentMonth = initialMonth;
  let currentYear = initialYear;

  const grid  = document.getElementById('calendarGrid');
  const title = document.getElementById('calendarTitle');

  function renderCalendar(month, year) {
    grid.innerHTML = '';
    title.textContent = new Date(year, month - 1)
      .toLocaleString('default', { month: 'long', year: 'numeric' });

    const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    weekdays.forEach(d => {
      const el = document.createElement('div');
      el.className = 'weekday';
      el.textContent = d;
      grid.appendChild(el);
    });

    const firstDay = new Date(year, month - 1, 1);
    const totalDays = new Date(year, month, 0).getDate();
    const startWeekday = firstDay.getDay();

    const today = new Date();

    for (let i = 0; i < startWeekday; i++) {
      grid.appendChild(document.createElement('div')).className = 'empty';
    }

    for (let day = 1; day <= totalDays; day++) {
      const dateKey =
        `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;

      const cell = document.createElement('div');
      cell.className = 'date-cell ' +
        (attendanceMap[dateKey] ? 'present' : 'absent');

      if (
        day === today.getDate() &&
        month === today.getMonth() + 1 &&
        year === today.getFullYear()
      ) {
        cell.classList.add('today');
      }

      const span = document.createElement('span');
      span.className = 'day-number';
      span.textContent = day;

      cell.appendChild(span);
      grid.appendChild(cell);
    }
  }

  function updateAttendanceSummary(month, year) {
    const yearData = attendanceStats[year] || { yearTotal: 0, months: {} };

    document.getElementById('yearTotal').textContent =
      yearData.yearTotal || 0;

    document.getElementById('monthTotal').textContent =
      yearData.months?.[month] || 0;
  }

  document.getElementById('prevMonth')?.addEventListener('click', () => {
    currentMonth--;
    if (currentMonth < 1) {
      currentMonth = 12;
      currentYear--;
    }
    renderCalendar(currentMonth, currentYear);
    updateAttendanceSummary(currentMonth, currentYear);
  });

  document.getElementById('nextMonth')?.addEventListener('click', () => {
    currentMonth++;
    if (currentMonth > 12) {
      currentMonth = 1;
      currentYear++;
    }
    renderCalendar(currentMonth, currentYear);
    updateAttendanceSummary(currentMonth, currentYear);
  });

  renderCalendar(currentMonth, currentYear);
  updateAttendanceSummary(currentMonth, currentYear);

})();




/* ADMINS */
const table = document.querySelector('.attendance-table');

if (table) {
  table.addEventListener('mouseover', e => {
    const cell = e.target.closest('td[data-row][data-col]');
    if (!cell) return;

    const row = cell.dataset.row;
    const col = cell.dataset.col;

    table.querySelectorAll(`[data-col="${col}"]`)
      .forEach(el => el.classList.add('col-hover'));

    table.querySelectorAll(`[data-row="${row}"]`)
      .forEach(el => el.classList.add('row-hover'));

    cell.classList.add('cell-focus');
  });

  table.addEventListener('mouseout', () => {
    table.querySelectorAll('.col-hover,.row-hover,.cell-focus')
      .forEach(el =>
        el.classList.remove('col-hover','row-hover','cell-focus')
      );
  });
}