/**
 * Boas-Vindas Prado Aqui - Admin SPA Application
 * Dynamic state-driven vanilla JS application for managing properties, content, guests, and reservations.
 */

// Application State
const AppState = {
    currentView: 'dashboard',
    properties: [],
    guests: [],
    reservations: [],
    activePropertyIdForContent: null,
    activeContentSection: 'welcome',
    activeContentData: {},
    subscription: null,
    stats: {
        properties: 0,
        active_reservations: 0,
        active_guides: 0,
        expired_guides: 0,
        upcoming: []
    }
};

// API Fetch Helper
const API = {
    request(endpoint, method = 'GET', data = null) {
        const url = `${pradoWelcomeData.root}prado-welcome/v1/${endpoint}`;
        const headers = {
            'X-WP-Nonce': pradoWelcomeData.nonce
        };

        if (data) {
            headers['Content-Type'] = 'application/json';
        }

        const options = {
            method: method,
            headers: headers
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        return fetch(url, options)
            .then(async response => {
                const resData = await response.json();
                if (!response.ok) {
                    throw new Error(resData.message || 'Ocorreu um erro no servidor.');
                }
                return resData;
            });
    }
};

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    initRouter();
    initModals();
    initMediaUploaders();
    
    // Load initial data
    loadAllData();
});

// Load all API data
function loadAllData() {
    showLoader(true);
    Promise.all([
        API.request('dashboard').then(stats => AppState.stats = stats),
        API.request('properties').then(props => AppState.properties = props),
        API.request('guests').then(guests => AppState.guests = guests),
        API.request('reservations').then(res => AppState.reservations = res),
        API.request('subscription').then(sub => AppState.subscription = sub)
    ])
    .then(() => {
        renderCurrentView();
        showLoader(false);
    })
    .catch(err => {
        showLoader(false);
        showToast(err.message || 'Erro ao carregar dados do servidor.', 'danger');
    });
}

// Global Loader
function showLoader(show) {
    let loader = document.getElementById('prado-app-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'prado-app-loader';
        loader.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:3px;background:#10b981;z-index:999999;transition:opacity 0.3s;pointer-events:none;opacity:0;';
        document.body.appendChild(loader);
    }
    loader.style.opacity = show ? '1' : '0';
}

// Toast Messages
function showToast(message, type = 'success') {
    let toast = document.querySelector('.admin-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'admin-toast';
        document.body.appendChild(toast);
    }
    
    toast.innerText = message;
    
    if (type === 'danger') {
        toast.style.background = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
    } else {
        toast.style.background = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
    }

    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

/**
 * SPA Router
 */
function initRouter() {
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            const targetView = item.getAttribute('data-view');
            
            // Set active menu item
            menuItems.forEach(m => m.classList.remove('active'));
            item.classList.add('active');

            // Switch view
            AppState.currentView = targetView;
            renderCurrentView();
        });
    });
}

function renderCurrentView() {
    const views = document.querySelectorAll('.app-view');
    views.forEach(v => v.classList.remove('active'));

    const activeView = document.getElementById(`view-${AppState.currentView}`);
    if (activeView) {
        activeView.classList.add('active');
    }

    // Trigger view-specific render
    switch (AppState.currentView) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'properties':
            renderProperties();
            break;
        case 'guests':
            renderGuests();
            break;
        case 'reservations':
            renderReservations();
            break;
        case 'content':
            renderContentManager();
            break;
        case 'subscription':
            renderSubscription();
            break;
    }
}

/**
 * 1. Dashboard View
 */
