import axios from 'axios';

export function createDeviceCommandDialog() {
    const dialog = document.createElement('dialog');
    dialog.className = 'gps-command-dialog';
    dialog.setAttribute('aria-labelledby', 'gps-command-title');
    dialog.innerHTML = `<header><div><h3 id="gps-command-title">Device commands</h3><small data-device></small></div><button type="button" data-close aria-label="Close device commands">×</button></header>
        <p data-connection role="status">Loading connection…</p>
        <p class="gps-command-note">Commands use the tracker's active TCP connection. Firmware support varies. “Sent” means written to the connection; only the actual device reply confirms its result.</p>
        <div data-error role="alert" hidden></div>
        <div class="gps-command-columns"><section><h4>Command catalog</h4><input data-search type="search" placeholder="Search STATUS, WHERE, SERVER, GMT…" aria-label="Search device commands"><div data-catalog></div></section>
        <section><h4>Configure command</h4><p data-help>Select Configure or an action from the catalog. Query buttons send the displayed read-only command.</p>
        <form data-form><label>Exact command<input data-command maxlength="180" autocomplete="off" placeholder="Select a command" required></label>
        <label class="gps-command-confirm"><input data-confirm type="checkbox"> I reviewed this command and confirm changing this device.</label>
        <button type="submit" data-send disabled>Send command</button></form>
        <h4 class="mt-4">Command replies</h4><button type="button" data-refresh>Refresh replies</button><div data-history aria-live="polite"></div></section></div>`;
    document.body.append(dialog);
    const $ = selector => dialog.querySelector(selector);
    let deviceId = null, generation = 0, timer, controller, catalog = [], available = false, pending = false, sending = false, uncertain = null;
    let historySignature = '', catalogSignature = '';
    const error = message => { $('[data-error]').textContent = message; $('[data-error]').hidden = !message; };
    const controls = () => {
        const disabled = !available || pending || sending;
        $('[data-send]').disabled = disabled;
        dialog.querySelectorAll('[data-query]').forEach(button => { button.disabled = disabled; });
    };
    const renderCatalog = () => {
        const term = $('[data-search]').value.trim().toLowerCase();
        const list = $('[data-catalog]'); list.replaceChildren();
        for (const item of catalog.filter(entry => `${entry.key} ${entry.name}`.toLowerCase().includes(term))) {
            const row = document.createElement('article'); row.className = 'gps-command-entry';
            const title = document.createElement('strong'); title.textContent = `${item.key} — ${item.name}`; row.append(title);
            if (item.query) {
                const query = document.createElement('button'); query.type = 'button'; query.dataset.query = item.query;
                query.textContent = `Send ${item.query}`; query.onclick = () => send(item.query, false); row.append(query);
            }
            if (item.example || item.action) {
                const configure = document.createElement('button'); configure.type = 'button'; configure.textContent = item.action ? 'Prepare action' : 'Configure';
                configure.onclick = () => {
                    $('[data-help]').textContent = `${item.name}. ${item.help} ${item.example ? `Format: ${item.example}` : ''}`;
                    $('[data-command]').value = item.action ?? (item.example.includes('<') || item.example.includes(' or ') ? '' : item.example);
                    $('[data-command]').placeholder = item.action ?? item.example;
                    $('[data-confirm]').checked = false;
                    $('[data-command]').focus();
                }; row.append(configure);
            }
            list.append(row);
        }
        if (!list.children.length) list.textContent = 'No matching commands.';
        controls();
    };
    const renderHistory = rows => {
        const signature = JSON.stringify(rows);
        if (historySignature === signature) return;
        historySignature = signature;
        const list = $('[data-history]'); list.replaceChildren();
        for (const row of rows) {
            const article = document.createElement('article'); article.className = 'gps-command-reply';
            const heading = document.createElement('strong'); heading.textContent = `#${row.id} ${row.command} — ${row.status.replace('_', ' ')}`;
            const time = document.createElement('small'); time.textContent = new Date(row.created_at.replace(' ', 'T') + 'Z').toLocaleString();
            const response = document.createElement('pre'); response.textContent = row.response ?? row.error ?? (row.status === 'sent' ? 'Waiting for device reply (up to 60 seconds)…' : 'Waiting for dispatch (up to 30 seconds)…');
            article.append(heading, time, response); list.append(article);
        }
        if (!rows.length) list.textContent = 'No commands have been sent to this device.';
    };
    async function refresh() {
        if (!dialog.open || deviceId == null) return;
        clearTimeout(timer); controller?.abort(); controller = new AbortController();
        const current = generation;
        try {
            const {data} = await axios.get(`/admin/gps-devices/${deviceId}/commands`, {signal: controller.signal, timeout: 10000});
            if (current !== generation || !dialog.open) return;
            available = data.supported && data.connected;
            pending = data.commands.some(row => ['queued', 'writing', 'sent'].includes(row.status));
            $('[data-device]').textContent = `${data.label} · IMEI ${data.imei}`;
            $('[data-connection]').textContent = !data.supported ? 'This command catalog is for PT06 devices.' : !data.connected
                ? 'Device TCP connection unavailable. Commands will be enabled when it reconnects.'
                : pending ? 'Device connected · waiting for the pending command result.' : 'Device connected · ready for commands.';
            const signature = JSON.stringify(data.catalog);
            if (catalogSignature !== signature) { catalog = data.catalog; catalogSignature = signature; renderCatalog(); }
            renderHistory(data.commands); controls();
        } catch (failure) {
            if (current !== generation || failure.code === 'ERR_CANCELED') return;
            available = false; controls();
            error(failure.response?.data?.message ?? 'Could not refresh command status. Retrying…');
        } finally {
            if (current === generation && dialog.open) timer = setTimeout(refresh, 3000);
        }
    }
    async function send(command, confirmed) {
        if (!available || pending || sending) return;
        const current = generation, target = deviceId;
        const request = uncertain?.command === command ? uncertain : {command, confirmed, request_id: crypto.randomUUID()};
        uncertain = request; sending = true; controls(); error('');
        try {
            await axios.post(`/admin/gps-devices/${target}/commands`, request, {timeout: 10000});
            if (current !== generation) return;
            uncertain = null; pending = true; $('[data-confirm]').checked = false;
            await refresh();
        } catch (failure) {
            if (current !== generation) return;
            if (failure.response) uncertain = null;
            error(failure.response?.data?.message ?? 'Delivery status is unknown. Check replies before retrying; retrying the same command reuses its request ID.');
        } finally {
            if (current === generation) { sending = false; controls(); }
        }
    }
    $('[data-form]').onsubmit = event => {
        event.preventDefault();
        const command = $('[data-command]').value.trim();
        const query = catalog.some(item => item.query === command) || command === 'SENSORRANGE,CHECK#';
        if (!query && !$('[data-confirm]').checked) { error('Review the command and check the confirmation box before changing the device.'); return; }
        send(command, !query && $('[data-confirm]').checked);
    };
    $('[data-command]').oninput = () => { $('[data-confirm]').checked = false; };
    $('[data-search]').oninput = renderCatalog;
    $('[data-refresh]').onclick = refresh;
    $('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => { generation++; clearTimeout(timer); controller?.abort(); deviceId = null; });
    return {
        open(device) {
            generation++; clearTimeout(timer); controller?.abort(); deviceId = Number(device.device_id);
            sending = false; pending = false; available = false; uncertain = null; historySignature = ''; catalogSignature = '';
            $('[data-device]').textContent = `${device.registration ?? device.name} · ${device.imei}`;
            $('[data-connection]').textContent = 'Checking device connection…';
            $('[data-history]').replaceChildren(); $('[data-catalog]').replaceChildren();
            $('[data-command]').value = ''; $('[data-confirm]').checked = false; $('[data-search]').value = '';
            error(''); controls(); if (!dialog.open) dialog.showModal(); refresh();
        },
        dispose() { generation++; clearTimeout(timer); controller?.abort(); dialog.remove(); },
    };
}
