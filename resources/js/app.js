import './bootstrap';
import 'bootstrap';
import 'admin-lte/dist/js/adminlte';
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';
import { initThemeCustomizer } from './theme-customizer';
import { initNotificationCenter } from './notifications';
import { initIncomingCallRinger } from './calls';

window.$ = window.jQuery = $;
window.Swal = Swal;
window.Chart = Chart;

try {
    DataTable(window, $);
} catch (e) {
    // datatables.net's UMD bootstrap reassigns the bareword `window`, which
    // throws under ES module strict mode; harmless, DataTable still works
    // when instantiated per-table below.
}

document.addEventListener('DOMContentLoaded', () => {
    initThemeCustomizer();
    initNotificationCenter();
    initIncomingCallRinger();

    if (window.__trackerSelfTrackingEnabled && !document.getElementById('location-sharing-toggle')) {
        import('./gps-watcher').then(({ GpsWatcher }) => {
            if (window.__trackerSelfGpsWatcher) return;
            window.__trackerSelfGpsWatcher = new GpsWatcher();
            window.__trackerSelfGpsWatcher.start();
        });
    }

    if (window.__trackerUserId && window.axios) {
        const sendPresenceHeartbeat = () => {
            window.axios.post('/presence/heartbeat').catch(() => {});
        };

        sendPresenceHeartbeat();
        window.setInterval(sendPresenceHeartbeat, 30000);
    }

    document.querySelectorAll('[data-datatable]').forEach((table) => {
        new DataTable(table, {
            responsive: true,
            pageLength: 10,
            lengthChange: false,
            autoWidth: false,
        });
    });

    const alertMessage = document.querySelector('[data-toast-message]');

    if (alertMessage) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false,
            icon: alertMessage.dataset.toastType ?? 'success',
            title: alertMessage.dataset.toastMessage,
        });
    }

    document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            Swal.fire({
                title: form.dataset.confirmTitle ?? 'Are you sure?',
                text: form.dataset.confirmText ?? 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton ?? 'Yes, continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc3545',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });

    const sidebarSearch = document.querySelector('[data-sidebar-search]');

    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', () => {
            const term = sidebarSearch.value.trim().toLowerCase();
            const topLevelItems = document.querySelectorAll('.tracker-sidebar-nav > .nav-item');

            topLevelItems.forEach((item) => {
                const isParent = item.classList.contains('tracker-nav-parent');

                if (! isParent) {
                    const matches = term === '' || item.textContent.toLowerCase().includes(term);
                    item.style.display = matches ? '' : 'none';
                    return;
                }

                const children = item.querySelectorAll(':scope > .tracker-submenu > .nav-item');
                let anyChildVisible = false;

                children.forEach((child) => {
                    const matches = term === '' || child.textContent.toLowerCase().includes(term);
                    child.style.display = matches ? '' : 'none';
                    anyChildVisible = anyChildVisible || matches;
                });

                item.style.display = anyChildVisible ? '' : 'none';
                item.classList.toggle('tracker-force-open', term !== '' && anyChildVisible);
            });
        });
    }

    document.querySelectorAll('[data-country-select]').forEach((countrySelect) => {
        const stateSelect = document.querySelector(countrySelect.dataset.stateTarget ?? '[data-state-select]');
        const citySelect = document.querySelector(countrySelect.dataset.cityTarget ?? '[data-city-select]');

        const loadStates = (countryId, selectedStateId) => {
            if (! stateSelect) {
                return;
            }

            stateSelect.innerHTML = '<option value="">Select State</option>';

            if (citySelect) {
                citySelect.innerHTML = '<option value="">Select City</option>';
            }

            if (! countryId) {
                return;
            }

            fetch(`/admin/geo/states/${countryId}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((states) => {
                    states.forEach((state) => {
                        const option = document.createElement('option');
                        option.value = state.id;
                        option.textContent = state.name;
                        option.selected = String(state.id) === String(selectedStateId);
                        stateSelect.appendChild(option);
                    });
                });
        };

        const loadCities = (stateId, selectedCityId) => {
            if (! citySelect) {
                return;
            }

            citySelect.innerHTML = '<option value="">Select City</option>';

            if (! stateId) {
                return;
            }

            fetch(`/admin/geo/cities/${stateId}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((cities) => {
                    cities.forEach((city) => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        option.selected = String(city.id) === String(selectedCityId);
                        citySelect.appendChild(option);
                    });
                });
        };

        countrySelect.addEventListener('change', () => loadStates(countrySelect.value));

        if (stateSelect) {
            stateSelect.addEventListener('change', () => loadCities(stateSelect.value));
        }
    });
});
