(() => {
    // Version switch in System → Einstellungen: the hidden span next to the tag select carries URLs and labels
    const getConfig = () => {
        const element = document.querySelector('[data-memo-version-switch]');

        if (!element) {
            return null;
        }

        return {
            urls: JSON.parse(element.dataset.urls),
            labels: JSON.parse(element.dataset.labels),
            current: element.dataset.current,
        };
    };

    const MAX_LOG_CHARS = 300000;

    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

    const format = (text, value) => text.replace('%s', value);

    const duration = (run) => {
        if (!run?.startedAt) {
            return '';
        }

        const end = run.finishedAt ? Date.parse(run.finishedAt) : Date.now();
        const seconds = Math.max(0, Math.round((end - Date.parse(run.startedAt)) / 1000));

        return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    };

    const post = async(url, fields = {}) => {
        const body = new FormData();
        body.append('REQUEST_TOKEN', document.querySelector('input[name="REQUEST_TOKEN"]')?.value ?? '');
        Object.entries(fields).forEach(([key, value]) => body.append(key, value));

        const response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        const data = await response.json().catch(() => ({
            error: `HTTP ${response.status}`,
        }));

        if (!response.ok || data.error) {
            throw new Error(data.error ?? `HTTP ${response.status}`);
        }

        return data;
    };

    // <progress> values cannot be transitioned in CSS, so the bar is tweened towards each new value
    const animateProgress = (bar, label, target) => {
        const from = Number(bar.value);
        const startedAt = window.performance.now();

        window.cancelAnimationFrame(bar.memoFrame);

        if (target <= from) {
            bar.value = target;
            label.textContent = `${Math.floor(target)}%`;

            return;
        }

        const step = (now) => {
            const t = Math.min(1, (now - startedAt) / 900);
            const value = from + ((target - from) * (1 - ((1 - t) ** 3)));

            bar.value = value;
            label.textContent = `${Math.floor(value)}%`;

            if (t < 1) {
                bar.memoFrame = window.requestAnimationFrame(step);
            }
        };

        bar.memoFrame = window.requestAnimationFrame(step);
    };

    const renderDialog = (labels) => {
        document.querySelector('dialog.memo-version-dialog')?.remove();

        // Same markup as Contao's dialog component (component/dialog/_dialog.html.twig), so the contao--dialog
        // controller opens it and the backend theme styles it
        document.body.insertAdjacentHTML('beforeend', `
            <dialog class="memo-version-dialog" data-controller="contao--dialog" data-turbo-temporary>
                <div class="dialog_wrapper">
                    <div class="header_wrapper">
                        <h3>${escape(labels.title)}</h3>
                        <button
                            type="button"
                            class="memo-version-dialog__close"
                            data-action="contao--dialog#close"
                            aria-label="${escape(labels.close)}"
                            title="${escape(labels.close)}"
                        >&times;</button>
                    </div>
                    <form method="dialog">
                        <div class="memo-version-dialog__body">
                            <select class="tl_select" data-run aria-label="${escape(labels.run)}"></select>
                            <div class="memo-version-dialog__status">
                                <span data-details></span>
                                <span>${escape(labels.elapsed)} <span data-elapsed></span></span>
                            </div>
                            <label class="jobs-progress memo-version-dialog__progress">
                                <progress class="jobs-progress-bar" value="0" max="100" data-progress></progress>
                                <span class="jobs-progress-label" data-progress-label></span>
                            </label>
                            <ol class="memo-version-dialog__steps" data-steps></ol>
                            <p class="tl_error" data-error hidden></p>
                            <details class="memo-version-dialog__details" open>
                                <summary>${escape(labels.details)}</summary>
                                <pre class="memo-version-dialog__log" data-log></pre>
                            </details>
                        </div>
                        <div class="buttons tl_submit_container">
                            <button type="button" data-do="relogin" hidden>${escape(labels.relogin)}</button>
                            <button type="button" data-do="rollback" hidden></button>
                            <button type="button" data-do="restore" hidden>${escape(labels.restore)}</button>
                            <button type="button" class="close" data-action="contao--dialog#close">
                                ${escape(labels.close)}
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>`);

        const dialog = document.querySelector('dialog.memo-version-dialog');

        // contao--dialog only exists from Contao 5.6, and Esc closes the dialog without removing it
        dialog.addEventListener('close', () => dialog.remove());
        dialog.querySelectorAll('[data-action="contao--dialog#close"]').forEach((close) => {
            close.addEventListener('click', () => dialog.remove());
        });

        if (!dialog.open) {
            dialog.showModal();
        }

        return dialog;
    };

    const openModal = async(config, start = null) => {
        const urls = config.urls;
        const labels = config.labels;
        const root = renderDialog(labels);
        const element = (name) => root.querySelector(`[data-${name}]`);
        const button = (name) => root.querySelector(`[data-do="${name}"]`);

        let selectedRun = '';
        let state = null;
        let timer = null;
        let generation = 0;
        let finishedHere = false;

        const showError = (message) => {
            element('error').textContent = message ?? '';
            element('error').hidden = !message;
        };

        const details = (run) => {
            const parts = [];

            if (run.running) {
                parts.push(`${labels.step}: ${labels.steps[run.step] ?? run.step ?? '…'}`);
            }

            if (run.backup && 'restore' !== run.action) {
                parts.push(`${labels.backup}: ${run.backup}`);
            }

            if (run.message) {
                parts.push(run.message);
            }

            return parts.join(' – ');
        };

        const renderRun = (run, appendLog) => {
            element('details').textContent = details(run);
            element('elapsed').textContent = duration(run);
            animateProgress(element('progress'), element('progress-label'), run.progress ?? 0);

            element('steps').innerHTML = (run.steps ?? [])
                .map((step) => `<li class="memo-version-dialog__step is-${escape(step.state)}">
                    ${escape(labels.steps[step.key] ?? step.key)}
                </li>`)
                .join('');

            const log = element('log');
            const atBottom = log.scrollTop + log.clientHeight >= log.scrollHeight - 20;
            const text = appendLog ? log.textContent + (run.log ?? '') : (run.log ?? '');

            if (text !== log.textContent) {
                log.textContent = text.length > MAX_LOG_CHARS ? `…${text.slice(-MAX_LOG_CHARS)}` : text;

                if (atBottom) {
                    log.scrollTop = log.scrollHeight;
                }
            }
        };

        const renderOverview = (data) => {
            const runSelect = element('run');
            const options = data.runs
                .map((item) => `<option value="${escape(item.runId)}">${escape(item.label)}</option>`)
                .join('');

            if (runSelect.innerHTML !== options) {
                runSelect.innerHTML = options;
            }

            runSelect.value = data.run?.runId ?? '';
            button('rollback').hidden = !data.rollbackRef || data.running;
            button('rollback').textContent = format(labels.rollback, data.rollbackRef ?? '');
            // Without a matching backup the button stays visible but disabled and names the reason
            const restore = button('restore');

            restore.hidden = !data.restoreBackup && (!data.restoreReason || 'none' === data.restoreReason);
            restore.disabled = data.running || !data.restoreBackup;
            restore.textContent = data.restoreBackup ? labels.restore : labels[`restore_${data.restoreReason}`];
            restore.title = data.restoreBackup ?? '';
        };

        const schedule = (callback, delay) => {
            clearTimeout(timer);
            timer = setTimeout(callback, delay);
        };

        const closed = () => {
            if (root.isConnected) {
                return false;
            }

            // contao--dialog#close removes the dialog; reload once so the select and description show the new version
            if (finishedHere) {
                window.location.reload();
            }

            return true;
        };

        let load = null;

        // Session-free and incremental: only the bytes appended to the log since the last poll
        const poll = async(pollUrl, offset, current) => {
            if (closed() || current !== generation) {
                return;
            }

            const response = await fetch(`${pollUrl}&offset=${offset}`, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'omit',
            }).catch(() => null);

            if (current !== generation) {
                return;
            }

            // composer and the job clear the cache, so single requests can fail: keep polling
            const data = response?.ok ? await response.json().catch(() => null) : null;

            if (!data?.run) {
                element('details').textContent = labels.busy;
                schedule(() => poll(pollUrl, offset, current), 2000);

                return;
            }

            renderRun(data.run, true);

            if (data.run.running) {
                schedule(() => poll(pollUrl, data.run.offset, current), 1000);

                return;
            }

            finishedHere = true;
            load();
        };

        // Logged in: the selected run with its log tail, the run list and the actions; called on open and after a run
        load = async() => {
            generation += 1;

            const current = generation;
            const query = selectedRun ? `?run=${encodeURIComponent(selectedRun)}` : '';

            clearTimeout(timer);

            if (closed()) {
                return;
            }

            const response = await fetch(`${urls.status}${query}`, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                redirect: 'manual',
            }).catch(() => null);

            if (current !== generation) {
                return;
            }

            // A redirect is the backend login: the session has ended, the job itself keeps running
            if ('opaqueredirect' === response?.type || [401, 403].includes(response?.status)) {
                showError(labels.session);
                button('relogin').hidden = false;

                return;
            }

            const data = response?.ok ? await response.json().catch(() => null) : null;

            if (!data) {
                element('details').textContent = labels.busy;
                schedule(load, 2000);

                return;
            }

            state = data;
            renderOverview(data);

            if (!data.run) {
                return;
            }

            renderRun(data.run, false);

            if (data.run.running) {
                schedule(() => poll(data.run.pollUrl, data.run.logOffset, current), 1000);
            }
        };

        const run = async(action, question) => {
            if (!window.confirm(question)) {
                return;
            }

            showError(null);

            selectedRun = await post(urls[action]).then((data) => data.runId ?? '', (error) => {
                showError(error.message);

                return selectedRun;
            });

            load();
        };

        element('run').addEventListener('change', (event) => {
            selectedRun = event.target.value;
            load();
        });

        button('rollback').addEventListener('click', () => {
            run('rollback', format(labels.confirm_rollback, state?.rollbackRef ?? ''));
        });

        button('restore').addEventListener('click', () => {
            run('restore', format(labels.confirm_restore, state?.restoreBackup ?? ''));
        });

        button('relogin').addEventListener('click', () => window.location.reload());

        if (start) {
            selectedRun = await start().then((runId) => runId ?? '', (error) => {
                showError(error.message);

                return '';
            });
        }

        load();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-memo-version-action]');
        const config = getConfig();

        if (!trigger || !config) {
            return;
        }

        event.preventDefault();

        if ('log' === trigger.dataset.memoVersionAction) {
            openModal(config);

            return;
        }

        const tag = document.getElementById('ctrl_memo_version_tag')?.value ?? '';

        if (!tag) {
            window.alert(config.labels.no_tag);

            return;
        }

        if (tag === config.current) {
            window.alert(format(config.labels.already_current, tag));

            return;
        }

        if (!window.confirm(format(config.labels.confirm_switch, tag))) {
            return;
        }

        openModal(config, async() => {
            const data = await post(config.urls.switch, {
                tag,
            });

            return data.runId;
        });
    });
})();
