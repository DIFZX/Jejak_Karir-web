const applicationModal = document.querySelector('#applicationModal');
const applicationForm = document.querySelector('#applicationForm');
const modalTitle = document.querySelector('#modalTitle');
const formAction = document.querySelector('#formAction');
const applicationId = document.querySelector('#applicationId');
const submitButton = document.querySelector('#submitButton');
const channelSource = document.querySelector('#channelSource');
const channelCustomField = document.querySelector('#channelCustomField');
const channelCustom = document.querySelector('#channelCustom');

function setChannelValue(value = '') {
    if (!channelSource || !channelCustomField || !channelCustom) return;

    const normalizedValue = String(value ?? '').trim();
    const presetExists = [...channelSource.options].some(option => (
        option.value !== '' &&
        option.value !== '__other__' &&
        option.value === normalizedValue
    ));
    const useCustom = normalizedValue !== '' && !presetExists;

    channelSource.value = useCustom ? '__other__' : normalizedValue;
    channelCustom.value = useCustom ? normalizedValue : '';
    channelCustomField.classList.toggle('visible', useCustom);
    channelCustom.disabled = !useCustom;
    channelCustom.required = useCustom;
}

function updateChannelInput() {
    if (!channelSource || !channelCustomField || !channelCustom) return;

    const useCustom = channelSource.value === '__other__';
    channelCustomField.classList.toggle('visible', useCustom);
    channelCustom.disabled = !useCustom;
    channelCustom.required = useCustom;
    if (!useCustom) {
        channelCustom.value = '';
    } else {
        requestAnimationFrame(() => channelCustom.focus());
    }
}

function toDatetimeLocal(value) {
    return value ? String(value).slice(0, 16).replace(' ', 'T') : '';
}

function renderStatusHistory(history) {
    const container = document.querySelector('#statusHistory');
    const timeline = document.querySelector('#statusTimeline');
    if (!container || !timeline) return;

    container.classList.toggle('visible', Array.isArray(history) && history.length > 0);
    timeline.replaceChildren();
    (history || []).forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'timeline-item';
        const marker = document.createElement('i');
        const content = document.createElement('div');
        const status = document.createElement('strong');
        const date = document.createElement('small');
        status.textContent = item.status;
        const parsedDate = new Date(String(item.changed_at).replace(' ', 'T'));
        date.textContent = Number.isNaN(parsedDate.getTime())
            ? item.changed_at
            : parsedDate.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        content.append(status, date);
        row.append(marker, content);
        timeline.append(row);
    });
}

function setScheduleEnabled(enabled) {
    const checkbox = document.querySelector('#scheduleEnabled');
    const fields = document.querySelector('#scheduleFields');
    if (!checkbox || !fields) return;

    checkbox.checked = enabled;
    fields.classList.toggle('visible', enabled);
    fields.querySelectorAll('input').forEach(input => {
        input.disabled = !enabled;
    });
}

function openModal(application = null) {
    if (!applicationModal || !applicationForm) return;

    applicationForm.reset();
    setChannelValue('');
    formAction.value = application ? 'update' : 'create';
    applicationId.value = application?.id ?? '';
    modalTitle.textContent = application ? 'Edit Riwayat Lamaran' : 'Catat Lamaran Baru';
    submitButton.textContent = application ? 'Simpan Perubahan' : 'Simpan Lamaran';
    renderStatusHistory(application?.history || []);
    setScheduleEnabled(Boolean(
        application?.follow_up_at || application?.interview_at || application?.deadline_at
    ));

    if (application) {
        document.querySelector('#company').value = application.company ?? '';
        document.querySelector('#position').value = application.position ?? '';
        setChannelValue(application.channel ?? '');
        document.querySelector('#applicationStatus').value = application.status ?? 'Terkirim';
        document.querySelector('#applicationPriority').value = application.priority ?? 'Sedang';
        document.querySelector('#notes').value = application.notes ?? '';
        document.querySelector('#followUpAt').value = toDatetimeLocal(application.follow_up_at);
        document.querySelector('#interviewAt').value = toDatetimeLocal(application.interview_at);
        document.querySelector('#deadlineAt').value = toDatetimeLocal(application.deadline_at);
    }

    applicationModal.classList.add('visible');
    applicationModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    setTimeout(() => document.querySelector('#company')?.focus(), 100);
}

