function nextMonth(currentMonth, currentYear) {
  let nextMonth = currentMonth + 1;
  let nextYear = currentYear;
  if (nextMonth > 12) {
    nextMonth = 1;
    nextYear += 1;
  }
  window.location.href = `calendar.php?month=${nextMonth}&year=${nextYear}`;
}

function prevMonth(currentMonth, currentYear) {
  let prevMonth = currentMonth - 1;
  let prevYear = currentYear;
  if (prevMonth < 1) {
    prevMonth = 12;
    prevYear -= 1;
  }
  window.location.href = `calendar.php?month=${prevMonth}&year=${prevYear}`;
}

function currentMoth() {
    window.location.href = `calendar.php`;
}

function closeSubscriptionModal() {
    const modal = document.getElementById('subscriptionModal');
    modal.classList.remove('is-open');
}

function runSubscriptionModalAction(endpoint, subscriptionId, successMessage) {
    safeFetch(endpoint, {
        method: 'POST',
        body: JSON.stringify({id: subscriptionId}),
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.csrfToken,
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeSubscriptionModal();
                showSuccessMessage(data.message || successMessage);
                window.location.reload();
            } else {
                showErrorMessage(data.message || translate('error'));
            }
        })
        .catch(() => showErrorMessage(translate('error')));
}

function translateWithFallback(key, fallback) {
    const translated = translate(key);
    return translated && translated !== key && translated !== key.replace(/_/g, ' ') && translated !== '[Translation Missing]'
        ? translated
        : fallback;
}

function calendarDataArgs(args) {
    return JSON.stringify(args)
        .replace(/&/g, '&amp;')
        .replace(/'/g, '&#39;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

function calendarEscapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function openDayModal(dayLabel) {
    const cell = this && this.closest ? this.closest('.calendar-cell') : null;
    if (!cell) {
        return;
    }
    const pills = Array.from(cell.querySelectorAll('.calendar-subscription-title'));
    if (pills.length === 0) {
        return;
    }
    if (pills.length === 1) {
        const args = parseDeclarativeArgs(pills[0]);
        if (args.length) {
            openSubscriptionModal(args[0]);
            return;
        }
    }

    const modal = document.getElementById('subscriptionModal');
    const modalContent = document.getElementById('subscriptionModalContent');

    const items = pills.map(pill => {
        const name = pill.querySelector('.sub-name')?.textContent?.trim() || pill.textContent.trim();
        const price = pill.querySelector('.sub-price')?.textContent?.trim() || '';
        const args = pill.getAttribute('data-args') || '[]';
        return `
            <button type="button" class="calendar-day-item" data-click="openSubscriptionModal" data-args='${calendarEscapeHtml(args)}'>
                <span class="name">${calendarEscapeHtml(name)}</span>
                <span class="price">${calendarEscapeHtml(price)}</span>
            </button>`;
    }).join('');

    const totalText = cell.querySelector('.calendar-cell-total')?.textContent?.trim() || '';
    const totalLabel = translateWithFallback('total_cost', 'Total');
    const totalRow = totalText
        ? `<div class="calendar-day-total"><span>${calendarEscapeHtml(totalLabel)}</span><span>${calendarEscapeHtml(totalText)}</span></div>`
        : '';

    modalContent.innerHTML = `
        <div class="modal-header">
            <h3>${calendarEscapeHtml(dayLabel || '')}</h3>
            <span class="fa-solid fa-xmark close-modal" data-click="closeSubscriptionModal"></span>
        </div>
        <div class="modal-body calendar-day-list">
            <div class="calendar-day-items">${items}</div>
            ${totalRow}
        </div>`;
    modal.classList.add('is-open');
}

function openSubscriptionModal(subscriptionId) {
    const modal = document.getElementById('subscriptionModal');
    const modalContent = document.getElementById('subscriptionModalContent');

    modalContent.innerHTML = '';

    withSpinner(safeFetch('endpoints/subscription/getcalendar.php', {
        method: 'POST',
        body: JSON.stringify({id: subscriptionId}),
        headers: {
          'Content-Type': 'application/json'
        }
      }), modalContent)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data) {
          const subscription = data.data;
          const paymentMissingText = translateWithFallback('payment_missing', 'Payment Missing');
          const alreadyPaidText = translateWithFallback('already_paid', 'Already Paid');
          const editText = translateWithFallback('edit', 'Edit');
          const paymentRecordedText = translateWithFallback('payment_recorded', 'Payment recorded and next payment updated.');
          const actionButtons = `
            <div class="modal-footer">
                <button class="button danger-button tiny" data-click="runSubscriptionModalAction" data-args='${calendarDataArgs(['endpoints/subscription/renew.php', Number(subscription.id), translate('success')])}'>${paymentMissingText}</button>
                <button class="button success-button tiny" data-click="runSubscriptionModalAction" data-args='${calendarDataArgs(['endpoints/subscription/markpaid.php', Number(subscription.id), paymentRecordedText])}'>${alreadyPaidText}</button>
                <button class="button secondary-button tiny" data-click="openDashboardEditModal" data-args='${calendarDataArgs([Number(subscription.id)])}'>${editText}</button>
            </div>`;
          const html = `
            <div class="modal-header">
                <h3>${subscription.name}</h3>
                <span class="fa-solid fa-xmark close-modal" data-click="closeSubscriptionModal"></span>
            </div>
            <div class="modal-body">
                ${subscription.logo ? `<div class="subscription-logo">
                <img src="images/uploads/logos/${subscription.logo}" alt="${subscription.name}">
                </div>` : ''}
                <div class="subscription-info">
                ${subscription.price ? `<p><strong>${translate('price')}:</strong> ${subscription.currency}${subscription.price}</p>` : ''}
                ${subscription.category ? `<p><strong>${translate('category')}:</strong> ${subscription.category}</p>` : ''}
                ${subscription.payer_user ? `<p><strong>${translate('paid_by')}:</strong> ${subscription.payer_user}</p>` : ''}
                ${subscription.payment_method ? `<p><strong>${translate('payment_method')}:</strong> ${subscription.payment_method}</p>` : ''}
                ${subscription.notes ? `<p><strong>${translate('notes')}:</strong> ${subscription.notes}</p>` : ''}
                </div>
            </div>
            ${actionButtons}`;
          modalContent.innerHTML = html;
          modal.classList.add('is-open');
        } else {
          console.error(data.message);
        }
      })
      .catch(error => console.error('Error:', error));
}