function renderDashboard() {
    // Check database status (defensive check)
    const dbWarning = document.getElementById('db-warning-banner');
    if (dbWarning) {
        if (AppState.stats.db_status && !AppState.stats.db_status.ok) {
            dbWarning.style.display = 'block';
            const fixBtn = document.getElementById('btn-fix-db');
            if (fixBtn) {
                fixBtn.onclick = (e) => {
                    e.preventDefault();
                    showLoader(true);
                    API.request('db-setup', 'POST')
                        .then(res => {
                            showToast(res.message || 'Banco de dados corrigido!');
                            loadAllData();
                        })
                        .catch(err => {
                            showLoader(false);
                            alert(err.message || 'Erro ao corrigir banco de dados.');
                        });
                };
            }
        } else {
            dbWarning.style.display = 'none';
        }
    }

    // Populate stats
    document.getElementById('stat-properties').innerText = AppState.stats.properties;
    document.getElementById('stat-reservations').innerText = AppState.stats.active_reservations;
    document.getElementById('stat-active-guides').innerText = AppState.stats.active_guides;
    document.getElementById('stat-expired-guides').innerText = AppState.stats.expired_guides;

    // Render upcoming list
    const tbody = document.getElementById('upcoming-guests-list');
    tbody.innerHTML = '';

    if (AppState.stats.upcoming && AppState.stats.upcoming.length > 0) {
        AppState.stats.upcoming.forEach(item => {
            const tr = document.createElement('tr');
            
            const checkin = formatDate(item.checkin_date);
            const checkout = formatDate(item.checkout_date);
            
            const badgeClass = item.guide_status === 'active' ? 'badge-active' : (item.guide_status === 'revoked' ? 'badge-revoked' : 'badge-expired');
            const statusLabel = item.guide_status === 'active' ? 'Ativo' : (item.guide_status === 'revoked' ? 'Revogado' : 'Expirado');

            // Format Link dynamically based on localized permalinks config
            const guideLink = pradoWelcomeData.use_pretty_links 
                ? `${pradoWelcomeData.home_url}g/${item.token}`
                : `${pradoWelcomeData.home_url}?g=${item.token}`;

            tr.innerHTML = `
                <td><strong>${escapeHTML(item.guest_name)}</strong></td>
                <td>${escapeHTML(item.property_name)}</td>
                <td>${checkin} a ${checkout}</td>
                <td><span class="badge ${badgeClass}">${statusLabel}</span></td>
                <td>
                    <div class="actions-row">
                        <a href="${guideLink}" target="_blank" class="btn-icon-only" title="Visualizar Guia"><i class="fa-solid fa-eye"></i></a>
                        <button class="btn-icon-only whatsapp" onclick="sendWhatsapp('${item.guest_name}', '${item.guest_phone}', '${item.property_name}', '${item.token}')" title="Enviar WhatsApp"><i class="fa-brands fa-whatsapp"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Nenhum check-in nos próximos dias.</td></tr>`;
    }
}

/**
 * 2. Properties (Meus Imóveis) View
 */
function renderProperties() {
    const listContainer = document.getElementById('properties-grid-list');
    listContainer.innerHTML = '';

    if (AppState.properties && AppState.properties.length > 0) {
        AppState.properties.forEach(prop => {
            const card = document.createElement('div');
            card.className = 'panel-card';
            card.style.cssText = 'padding:0; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; border: 1px solid var(--border-color);';

            const photo = prop.photo_main ? prop.photo_main : '';
            const logo = prop.logo ? prop.logo : '';

            card.innerHTML = `
                <div style="height: 140px; background-size: cover; background-position: center; background-image: url('${photo}'); background-color: rgba(255,255,255,0.02); position:relative;">
                    ${logo ? `<img src="${logo}" style="position:absolute; bottom:12px; left:12px; height:32px; width:auto; border-radius:4px; padding:2px; background:rgba(255,255,255,0.8); border:1px solid #ccc;" />` : ''}
                </div>
                <div style="padding: 20px;">
                    <h3 style="margin:0 0 6px 0; font-size:16px; font-weight:700;">${escapeHTML(prop.name)}</h3>
                    <p style="font-size:12px; color:var(--text-muted); margin:0 0 12px 0;"><i class="fa-solid fa-location-dot"></i> ${escapeHTML(prop.city)} - ${escapeHTML(prop.state)}</p>
                    <p style="font-size:13px; color:var(--text-secondary); line-height:1.4; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; height:36px; margin:0;">${escapeHTML(prop.description)}</p>
                </div>
                <div style="padding:16px 20px; background:rgba(0,0,0,0.15); border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <button class="btn-admin btn-admin-secondary btn-sm" onclick="editPropertyContent(${prop.id})"><i class="fa-solid fa-book-open"></i> Conteúdo</button>
                    <div class="actions-row">
                        <button class="btn-icon-only" onclick="openEditPropertyModal(${prop.id})" title="Editar Imóvel"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-icon-only" onclick="deleteProperty(${prop.id})" title="Excluir Imóvel"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `;
            listContainer.appendChild(card);
        });
    } else {
        listContainer.innerHTML = `
            <div style="grid-column: 1 / -1; text-align:center; padding:60px 20px; color:var(--text-muted);">
                <i class="fa-solid fa-hotel" style="font-size:40px; margin-bottom:12px; color:var(--color-primary);"></i>
                <p>Você ainda não cadastrou nenhum imóvel.</p>
                <button class="btn-admin btn-admin-primary" style="margin-top:12px;" onclick="openModal('modal-property')"><i class="fa-solid fa-plus"></i> Novo Imóvel</button>
            </div>
        `;
    }
}

// Property Actions
window.openAddPropertyModal = function() {
    // Check limit for non-active users
    if (AppState.stats.plan_status === 'expired') {
        showToast('Assinatura expirada. Por favor, regularize seu pagamento.', 'danger');
        return;
    }

    const form = document.getElementById('form-property');
    form.reset();
    document.getElementById('property-id-field').value = '';
    document.getElementById('modal-property-title').innerText = 'Cadastrar Novo Imóvel';
    
    // Clear image previews
    document.getElementById('photo-main-preview').src = '';
    document.getElementById('photo-main-preview').style.display = 'none';
    document.getElementById('logo-preview').src = '';
    document.getElementById('logo-preview').style.display = 'none';

    openModal('modal-property');
};

window.openEditPropertyModal = function(id) {
    const prop = AppState.properties.find(p => p.id === id);
    if (!prop) return;

    const form = document.getElementById('form-property');
    form.reset();

    document.getElementById('property-id-field').value = prop.id;
    document.getElementById('modal-property-title').innerText = 'Editar Imóvel';

    document.getElementById('prop-name').value = prop.name;
    document.getElementById('prop-photo-main').value = prop.photo_main;
    document.getElementById('prop-logo').value = prop.logo;
    document.getElementById('prop-desc').value = prop.description;
    document.getElementById('prop-hostname').value = prop.hostname;
    document.getElementById('prop-host-phone').value = prop.host_phone;
    document.getElementById('prop-host-whatsapp').value = prop.host_whatsapp;
    document.getElementById('prop-city').value = prop.city;
    document.getElementById('prop-state').value = prop.state;
    document.getElementById('prop-address').value = prop.address;
    document.getElementById('prop-location-link').value = prop.location_link;

    // Show previews if exist
    if (prop.photo_main) {
        document.getElementById('photo-main-preview').src = prop.photo_main;
        document.getElementById('photo-main-preview').style.display = 'block';
    } else {
        document.getElementById('photo-main-preview').style.display = 'none';
    }

    if (prop.logo) {
        document.getElementById('logo-preview').src = prop.logo;
        document.getElementById('logo-preview').style.display = 'block';
    } else {
        document.getElementById('logo-preview').style.display = 'none';
    }

    openModal('modal-property');
};

window.saveProperty = function(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('property-id-field').value;
    const isEdit = id !== '';

    const data = {
        name: document.getElementById('prop-name').value,
        photo_main: document.getElementById('prop-photo-main').value,
        logo: document.getElementById('prop-logo').value,
        description: document.getElementById('prop-desc').value,
        hostname: document.getElementById('prop-hostname').value,
        host_phone: document.getElementById('prop-host-phone').value,
        host_whatsapp: document.getElementById('prop-host-whatsapp').value,
        city: document.getElementById('prop-city').value,
        state: document.getElementById('prop-state').value,
        address: document.getElementById('prop-address').value,
        location_link: document.getElementById('prop-location-link').value,
    };

    if (!data.name) {
        showToast('O nome do imóvel é obrigatório.', 'danger');
        return;
    }

    showLoader(true);
    
    const requestPromise = isEdit 
        ? API.request(`properties/${id}`, 'PUT', data) 
        : API.request('properties', 'POST', data);

    requestPromise
        .then(res => {
            closeModal('modal-property');
            showToast(res.message || 'Imóvel salvo com sucesso!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao salvar imóvel.', 'danger');
        });
};

window.deleteProperty = function(id) {
    if (!confirm('Tem certeza de que deseja excluir este imóvel? Todas as informações de guias e conteúdos associados serão deletadas permanentemente.')) {
        return;
    }

    showLoader(true);
    API.request(`properties/${id}`, 'DELETE')
        .then(res => {
            showToast(res.message || 'Imóvel excluído!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao excluir imóvel.', 'danger');
        });
};

/**
 * 3. Hóspedes (Guests) View
 */
function renderGuests() {
    const tbody = document.getElementById('guests-table-body');
    tbody.innerHTML = '';

    if (AppState.guests && AppState.guests.length > 0) {
        AppState.guests.forEach(g => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${escapeHTML(g.name)}</strong></td>
                <td>${escapeHTML(g.phone || 'Não informado')}</td>
                <td>${g.pax} ${g.pax > 1 ? 'pessoas' : 'pessoa'}</td>
                <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHTML(g.notes || '')}">${escapeHTML(g.notes || '-')}</td>
                <td>
                    <div class="actions-row">
                        <button class="btn-icon-only" onclick="openEditGuestModal(${g.id})" title="Editar Hóspede"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-icon-only" onclick="deleteGuest(${g.id})" title="Excluir Hóspede"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 10px;">Nenhum hóspede cadastrado.</td></tr>`;
    }
}

window.openAddGuestModal = function() {
    const form = document.getElementById('form-guest');
    form.reset();
    document.getElementById('guest-id-field').value = '';
    document.getElementById('modal-guest-title').innerText = 'Cadastrar Novo Hóspede';
    openModal('modal-guest');
};

window.openEditGuestModal = function(id) {
    const g = AppState.guests.find(item => item.id === id);
    if (!g) return;

    const form = document.getElementById('form-guest');
    form.reset();

    document.getElementById('guest-id-field').value = g.id;
    document.getElementById('modal-guest-title').innerText = 'Editar Hóspede';

    document.getElementById('guest-name').value = g.name;
    document.getElementById('guest-phone').value = g.phone;
    document.getElementById('guest-pax').value = g.pax;
    document.getElementById('guest-notes').value = g.notes;

    openModal('modal-guest');
};

window.saveGuest = function(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('guest-id-field').value;
    const isEdit = id !== '';

    const data = {
        name: document.getElementById('guest-name').value,
        phone: document.getElementById('guest-phone').value,
        pax: document.getElementById('guest-pax').value,
        notes: document.getElementById('guest-notes').value
    };

    if (!data.name) {
        showToast('Nome é obrigatório.', 'danger');
        return;
    }

    showLoader(true);
    const requestPromise = isEdit 
        ? API.request(`guests/${id}`, 'PUT', data) 
        : API.request('guests', 'POST', data);

    requestPromise
        .then(res => {
            closeModal('modal-guest');
            showToast(res.message || 'Hóspede salvo!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao salvar hóspede.', 'danger');
        });
};

window.deleteGuest = function(id) {
    if (!confirm('Deseja realmente excluir este hóspede?')) {
        return;
    }

    showLoader(true);
    API.request(`guests/${id}`, 'DELETE')
        .then(res => {
            showToast(res.message || 'Hóspede excluído!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao excluir.', 'danger');
        });
};

/**
 * 4. Reservas (Reservations) View
 */
function renderReservations() {
    const tbody = document.getElementById('reservations-table-body');
    tbody.innerHTML = '';

    // Populate property options in reservation form dropdown
    const selectProp = document.getElementById('res-property-id');
    selectProp.innerHTML = '<option value="">Selecione um Imóvel</option>';
    AppState.properties.forEach(p => {
        selectProp.innerHTML += `<option value="${p.id}">${escapeHTML(p.name)}</option>`;
    });

    if (AppState.reservations && AppState.reservations.length > 0) {
        AppState.reservations.forEach(r => {
            const tr = document.createElement('tr');
            
            const checkin = formatDate(r.checkin_date);
            const checkout = formatDate(r.checkout_date);
            
            const badgeClass = r.guide_status === 'active' ? 'badge-active' : (r.guide_status === 'revoked' ? 'badge-revoked' : 'badge-expired');
            const statusLabel = r.guide_status === 'active' ? 'Ativo' : (r.guide_status === 'revoked' ? 'Revogado' : 'Expirado');
            const guideLink = pradoWelcomeData.use_pretty_links 
                ? `${pradoWelcomeData.home_url}g/${r.token}`
                : `${pradoWelcomeData.home_url}?g=${r.token}`;

            const revokeBtn = r.guide_status === 'active'
                ? `<button class="btn-icon-only" onclick="toggleGuideRevocation(${r.id}, 'revoke')" title="Revogar/Bloquear Guia" style="color:var(--color-danger);"><i class="fa-solid fa-ban"></i></button>`
                : `<button class="btn-icon-only" onclick="toggleGuideRevocation(${r.id}, 'activate')" title="Ativar Guia" style="color:var(--color-success);"><i class="fa-solid fa-unlock"></i></button>`;

            tr.innerHTML = `
                <td>
                    <strong>${escapeHTML(r.guest_name)}</strong>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><i class="fa-solid fa-phone"></i> ${escapeHTML(r.guest_phone || 'N/A')}</div>
                </td>
                <td>${escapeHTML(r.property_name)}</td>
                <td>${checkin} a ${checkout}</td>
                <td><span class="badge ${badgeClass}">${statusLabel}</span></td>
                <td>
                    <div class="actions-row">
                        <button class="btn-icon-only whatsapp" onclick="sendWhatsapp('${r.guest_name}', '${r.guest_phone}', '${r.property_name}', '${r.token}')" title="Enviar Guia por WhatsApp"><i class="fa-brands fa-whatsapp"></i></button>
                        <button class="btn-icon-only" onclick="openQrModal('${r.token}')" title="Gerar QR Code"><i class="fa-solid fa-qrcode"></i></button>
                        <a href="${guideLink}" target="_blank" class="btn-icon-only" title="Abrir Guia no Navegador"><i class="fa-solid fa-eye"></i></a>
                        <button class="btn-icon-only" onclick="openEditReservationModal(${r.id})" title="Editar Reserva"><i class="fa-solid fa-pen"></i></button>
                        ${revokeBtn}
                        <button class="btn-icon-only" onclick="deleteReservation(${r.id})" title="Excluir Reserva"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 10px;">Nenhuma reserva cadastrada.</td></tr>`;
    }
}

window.openAddReservationModal = function() {
    const form = document.getElementById('form-reservation');
    if (form) form.reset();
    
    const idField = document.getElementById('reservation-id-field');
    if (idField) idField.value = '';
    
    const titleField = document.getElementById('modal-reservation-title');
    if (titleField) titleField.innerText = 'Nova Reserva';
    
    const propSelect = document.getElementById('res-property-id');
    if (propSelect) {
        propSelect.disabled = false;
        
        // Populate properties dropdown dynamically (needed if opened from Dashboard view)
        propSelect.innerHTML = '<option value="">Selecione um Imóvel</option>';
        AppState.properties.forEach(p => {
            propSelect.innerHTML += `<option value="${p.id}">${escapeHTML(p.name)}</option>`;
        });
    }
    
    openModal('modal-reservation');
};

window.openEditReservationModal = function(id) {
    const r = AppState.reservations.find(item => item.id === id);
    if (!r) return;

    const form = document.getElementById('form-reservation');
    if (form) form.reset();

    const idField = document.getElementById('reservation-id-field');
    if (idField) idField.value = r.id;

    const titleField = document.getElementById('modal-reservation-title');
    if (titleField) titleField.innerText = 'Editar Reserva';

    const propSelect = document.getElementById('res-property-id');
    if (propSelect) {
        // Populate first so we don't have empty option selection error
        propSelect.innerHTML = '<option value="">Selecione um Imóvel</option>';
        AppState.properties.forEach(p => {
            propSelect.innerHTML += `<option value="${p.id}">${escapeHTML(p.name)}</option>`;
        });
        propSelect.value = r.property_id;
        propSelect.disabled = true; // Lock property swap during edit to avoid consistency problems
    }

    document.getElementById('res-guest-name').value = r.guest_name;
    document.getElementById('res-guest-phone').value = r.guest_phone;
    document.getElementById('res-guest-pax').value = r.guest_pax;
    document.getElementById('res-checkin').value = r.checkin_date;
    document.getElementById('res-checkout').value = r.checkout_date;
    document.getElementById('res-notes').value = r.notes;

    openModal('modal-reservation');
};

window.saveReservation = function(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('reservation-id-field').value;
    const isEdit = id !== '';

    const data = {
        property_id: document.getElementById('res-property-id').value,
        guest_name: document.getElementById('res-guest-name').value,
        guest_phone: document.getElementById('res-guest-phone').value,
        guest_pax: document.getElementById('res-guest-pax').value,
        checkin_date: document.getElementById('res-checkin').value,
        checkout_date: document.getElementById('res-checkout').value,
        notes: document.getElementById('res-notes').value
    };

    if (!data.property_id || !data.guest_name || !data.checkin_date || !data.checkout_date) {
        showToast('Imóvel, hóspede e datas são campos obrigatórios.', 'danger');
        return;
    }

    showLoader(true);
    const requestPromise = isEdit 
        ? API.request(`reservations/${id}`, 'PUT', data) 
        : API.request('reservations', 'POST', data);

    requestPromise
        .then(res => {
            closeModal('modal-reservation');
            showToast(res.message || 'Reserva salva com sucesso!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao criar reserva.', 'danger');
        });
};

window.toggleGuideRevocation = function(id, action) {
    const actionLabel = action === 'activate' ? 'reativar' : 'revogar';
    if (!confirm(`Tem certeza de que deseja ${actionLabel} o acesso a este guia digital?`)) {
        return;
    }

    showLoader(true);
    API.request(`reservations/${id}/revoke`, 'POST', { action: action })
        .then(res => {
            showToast(res.message || 'Status do guia alterado!');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao alterar status.', 'danger');
        });
};

window.deleteReservation = function(id) {
    if (!confirm('Deseja excluir esta reserva? O guia digital gerado e seus tokens serão invalidados imediatamente.')) {
        return;
    }

    showLoader(true);
    API.request(`reservations/${id}`, 'DELETE')
        .then(res => {
            showToast(res.message || 'Reserva excluída.');
            loadAllData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao excluir reserva.', 'danger');
        });
};

/**
 * 5. Content Manager View
 */
function renderContentManager() {
    const select = document.getElementById('content-property-select');
    select.innerHTML = '<option value="">Selecione um imóvel para editar o conteúdo</option>';
    
    AppState.properties.forEach(p => {
        const selectedAttr = AppState.activePropertyIdForContent === p.id ? 'selected' : '';
        select.innerHTML += `<option value="${p.id}" ${selectedAttr}>${escapeHTML(p.name)}</option>`;
    });

    const builderArea = document.getElementById('content-builder-area');
    
    if (AppState.activePropertyIdForContent) {
        builderArea.style.display = 'block';
        
        // Render sub-tabs navigation
        const tabs = document.querySelectorAll('.content-sec-tab');
        tabs.forEach(t => {
            t.classList.remove('active');
            if (t.getAttribute('data-section') === AppState.activeContentSection) {
                t.classList.add('active');
            }
            
            // Add click events to section tabs
            t.onclick = () => {
                AppState.activeContentSection = t.getAttribute('data-section');
                loadPropertyContentData();
            };
        });
    } else {
        builderArea.style.display = 'none';
    }
}

window.selectPropertyForContent = function(val) {
    AppState.activePropertyIdForContent = val ? parseInt(val) : null;
    if (AppState.activePropertyIdForContent) {
        loadPropertyContentData();
    } else {
        renderContentManager();
    }
};

function loadPropertyContentData() {
    showLoader(true);
    API.request(`properties/${AppState.activePropertyIdForContent}/content`)
        .then(data => {
            AppState.activeContentData = data;
            renderContentSectionForm();
            showLoader(false);
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao carregar conteúdo.', 'danger');
        });
}

function renderContentSectionForm() {
    renderContentManager(); // Update dropdown & active tab class

    // Hide all panel areas
    const panels = document.querySelectorAll('.content-section-panel');
    panels.forEach(p => p.classList.remove('active'));

    const activePanel = document.getElementById(`sec-panel-${AppState.activeContentSection}`);
    if (activePanel) {
        activePanel.classList.add('active');
    }

    const propId = AppState.activePropertyIdForContent;
    const sec = AppState.activeContentSection;
    const secData = AppState.activeContentData[sec] || {};

    switch (sec) {
        case 'welcome':
            document.getElementById('welcome-title').value = secData.title || 'Seja bem-vindo!';
            document.getElementById('welcome-msg').value = secData.message || '';
            document.getElementById('welcome-hostname').value = secData.host_name || '';
            document.getElementById('welcome-host-photo').value = secData.host_photo || '';
            document.getElementById('welcome-signature').value = secData.signature || '';
            // Host Photo Preview
            if (secData.host_photo) {
                document.getElementById('welcome-host-photo-preview').src = secData.host_photo;
                document.getElementById('welcome-host-photo-preview').style.display = 'block';
            } else {
                document.getElementById('welcome-host-photo-preview').style.display = 'none';
            }
            break;
            
        case 'wifi':
            document.getElementById('wifi-ssid-field').value = secData.ssid || '';
            document.getElementById('wifi-pass-field').value = secData.password || '';
            document.getElementById('wifi-notes-field').value = secData.notes || '';
            break;
            
        case 'checkin':
            document.getElementById('checkin-time').value = secData.checkin_time || '14:00';
            document.getElementById('checkin-instructions').value = secData.instructions || '';
            document.getElementById('checkin-digital').value = secData.digital_lock || 'no';
            document.getElementById('checkin-code').value = secData.code || '';
            document.getElementById('checkin-gate').value = secData.gate_info || '';
            document.getElementById('checkin-parking').value = secData.parking || '';
            document.getElementById('checkin-ref').value = secData.reference_point || '';
            document.getElementById('checkin-other').value = secData.other_instructions || '';
            break;
            
        case 'checkout':
            document.getElementById('checkout-time').value = secData.checkout_time || '12:00';
            document.getElementById('checkout-key').value = secData.key_instructions || '';
            document.getElementById('checkout-trash').value = secData.trash || '';
            document.getElementById('checkout-lights').value = secData.lights || '';
            document.getElementById('checkout-ac').value = secData.ac || '';
            document.getElementById('checkout-doors').value = secData.doors || '';
            document.getElementById('checkout-windows').value = secData.windows || '';
            document.getElementById('checkout-dishes').value = secData.dishes || '';
            document.getElementById('checkout-objects').value = secData.personal_objects || '';
            document.getElementById('checkout-notice').value = secData.departure_notice || '';
            break;
            
        case 'structure':
            // Check correct checkboxes
            const selectedStruct = Array.isArray(secData) ? secData : [];
            const checkboxes = document.querySelectorAll('input[name="prop_structures"]');
            checkboxes.forEach(cb => {
                cb.checked = selectedStruct.includes(cb.value);
            });
            break;
            
        case 'how_to_use':
            renderHowToUseBuilder(Array.isArray(secData) ? secData : []);
            break;
            
        case 'rules':
            renderRulesBuilder(secData || {});
            break;
            
        case 'contacts':
            loadAndRenderContacts();
            break;
            
        case 'places':
            loadAndRenderPlaces();
            break;
    }
}

// Save Content Handler
window.saveActiveContent = function(e) {
    if (e) e.preventDefault();
    if (!AppState.activePropertyIdForContent) return;

    const sec = AppState.activeContentSection;
    let data = {};

    switch (sec) {
        case 'welcome':
            data = {
                title: document.getElementById('welcome-title').value,
                message: document.getElementById('welcome-msg').value,
                host_name: document.getElementById('welcome-hostname').value,
                host_photo: document.getElementById('welcome-host-photo').value,
                signature: document.getElementById('welcome-signature').value,
            };
            break;
            
        case 'wifi':
            data = {
                ssid: document.getElementById('wifi-ssid-field').value,
                password: document.getElementById('wifi-pass-field').value,
                notes: document.getElementById('wifi-notes-field').value
            };
            break;
            
        case 'checkin':
            data = {
                checkin_time: document.getElementById('checkin-time').value,
                instructions: document.getElementById('checkin-instructions').value,
                digital_lock: document.getElementById('checkin-digital').value,
                code: document.getElementById('checkin-code').value,
                gate_info: document.getElementById('checkin-gate').value,
                parking: document.getElementById('checkin-parking').value,
                reference_point: document.getElementById('checkin-ref').value,
                other_instructions: document.getElementById('checkin-other').value,
            };
            break;
            
        case 'checkout':
            data = {
                checkout_time: document.getElementById('checkout-time').value,
                key_instructions: document.getElementById('checkout-key').value,
                trash: document.getElementById('checkout-trash').value,
                lights: document.getElementById('checkout-lights').value,
                ac: document.getElementById('checkout-ac').value,
                doors: document.getElementById('checkout-doors').value,
                windows: document.getElementById('checkout-windows').value,
                dishes: document.getElementById('checkout-dishes').value,
                personal_objects: document.getElementById('checkout-objects').value,
                departure_notice: document.getElementById('checkout-notice').value
            };
            break;
            
        case 'structure':
            data = [];
            const checkboxes = document.querySelectorAll('input[name="prop_structures"]:checked');
            checkboxes.forEach(cb => {
                data.push(cb.value);
            });
            break;
            
        case 'how_to_use':
            data = collectHowToUseBuilderData();
            break;
            
        case 'rules':
            data = collectRulesBuilderData();
            break;
            
        default:
            return; // Contacts and Places have their own instant save APIs
    }

    showLoader(true);
    API.request(`properties/${AppState.activePropertyIdForContent}/content`, 'POST', { type: sec, data: data })
        .then(res => {
            showToast(res.message || 'Conteúdo atualizado com sucesso!');
            loadPropertyContentData();
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao salvar conteúdo.', 'danger');
        });
};

// "Como Usar" Dynamic Builder
function renderHowToUseBuilder(items) {
    const container = document.getElementById('how-to-use-builder-list');
    container.innerHTML = '';

    items.forEach((item, index) => {
        addHowToUseItemDOM(item, index);
    });

    if (items.length === 0) {
        container.innerHTML = `<p id="how-to-empty-msg" style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">Nenhum item cadastrado. Clique no botão abaixo para adicionar.</p>`;
    }
}

window.addHowToUseItem = function() {
    const emptyMsg = document.getElementById('how-to-empty-msg');
    if (emptyMsg) emptyMsg.remove();

    const container = document.getElementById('how-to-use-builder-list');
    const index = container.children.length;
    addHowToUseItemDOM({ name: '', photo: '', description: '', instructions: '' }, index);
};

function addHowToUseItemDOM(item, index) {
    const container = document.getElementById('how-to-use-builder-list');
    const card = document.createElement('div');
    card.className = 'builder-item-card';
    card.id = `howto-card-${index}`;

    card.innerHTML = `
        <div class="builder-item-header">
            <h4>Item #${index + 1}</h4>
            <button class="btn-icon-only" onclick="removeHowToUseItemDOM(${index})" title="Remover" style="color:var(--color-danger);"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="form-group-row">
            <div class="form-group">
                <label class="form-label">Nome do Item</label>
                <input type="text" class="form-control howto-input-name" value="${escapeHTML(item.name)}" placeholder="Ex: Ar-condicionado do quarto" />
            </div>
            <div class="form-group">
                <label class="form-label">Foto / Ícone</label>
                <div class="media-uploader-box">
                    <input type="text" class="form-control howto-input-photo" id="howto-photo-${index}" value="${escapeHTML(item.photo)}" placeholder="URL da foto" />
                    <button type="button" class="btn-admin btn-admin-secondary btn-sm wp-media-trigger" data-target="howto-photo-${index}">Enviar</button>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Breve Descrição</label>
            <input type="text" class="form-control howto-input-desc" value="${escapeHTML(item.description)}" placeholder="Ex: Marca LG Dual Inverter" />
        </div>
        <div class="form-group">
            <label class="form-label">Instruções de Utilização</label>
            <textarea class="form-control howto-input-inst" rows="3" placeholder="Ex: Mantenha as portas e janelas fechadas. Ao sair do quarto, lembre-se de desligar o aparelho.">${escapeHTML(item.instructions)}</textarea>
        </div>
    `;
    container.appendChild(card);
}

window.removeHowToUseItemDOM = function(index) {
    const card = document.getElementById(`howto-card-${index}`);
    if (card) card.remove();
};

function collectHowToUseBuilderData() {
    const list = [];
    const cards = document.querySelectorAll('.builder-item-card');

    cards.forEach(card => {
        const name = card.querySelector('.howto-input-name').value;
        const photo = card.querySelector('.howto-input-photo').value;
        const description = card.querySelector('.howto-input-desc').value;
        const instructions = card.querySelector('.howto-input-inst').value;

        if (name && instructions) {
            list.push({ name, photo, description, instructions });
        }
    });

    return list;
}

// Rules Manager Builder
function renderRulesBuilder(rules) {
    document.getElementById('rule-pet').value = rules.animais_estimacao || 'Permitidos sob consulta.';
    document.getElementById('rule-smoke').value = rules.fumar || 'Proibido fumar nas áreas internas.';
    document.getElementById('rule-parties').value = rules.festas || 'Não são permitidas festas ou eventos.';
    document.getElementById('rule-visitors').value = rules.visitantes || 'Não é permitida a entrada de visitantes sem aviso.';
    document.getElementById('rule-silence').value = rules.silencio || 'Horário de silêncio das 22h às 08h.';
    document.getElementById('rule-max-guests').value = rules.max_hospedes || 'Limite máximo conforme contratado na reserva.';
    document.getElementById('rule-pool').value = rules.piscina || 'Proibido vasilhames de vidro na área da piscina.';
    document.getElementById('rule-custom').value = rules.outras_regras || '';
}

function collectRulesBuilderData() {
    return {
        animais_estimacao: document.getElementById('rule-pet').value,
        fumar: document.getElementById('rule-smoke').value,
        festas: document.getElementById('rule-parties').value,
        visitantes: document.getElementById('rule-visitors').value,
        silencio: document.getElementById('rule-silence').value,
        max_hospedes: document.getElementById('rule-max-guests').value,
        piscina: document.getElementById('rule-pool').value,
        outras_regras: document.getElementById('rule-custom').value,
    };
}

// Dynamic Contacts List Builder (Instant Sub-Save)
function loadAndRenderContacts() {
    const propId = AppState.activePropertyIdForContent;
    API.request(`properties/${propId}/contacts`)
        .then(contacts => {
            const list = document.getElementById('contacts-builder-list');
            list.innerHTML = '';
            
            contacts.forEach(c => {
                const li = document.createElement('div');
                li.style.cssText = 'padding:10px 14px; background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:4px; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;';
                li.innerHTML = `
                    <div>
                        <strong style="font-size:13.5px;">${escapeHTML(c.name)}</strong> - <span style="font-size:13px; color:var(--text-secondary);">${escapeHTML(c.phone)}</span>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Tipo: ${escapeHTML(c.type)} ${c.notes ? `&bull; ${escapeHTML(c.notes)}` : ''}</div>
                    </div>
                    <button class="btn-icon-only" onclick="deleteContact(${c.id})" title="Excluir" style="color:var(--color-danger);"><i class="fa-solid fa-trash"></i></button>
                `;
                list.appendChild(li);
            });

            if (contacts.length === 0) {
                list.innerHTML = `<p style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">Nenhum contato cadastrado.</p>`;
            }
        });
}

window.addContact = function(e) {
    if (e) e.preventDefault();
    const propId = AppState.activePropertyIdForContent;

    const data = {
        name: document.getElementById('contact-name').value,
        type: document.getElementById('contact-type').value,
        phone: document.getElementById('contact-phone').value,
        notes: document.getElementById('contact-notes').value,
    };

    if (!data.name || !data.phone) {
        showToast('Nome e telefone são obrigatórios.', 'danger');
        return;
    }

    showLoader(true);
    API.request(`properties/${propId}/contacts`, 'POST', data)
        .then(res => {
            document.getElementById('form-add-contact').reset();
            showToast(res.message || 'Contato adicionado!');
            loadAndRenderContacts();
            showLoader(false);
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao adicionar contato.', 'danger');
        });
};

window.deleteContact = function(contactId) {
    if (!confirm('Deseja excluir este contato?')) return;
    const propId = AppState.activePropertyIdForContent;

    showLoader(true);
    API.request(`properties/${propId}/contacts/${contactId}`, 'DELETE')
        .then(res => {
            showToast(res.message || 'Contato excluído.');
            loadAndRenderContacts();
            showLoader(false);
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao excluir contato.', 'danger');
        });
};

// Dynamic Places List Builder (Instant Sub-Save)
function loadAndRenderPlaces() {
    const propId = AppState.activePropertyIdForContent;
    API.request(`properties/${propId}/places`)
        .then(places => {
            const list = document.getElementById('places-builder-list');
            list.innerHTML = '';
            
            places.forEach(p => {
                const li = document.createElement('div');
                li.style.cssText = 'padding:12px; background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:4px; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;';
                li.innerHTML = `
                    <div>
                        <strong style="font-size:13.5px;">${escapeHTML(p.name)}</strong> <span style="font-size:11px; padding:2px 6px; background:rgba(255,255,255,0.05); border-radius:4px; color:var(--color-secondary); margin-left:6px;">${p.type}</span>
                        ${p.description ? `<p style="font-size:12px; color:var(--text-secondary); margin:4px 0 0 0;">${escapeHTML(p.description)}</p>` : ''}
                        ${p.address ? `<div style="font-size:11px; color:var(--text-muted); margin-top:4px;"><i class="fa-solid fa-map-marker-alt"></i> ${escapeHTML(p.address)}</div>` : ''}
                    </div>
                    <button class="btn-icon-only" onclick="deletePlace(${p.id})" title="Excluir" style="color:var(--color-danger);"><i class="fa-solid fa-trash"></i></button>
                `;
                list.appendChild(li);
            });

            if (places.length === 0) {
                list.innerHTML = `<p style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">Nenhum local turístico cadastrado.</p>`;
            }
        });
}

window.addPlace = function(e) {
    if (e) e.preventDefault();
    const propId = AppState.activePropertyIdForContent;

    const data = {
        name: document.getElementById('place-name').value,
        type: document.getElementById('place-type').value,
        description: document.getElementById('place-desc').value,
        image: document.getElementById('place-image').value,
        address: document.getElementById('place-address').value,
        phone: document.getElementById('place-phone').value,
        link: document.getElementById('place-link').value,
        location: document.getElementById('place-location').value,
    };

    if (!data.name) {
        showToast('Nome do local é obrigatório.', 'danger');
        return;
    }

    showLoader(true);
    API.request(`properties/${propId}/places`, 'POST', data)
        .then(res => {
            document.getElementById('form-add-place').reset();
            // Clear preview
            document.getElementById('place-image-preview').style.display = 'none';
            showToast(res.message || 'Local adicionado com sucesso!');
            loadAndRenderPlaces();
            showLoader(false);
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao adicionar local.', 'danger');
        });
};

window.deletePlace = function(placeId) {
    if (!confirm('Deseja excluir este local da lista?')) return;
    const propId = AppState.activePropertyIdForContent;

    showLoader(true);
    API.request(`properties/${propId}/places/${placeId}`, 'DELETE')
        .then(res => {
            showToast(res.message || 'Local excluído!');
            loadAndRenderPlaces();
            showLoader(false);
        })
        .catch(err => {
            showLoader(false);
            showToast(err.message || 'Erro ao excluir local.', 'danger');
        });
};

window.editPropertyContent = function(propertyId) {
    AppState.activePropertyIdForContent = propertyId;
    AppState.activeContentSection = 'welcome';
    AppState.currentView = 'content';
    
    // Set menu active
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(m => {
        m.classList.remove('active');
        if (m.getAttribute('data-view') === 'content') {
            m.classList.add('active');
        }
    });

    loadPropertyContentData();
};

/**
 * 6. Minha Assinatura (Subscription) View
 */
function renderSubscription() {
    const sub = AppState.subscription;
    const container = document.getElementById('subscription-details-panel');

    if (!sub) {
        container.innerHTML = `<p>Erro ao carregar dados de assinatura.</p>`;
        return;
    }

    const badgeClass = sub.status === 'active' ? 'badge-active' : (sub.status === 'pending' ? 'badge-pending' : (sub.status === 'late' ? 'badge-revoked' : 'badge-expired'));
    const statusLabel = sub.status === 'active' ? 'Ativo' : (sub.status === 'pending' ? 'Pendente' : (sub.status === 'late' ? 'Atrasado' : 'Cancelado / Expirado'));
    
    const formattedEnd = sub.end_date === 'Ilimitado' ? 'Ilimitado' : formatDate(sub.end_date);

    container.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h3 style="margin:0 0 4px 0; font-size:18px;">${escapeHTML(sub.plan_name)}</h3>
                <p style="font-size:12px; color:var(--text-muted); margin:0;">ID Assinatura: ${escapeHTML(sub.subscription_id)}</p>
            </div>
            <span class="badge ${badgeClass}" style="font-size:13px; padding:6px 14px;">${statusLabel}</span>
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:6px; border:1px solid var(--border-color);">
                <span style="font-size:12px; color:var(--text-muted); display:block; margin-bottom:4px;">STATUS DO PLANO</span>
                <strong style="color:${sub.status === 'active' ? 'var(--color-primary)' : 'var(--color-secondary)'}">${statusLabel}</strong>
            </div>
            <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:6px; border:1px solid var(--border-color);">
                <span style="font-size:12px; color:var(--text-muted); display:block; margin-bottom:4px;">PRÓXIMA COBRANÇA</span>
                <strong>${formattedEnd}</strong>
            </div>
        </div>

        <div class="premium-alert-banner">
            <i class="fa-solid fa-circle-check" style="color:var(--color-primary);"></i>
            <div class="premium-banner-text">
                <h4>Integração WooCommerce Ativa</h4>
                <p>O status da sua assinatura é controlado automaticamente pelos seus pagamentos no WooCommerce.</p>
            </div>
        </div>
    `;
}

/**
 * WhatsApp Dispatcher
 */
window.sendWhatsapp = function(guestName, guestPhone, propertyName, token) {
    if (!guestPhone) {
        showToast('Telefone do hóspede não cadastrado.', 'danger');
        return;
    }

    const guideUrl = pradoWelcomeData.use_pretty_links 
        ? `${pradoWelcomeData.home_url}g/${token}`
        : `${pradoWelcomeData.home_url}?g=${token}`;
    const guestFirstName = guestName.split(' ')[0] || guestName;

    let text = `Olá, *${guestFirstName}*! 👋\n\n`;
    text += `Seja muito bem-vindo!\n\n`;
    text += `Preparamos um *Guia Digital* exclusivo para sua estadia no *${propertyName}* com todas as informações importantes (Wi-Fi, regras, dicas locais, etc).\n\n`;
    text += `👉 *[ ABRIR MEU GUIA ]*\n${guideUrl}`;

    // Clean phone number
    const cleanPhone = guestPhone.replace(/\D/g, '');
    const ddi = cleanPhone.length <= 11 ? '55' : ''; // Fallback for Brazil DDI

    const waUrl = `https://api.whatsapp.com/send?phone=${ddi}${cleanPhone}&text=${encodeURIComponent(text)}`;
    window.open(waUrl, '_blank');
};

/**
 * QR Code Generator Modal Handler
 */
let currentQrCodeInstance = null;

window.openQrModal = function(token) {
    const guideUrl = pradoWelcomeData.use_pretty_links 
        ? `${pradoWelcomeData.home_url}g/${token}`
        : `${pradoWelcomeData.home_url}?g=${token}`;
    
    document.getElementById('qr-link-placeholder').innerText = guideUrl;
    
    // Clear canvas
    const canvasContainer = document.getElementById('qr-code-canvas');
    canvasContainer.innerHTML = '';

    openModal('modal-qrcode');

    // Wait for DOM to adjust and render QR code using David Shim vendor library enqueued
    setTimeout(() => {
        if (typeof QRCode !== 'undefined') {
            currentQrCodeInstance = new QRCode(canvasContainer, {
                text: guideUrl,
                width: 220,
                height: 220,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            canvasContainer.innerHTML = `<p style="color:red; font-size:12px;">Erro: Biblioteca QR Code não carregada.</p>`;
        }
    }, 100);
};

window.downloadQrCode = function() {
    const canvasContainer = document.getElementById('qr-code-canvas');
    const img = canvasContainer.querySelector('img');
    if (!img) return;

    const link = document.createElement('a');
    link.href = img.src;
    link.download = 'qrcode-guia-hospedagem.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

window.printQrCode = function() {
    const canvasContainer = document.getElementById('qr-code-canvas');
    const img = canvasContainer.querySelector('img');
    if (!img) return;

    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Imprimir QR Code</title>
            <style>
                body { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 90vh; font-family: sans-serif; }
                img { width: 300px; height: 300px; border: 1px solid #ccc; padding: 10px; border-radius: 8px; }
                h2 { margin-bottom: 5px; }
                p { color: #666; margin-top: 5px; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <h2>Guia Digital de Hospedagem</h2>
            <img src="${img.src}" />
            <p>Escaneie para acessar o manual da casa.</p>
        </body>
        </html>
    `);
    win.document.close();
};

/**
 * Modals Helper Methods
 */
function initModals() {
    const closeButtons = document.querySelectorAll('.btn-modal-close, .btn-modal-cancel');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.prado-modal');
            if (modal) closeModal(modal.id);
        });
    });

    // Close on backdrop click
    const overlays = document.querySelectorAll('.modal-overlay');
    overlays.forEach(ol => {
        ol.addEventListener('click', () => {
            const modal = ol.closest('.prado-modal');
            if (modal) closeModal(modal.id);
        });
    });
}

window.openModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
};

window.closeModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
};

/**
 * WordPress Media Library Uploader Hooks integration
 */
function initMediaUploaders() {
    document.body.addEventListener('click', e => {
        const trigger = e.target.closest('.wp-media-trigger');
        if (!trigger) return;

        e.preventDefault();

        // Check if wp.media exists
        if (typeof wp === 'undefined' || !wp.media) {
            alert('WordPress Media Library não disponível.');
            return;
        }

        const targetFieldId = trigger.getAttribute('data-target');
        const inputField = document.getElementById(targetFieldId);
        const previewImg = document.getElementById(`${targetFieldId}-preview`);

        const mediaUploader = wp.media({
            title: 'Selecionar Imagem',
            button: {
                text: 'Usar Imagem'
            },
            multiple: false
        });

        mediaUploader.on('select', () => {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            if (inputField) {
                inputField.value = attachment.url;
                // Dispatch change event
                inputField.dispatchEvent(new Event('change'));
            }
            if (previewImg) {
                previewImg.src = attachment.url;
                previewImg.style.display = 'block';
            }
        });

        mediaUploader.open();
    });

    // Hook inline input changes to show previews instantly
    const mediaInputs = ['prop-photo-main', 'prop-logo', 'welcome-host-photo', 'place-image'];
    mediaInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('change', () => {
                const preview = document.getElementById(`${id}-preview`);
                if (preview) {
                    if (input.value) {
                        preview.src = input.value;
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                }
            });
        }
    });
}

/**
 * Format Date Helper
 */
function formatDate(dateStr) {
    if (!dateStr || dateStr === 'Ilimitado') return dateStr;
    // Format YYYY-MM-DD or mysql timestamp into DD/MM/YYYY
    const datePart = dateStr.split(' ')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
}

/**
 * HTML Escaper Helper
 */
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}