function closeModal() {
    applicationModal?.classList.remove('visible');
    applicationModal?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => openModal());
});
channelSource?.addEventListener('change', updateChannelInput);
if (channelSource?.value === '__other__') {
    setChannelValue(channelCustom?.value || '');
} else {
    updateChannelInput();
}
document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeModal));
applicationModal?.addEventListener('click', event => {
    if (event.target === applicationModal) closeModal();
});

document.querySelectorAll('.edit-button').forEach(button => {
    button.addEventListener('click', () => {
        try {
            openModal(JSON.parse(button.dataset.application));
        } catch {
            window.alert('Data tidak dapat dibuka.');
        }
    });
});

const confirmModal = document.querySelector('#confirmModal');
const deleteCompany = document.querySelector('#deleteCompany');
let pendingDeleteForm = null;

document.querySelectorAll('[data-delete-form]').forEach(form => {
    form.addEventListener('submit', event => {
        event.preventDefault();
        pendingDeleteForm = form;
        deleteCompany.textContent = form.dataset.company;
        confirmModal.classList.add('visible');
        confirmModal.setAttribute('aria-hidden', 'false');
    });
});

document.querySelector('[data-cancel-delete]')?.addEventListener('click', () => {
    confirmModal.classList.remove('visible');
    confirmModal.setAttribute('aria-hidden', 'true');
    pendingDeleteForm = null;
});
document.querySelector('[data-confirm-delete]')?.addEventListener('click', () => pendingDeleteForm?.submit());
confirmModal?.addEventListener('click', event => {
    if (event.target === confirmModal) document.querySelector('[data-cancel-delete]').click();
});

document.querySelector('[data-close-alert]')?.addEventListener('click', event => {
    event.target.closest('[data-alert]')?.remove();
});
setTimeout(() => document.querySelector('[data-alert]')?.classList.add('fade-out'), 4500);

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (confirmModal?.classList.contains('visible')) document.querySelector('[data-cancel-delete]').click();
    else if (exportModal?.classList.contains('visible')) closeExportModal();
    else if (notificationDropdown?.classList.contains('visible')) setNotificationOpen(false);
    else if (accountDropdown?.classList.contains('visible')) setAccountOpen(false);
    else closeModal();
});

if (applicationModal?.classList.contains('visible')) {
    document.body.classList.add('modal-open');
}

document.querySelector('#scheduleEnabled')?.addEventListener('change', event => {
    setScheduleEnabled(event.target.checked);
});
setScheduleEnabled(document.querySelector('#scheduleEnabled')?.checked || false);

const themeButtons = document.querySelectorAll('[data-theme-toggle]');
const storedTheme = localStorage.getItem('jejak-karier-theme');
if (storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.dataset.theme = 'dark';
}
themeButtons.forEach(themeButton => {
    themeButton.addEventListener('click', () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        if (dark) {
            delete document.documentElement.dataset.theme;
            localStorage.setItem('jejak-karier-theme', 'light');
        } else {
            document.documentElement.dataset.theme = 'dark';
            localStorage.setItem('jejak-karier-theme', 'dark');
        }
    });
});

const sidebar = document.querySelector('#mainSidebar');
const sidebarToggle = document.querySelector('[data-open-sidebar]');
const sidebarOverlay = document.querySelector('.sidebar-overlay');

function setSidebar(open) {
    sidebar?.classList.toggle('visible', open);
    sidebarOverlay?.classList.toggle('visible', open);
    sidebar?.setAttribute('aria-hidden', String(!open));
    sidebarToggle?.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('sidebar-open', open);
}

sidebarToggle?.addEventListener('click', () => setSidebar(true));
document.querySelectorAll('[data-close-sidebar], [data-sidebar-link]').forEach(element => {
    element.addEventListener('click', () => setSidebar(false));
});

