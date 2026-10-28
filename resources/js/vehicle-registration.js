const form = document.getElementById('vehicle-registration');
if (form) {
    const panels = [...form.querySelectorAll('[data-panel]')];
    const steps = [...form.querySelectorAll('[data-step]')];
    const back = document.getElementById('registration-back');
    const next = document.getElementById('registration-next');
    const save = document.getElementById('registration-save');
    let current = 0;
    form.noValidate = true;
    const show = index => {
        current = Math.max(0, Math.min(panels.length - 1, index));
        panels.forEach((panel, i) => { panel.hidden = i !== current; });
        steps.forEach((button, i) => {
            if (i === current) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
            button.classList.toggle('is-complete', i < current);
        });
        back.hidden = current === 0; next.hidden = current === panels.length - 1;
        save.hidden = current !== panels.length - 1;
        document.getElementById('registration-progress').textContent = `Step ${current + 1} of ${panels.length}`;
    };
    const validatePanel = () => {
        const invalid = [...panels[current].querySelectorAll('input,select,textarea')].find(input => !input.disabled && !input.checkValidity());
        if (invalid) invalid.reportValidity();
        return !invalid;
    };
    steps.forEach(button => button.addEventListener('click', () => show(Number(button.dataset.step))));
    back.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => { if (validatePanel()) show(current + 1); });
    form.addEventListener('submit', event => {
        const invalid = [...form.elements].find(input => input.willValidate && !input.checkValidity());
        if (invalid) {
            event.preventDefault();
            show(Number(invalid.closest('[data-panel]')?.dataset.panel ?? 0));
            invalid.reportValidity();
        } else { save.disabled = true; save.textContent = 'Saving registration…'; }
    });
    const enabled = document.getElementById('device-enabled');
    const toggle = () => {
        for (const input of form.querySelectorAll('#device-fields input,#device-fields select,[data-panel="2"] input,[data-panel="2"] select')) input.disabled = !enabled.checked;
        document.getElementById('device_identifier').required = enabled.checked;
        document.getElementById('device_status').required = enabled.checked;
        document.getElementById('device-fields').style.opacity = enabled.checked ? '1' : '.5';
    };
    enabled.addEventListener('change', toggle); toggle();
    const models = JSON.parse(document.getElementById('registration-models').textContent);
    const modelSelect = document.getElementById('device_device_model_id');
    const summary = () => {
        const model = models.find(model => String(model.id) === modelSelect.value);
        document.getElementById('device-model-summary').textContent = model
            ? `${model.name} · ${model.manufacturer || 'Manufacturer not recorded'} · ${model.protocol.toUpperCase()} ${model.protocol_version || '(version not set)'} · ${model.network_generation || 'Network not recorded'}`
            : 'Select a model, or keep the current profile of an already installed device.';
    };
    modelSelect.addEventListener('change', summary); summary();
    const urls = new Map();
    for (const input of form.querySelectorAll('input[type="file"]')) input.addEventListener('change', () => {
        for (const url of urls.get(input.id) ?? []) URL.revokeObjectURL(url);
        urls.set(input.id, []);
        const preview = form.querySelector(`[data-previews="${input.id}"]`); preview.replaceChildren();
        const files = [...input.files];
        input.setCustomValidity(files.length > 5 ? 'Choose up to five files.' : files.some(f => f.size > 5 * 1024 * 1024) ? 'Each file must be 5 MB or smaller.' : '');
        for (const file of files) {
            const figure = document.createElement('figure');
            if (['image/jpeg','image/png','image/webp'].includes(file.type)) {
                const image = document.createElement('img'), url = URL.createObjectURL(file);
                urls.get(input.id).push(url); image.src = url; image.alt = file.name; figure.append(image);
            }
            const caption = document.createElement('figcaption'); caption.textContent = file.name; figure.append(caption); preview.append(figure);
        }
        input.reportValidity();
    });
    window.addEventListener('pagehide', () => { for (const list of urls.values()) for (const url of list) URL.revokeObjectURL(url); });
    const error = form.querySelector('[data-invalid]');
    show(Number(error?.closest('[data-panel]')?.dataset.panel ?? 0));
}
