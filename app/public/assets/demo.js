(() => {
    'use strict';

    const API = '/api/v1';
    const MODES = {
        inn: { label: 'ИНН', placeholder: '7707083893', maxlength: 12, regex: /^\d{10}$|^\d{12}$/, help: 'Введите 10 или 12 цифр ИНН.', error: 'ИНН должен состоять из 10 или 12 цифр.' },
        ogrn: { label: 'ОГРН', placeholder: '1027700132195', maxlength: 15, regex: /^\d{13}$|^\d{15}$/, help: 'Введите 13 или 15 цифр ОГРН.', error: 'ОГРН должен состоять из 13 или 15 цифр.' },
    };
    const $ = (id) => document.getElementById(id);
    const el = {
        form: $('search-form'), input: $('identifier'), prefix: $('input-prefix'), help: $('identifier-help'),
        clientError: $('client-error'), submit: $('submit-button'), submitLabel: $('submit-label'), spinner: $('submit-spinner'),
        requestUrl: $('request-url'), healthPill: $('health-pill'), healthText: $('health-text'),
        responseMeta: $('response-meta'), viewSwitch: $('view-switch'), empty: $('empty-state'), loading: $('loading-state'),
        error: $('error-state'), errorCode: $('error-code'), errorType: $('error-type'), errorMessage: $('error-message'),
        errorHint: $('error-hint'), result: $('result-state'), cardView: $('card-view'), jsonView: $('json-view'),
        jsonCode: document.querySelector('#json-code code'), copyJson: $('copy-json'),
    };
    const state = { mode: 'inn', view: 'card', json: '', identifier: '', last: null, busy: false };
    const escapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => escapeMap[char]);
    const has = (value) => value !== null && value !== undefined && String(value).trim() !== '';
    const dash = (value) => (has(value) ? esc(value) : '<span class="text-muted">—</span>');
    const codeName = (value) => (!value || (!has(value.name) && !has(value.code))
        ? dash(null)
        : `${esc(value.name || '—')}${has(value.code) ? `<span class="fact-code">${esc(value.code)}</span>` : ''}`);
    const ruDate = (value) => {
        const match = has(value) ? /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value)) : null;
        return match ? `${match[3]}.${match[2]}.${match[1]}` : value;
    };
    const fact = (label, value, wide = false, mono = false) => `<div class="fact${wide ? ' is-wide' : ''}"><span class="fact-label">${esc(label)}</span><span class="fact-value${mono ? ' is-mono' : ''}">${value}</span></div>`;
    const textList = (items) => (items || []).map((item) => esc(item)).join('<br>');

    function setMode(mode, clear = true) {
        state.mode = mode;
        document.querySelectorAll('.segment[data-mode]').forEach((button) => {
            const active = button.dataset.mode === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', String(active));
        });
        el.prefix.textContent = MODES[mode].label;
        el.help.textContent = MODES[mode].help;
        el.input.placeholder = MODES[mode].placeholder;
        el.input.maxLength = MODES[mode].maxlength;
        if (clear) el.input.value = '';
        clearClientError();
        updateRequestLine();
    }

    function updateRequestLine() {
        const value = el.input.value.trim();
        el.requestUrl.textContent = `${API}/company?${state.mode}=${value === '' ? '' : encodeURIComponent(value)}`;
    }

    function showClientError(message) {
        el.clientError.textContent = message;
        el.clientError.classList.remove('hidden');
    }

    function clearClientError() {
        el.clientError.textContent = '';
        el.clientError.classList.add('hidden');
    }

    function showState(name) {
        el.empty.classList.toggle('hidden', name !== 'empty');
        el.loading.classList.toggle('hidden', name !== 'loading');
        el.error.classList.toggle('hidden', name !== 'error');
        el.result.classList.toggle('hidden', name !== 'result');
        el.viewSwitch.classList.toggle('hidden', name !== 'result');
        el.responseMeta.classList.toggle('hidden', name === 'empty');
    }

    function setBusy(busy) {
        state.busy = busy;
        el.submit.disabled = busy;
        el.spinner.classList.toggle('hidden', !busy);
        el.submitLabel.textContent = busy ? 'Запрашиваю checko' : 'Получить досье';
    }

    function setView(view) {
        state.view = view;
        el.cardView.classList.toggle('hidden', view !== 'card');
        el.jsonView.classList.toggle('hidden', view !== 'json');
        document.querySelectorAll('[data-view]').forEach((button) => {
            const active = button.dataset.view === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', String(active));
        });
        if (view === 'json') {
            el.jsonCode.scrollTop = 0;
            requestAnimationFrame(() => { el.jsonCode.scrollTop = 0; });
        }
    }

    function scrollToResult() {
        if (!window.matchMedia('(max-width: 1023px)').matches) return;
        const panel = document.querySelector('.result-panel');
        if (panel) {
            const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            window.scrollTo({ top: Math.max(0, window.scrollY + panel.getBoundingClientRect().top - 12), behavior });
        }
    }

    function errorHint(status, type) {
        return {
            401: 'Серверный ключ checko недействителен. Проверьте TOKEN в окружении.',
            404: 'checko не нашёл организацию с такими реквизитами. Проверьте ИНН или ОГРН.',
            422: 'Проверьте формат реквизита: ИНН — 10 или 12 цифр, ОГРН — 13 или 15 цифр.',
            429: 'Превышен лимит запросов к checko. Подождите и повторите.',
            502: 'checko вернул неожиданный ответ. Подробности — в серверном логе.',
            504: 'checko не ответил после повторных попыток. Попробуйте позже.',
        }[status] || (type === 'NetworkError'
            ? 'Нет связи с API. Проверьте, что контейнеры запущены.'
            : 'Запрос не удался. Измените реквизит или повторите позже.');
    }

    function renderError(status, payload, elapsed) {
        const error = payload?.error || {};
        const code = error.code || status || 500;
        el.errorCode.textContent = String(code);
        el.errorType.textContent = error.type || 'RequestError';
        el.errorMessage.textContent = error.message || 'Запрос не выполнен.';
        el.errorHint.textContent = errorHint(code, error.type);
        el.responseMeta.textContent = `${status || '—'} / ${elapsed} ms`;
        el.responseMeta.classList.remove('hidden');
        showState('error');
        scrollToResult();
    }

    function renderCompany(company) {
        const active = company.status?.code === '001' || /действ/i.test(company.status?.name || '');
        const address = company.address?.address || [company.address?.settlement, company.address?.address].filter(has).join(', ');
        const risks = [
            ['Недостоверная запись', company.flags?.unreliablePost],
            ['Дисквалифицированные лица', company.flags?.disqualifiedPersons],
            ['Массовые руководители', company.flags?.massHeads],
            ['Массовые учредители', company.flags?.massFounders],
            ['Нелегальное финансирование', company.flags?.illegalFinancing],
            ['Санкции организации', company.flags?.sanctions],
            ['Санкции учредителей', company.flags?.founderSanctions],
        ];
        const heads = (company.heads || []).map((head) => `<div class="head-row">
            <div class="head-name">${esc(head.name)}</div>
            <div class="head-position">${esc(head.position || head.positionType || 'Должность не указана')}</div>
            <div class="head-meta">${[has(head.inn) ? `ИНН ${esc(head.inn)}` : null, has(head.recordDate) ? `запись ${esc(ruDate(head.recordDate))}` : null].filter(Boolean).join(' / ') || 'дополнительных сведений нет'}</div>
        </div>`).join('');
        const contacts = [];
        if (company.contacts?.phones?.length) contacts.push(`<div class="contact-item"><span class="contact-label">Телефон</span><span class="contact-value">${textList(company.contacts.phones)}</span></div>`);
        if (company.contacts?.emails?.length) contacts.push(`<div class="contact-item"><span class="contact-label">Почта</span><span class="contact-value">${company.contacts.emails.map((email) => `<a class="contact-value" href="mailto:${esc(email)}">${esc(email)}</a>`).join('<br>')}</span></div>`);
        ['website', 'vk', 'max'].forEach((key) => {
            if (!has(company.contacts?.[key])) return;
            const url = String(company.contacts[key]);
            const safe = /^https?:\/\//i.test(url);
            contacts.push(`<div class="contact-item"><span class="contact-label">${key === 'website' ? 'Сайт' : key === 'vk' ? 'ВКонтакте' : 'MAX'}</span>${safe ? `<a class="contact-value" href="${esc(url)}" target="_blank" rel="noopener">${esc(url)}</a>` : `<span class="contact-value">${esc(url)}</span>`}</div>`);
        });
        const related = Array.isArray(company.additional?.['СвязУчред']) ? company.additional['СвязУчред'].length : 0;
        const capital = has(company.capital?.amount) ? Number(company.capital.amount).toLocaleString('ru-RU') + ' ₽' : null;

        el.cardView.innerHTML = `<article class="dossier">
            <div class="dossier-head">
                <div>
                    <p class="section-kicker">Организация / ЕГРЮЛ</p>
                    <h2 class="dossier-name mt-2">${esc(company.nameShort)}</h2>
                    ${has(company.nameFull) ? `<p class="dossier-full-name">${esc(company.nameFull)}</p>` : ''}
                </div>
                ${company.status ? `<div class="status-stamp${active ? '' : ' is-inactive'}"><span class="stamp-code">${esc(company.status.code || '—')}</span><span>${esc(company.status.name || 'Статус не указан')}</span></div>` : ''}
            </div>
            <div class="fact-grid">
                ${fact('ОГРН', dash(company.ogrn), false, true)}
                ${fact('ИНН', dash(company.inn), false, true)}
                ${fact('КПП', dash(company.kpp))}
                ${fact('ОКПО', dash(company.okpo))}
                ${fact('Дата регистрации', dash(ruDate(company.registrationDate)))}
                ${fact('Дата ОГРН', dash(ruDate(company.ogrnDate)))}
                ${fact('Регион', codeName(company.region))}
                ${fact('Организационная форма', codeName(company.legalForm))}
                ${fact('Основной ОКВЭД', company.primaryOkved ? `${esc(company.primaryOkved.name)}<span class="fact-code">${esc(company.primaryOkved.code)}</span>` : dash(null), true)}
                ${fact('Юридический адрес', dash(address), true)}
            </div>
            <div class="dossier-section">
                <div class="section-title"><h3>Риски и отметки</h3><span class="section-count">${risks.filter(([, value]) => value).length} из ${risks.length}</span></div>
                <div class="risk-grid">${risks.map(([label, value]) => `<div class="risk${value ? ' is-set' : ''}">${esc(label)}</div>`).join('')}</div>
            </div>
            <div class="dossier-section">
                <div class="section-title"><h3>Руководство</h3><span class="section-count">${(company.heads || []).length}</span></div>
                <div class="head-list">${heads || '<div class="head-row"><div class="head-position">Сведения о руководителе отсутствуют.</div></div>'}</div>
            </div>
            ${contacts.length ? `<div class="dossier-section"><div class="section-title"><h3>Контакты</h3></div><div class="contact-list mt-3">${contacts.join('')}</div></div>` : ''}
            <div class="dossier-section">
                <div class="section-title"><h3>Масштаб</h3></div>
                <div class="stat-row mt-3">
                    <div class="stat"><div class="stat-value">${(company.licenses || []).length}</div><div class="stat-label">лицензии</div></div>
                    <div class="stat"><div class="stat-value">${(company.branches || []).length}</div><div class="stat-label">филиалы</div></div>
                    <div class="stat"><div class="stat-value">${(company.representations || []).length}</div><div class="stat-label">представительства</div></div>
                    <div class="stat"><div class="stat-value">${related}</div><div class="stat-label">связанные компании</div></div>
                </div>
            </div>
            <div class="mt-6 border-t border-line pt-4 font-mono text-[10px] leading-5 text-muted">
                Данные checko на ${esc(ruDate(company.actualityDate) || 'дату запроса')}${capital ? ` / капитал ${capital}` : ''}
            </div>
        </article>`;
        setView('card');
    }

    async function runRequest(mode, value) {
        if (state.busy) return;
        state.last = { mode, value };
        state.identifier = value;
        setBusy(true);
        clearClientError();
        el.responseMeta.textContent = 'запрос выполняется…';
        el.responseMeta.classList.remove('hidden');
        showState('loading');
        history.replaceState(null, '', `${window.location.pathname}?${new URLSearchParams({ [mode]: value })}`);
        const started = performance.now();

        try {
            const response = await fetch(`${API}/company?${new URLSearchParams({ [mode]: value })}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => null);
            const elapsed = Math.max(1, Math.round(performance.now() - started));
            state.json = JSON.stringify(payload, null, 2);

            if (!response.ok || payload?.error) {
                renderError(response.status, payload, elapsed);
                return;
            }

            el.jsonCode.textContent = state.json;
            renderCompany(payload.data);
            el.responseMeta.textContent = `${response.status} / ${elapsed} ms`;
            showState('result');
            scrollToResult();
        } catch {
            renderError(0, { error: { type: 'NetworkError', message: 'Не удалось связаться с API.' } }, Math.max(1, Math.round(performance.now() - started)));
        } finally {
            setBusy(false);
        }
    }

    function submitForm() {
        const value = el.input.value.trim();
        if (value === '') {
            showClientError('Введите реквизит организации.');
            el.input.focus();
            return;
        }
        if (!MODES[state.mode].regex.test(value)) {
            showClientError(MODES[state.mode].error);
            el.input.focus();
            return;
        }
        runRequest(state.mode, value);
    }

    async function checkHealth() {
        try {
            const response = await fetch(`${API}/health`, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') throw new Error('not ok');
            el.healthPill.dataset.state = 'ok';
            el.healthText.textContent = 'API доступен';
        } catch {
            el.healthPill.dataset.state = 'fail';
            el.healthText.textContent = 'API недоступен';
        }
    }

    el.form.addEventListener('submit', (event) => { event.preventDefault(); submitForm(); });
    el.input.addEventListener('input', () => { clearClientError(); updateRequestLine(); });
    document.querySelectorAll('.segment[data-mode]').forEach((button) => button.addEventListener('click', () => { setMode(button.dataset.mode); el.input.focus(); }));
    document.querySelectorAll('[data-mode][data-value]').forEach((button) => button.addEventListener('click', () => {
        setMode(button.dataset.mode, false);
        el.input.value = button.dataset.value;
        updateRequestLine();
        submitForm();
    }));
    document.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));
    el.copyJson.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(state.json);
            el.copyJson.textContent = 'Скопировано';
            setTimeout(() => { el.copyJson.textContent = 'Скопировать'; }, 1500);
        } catch {
            el.copyJson.textContent = 'Не удалось';
        }
    });
    el.error.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="retry"]') && state.last) runRequest(state.last.mode, state.last.value);
    });

    const params = new URLSearchParams(window.location.search);
    const mode = params.has('ogrn') ? 'ogrn' : 'inn';
    const value = params.get(mode) || '';
    setMode(mode, false);
    if (value) {
        el.input.value = value;
        updateRequestLine();
        submitForm();
    } else {
        updateRequestLine();
        showState('empty');
    }
    checkHealth();
})();