const exportModal = document.querySelector('#exportModal');
const exportDate = document.querySelector('#exportDate');
const exportDateField = document.querySelector('#exportDateField');

function setExportScope(scope) {
    const useDate = scope === 'date';
    exportDateField?.classList.toggle('visible', useDate);
    if (exportDate) {
        exportDate.disabled = !useDate;
        exportDate.required = useDate;
        if (!useDate) exportDate.value = '';
    }
}

function openExportModal() {
    setSidebar(false);
    exportModal?.classList.add('visible');
    exportModal?.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeExportModal() {
    exportModal?.classList.remove('visible');
    exportModal?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

document.querySelector('[data-open-export]')?.addEventListener('click', openExportModal);
document.querySelectorAll('[data-close-export]').forEach(button => {
    button.addEventListener('click', closeExportModal);
});
exportModal?.addEventListener('click', event => {
    if (event.target === exportModal) closeExportModal();
});
document.querySelectorAll('input[name="export_scope"]').forEach(radio => {
    radio.addEventListener('change', () => setExportScope(radio.value));
});
setExportScope(document.querySelector('input[name="export_scope"]:checked')?.value || 'all');

const notificationMenu = document.querySelector('.notification-menu');
const notificationButton = document.querySelector('[data-notification-toggle]');
const notificationDropdown = document.querySelector('[data-notification-dropdown]');

function setNotificationOpen(open) {
    notificationDropdown?.classList.toggle('visible', open);
    notificationDropdown?.setAttribute('aria-hidden', String(!open));
    notificationButton?.setAttribute('aria-expanded', String(open));
}

notificationButton?.addEventListener('click', event => {
    event.stopPropagation();
    setAccountOpen(false);
    setNotificationOpen(!notificationDropdown?.classList.contains('visible'));
});
notificationDropdown?.addEventListener('click', event => event.stopPropagation());
document.querySelectorAll('[data-notification-link]').forEach(link => {
    link.addEventListener('click', () => setNotificationOpen(false));
});
document.addEventListener('click', event => {
    if (notificationMenu && !notificationMenu.contains(event.target)) {
        setNotificationOpen(false);
    }
});

const accountDropdownMenu = document.querySelector('.account-dropdown-menu');
const accountToggle = document.querySelector('[data-account-toggle]');
const accountDropdown = document.querySelector('[data-account-dropdown]');

function setAccountOpen(open) {
    accountDropdown?.classList.toggle('visible', open);
    accountDropdown?.setAttribute('aria-hidden', String(!open));
    accountToggle?.setAttribute('aria-expanded', String(open));
}

accountToggle?.addEventListener('click', event => {
    event.stopPropagation();
    setNotificationOpen(false);
    setAccountOpen(!accountDropdown?.classList.contains('visible'));
});
accountDropdown?.addEventListener('click', event => event.stopPropagation());
document.addEventListener('click', event => {
    if (accountDropdownMenu && !accountDropdownMenu.contains(event.target)) {
        setAccountOpen(false);
    }
});

const sidebarLinks = document.querySelectorAll('[data-sidebar-link]');
const observedSections = [...sidebarLinks]
    .map(link => document.querySelector(link.getAttribute('href')))
    .filter(Boolean);
if ('IntersectionObserver' in window && observedSections.length) {
    const sectionObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            sidebarLinks.forEach(link => {
                link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`);
            });
        });
    }, { rootMargin: '-25% 0px -65% 0px' });
    observedSections.forEach(section => sectionObserver.observe(section));
}

const chartButtons = document.querySelectorAll('[data-chart-button]');
chartButtons.forEach(button => {
    button.addEventListener('click', () => {
        const mode = button.dataset.chartButton;
        chartButtons.forEach(item => item.classList.toggle('active', item === button));
        document.querySelectorAll('[data-chart]').forEach(chart => {
            chart.classList.toggle('is-hidden', chart.dataset.chart !== mode);
        });
        const subtitle = document.querySelector('[data-chart-subtitle]');
        if (subtitle) {
            subtitle.textContent = mode === 'daily' ? 'Tujuh hari terakhir' : 'Enam bulan terakhir';
        }
    });
});
