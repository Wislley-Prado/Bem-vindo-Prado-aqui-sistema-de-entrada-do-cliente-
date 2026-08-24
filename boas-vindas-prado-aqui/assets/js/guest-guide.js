/**
 * Boas-Vindas Prado Aqui - Guest Guide JS
 * Vanilla JS SPA router, panels, overlays and copy utilities.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initCopyWifi();
    initOverlaySheets();
    initHowToAccordions();
    initRegionFilters();
    initCheckoutWhatsapp();
});

/**
 * 1. Navigation Panel Tabs Switching
 */
function initTabs() {
    const tabs = document.querySelectorAll('.nav-tab');
    const panels = document.querySelectorAll('.guide-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-target');

            // Set active tab
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Set active panel
            panels.forEach(panel => {
                panel.classList.remove('active');
                if (panel.getAttribute('id') === target) {
                    panel.classList.add('active');
                    // Scroll to top of panels area
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    });
}

/**
 * 2. Copy Wi-Fi Password to Clipboard
 */
function initCopyWifi() {
    const btn = document.getElementById('btn-copy-wifi');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const ssid = document.getElementById('wifi-ssid').innerText;
        const pass = document.getElementById('wifi-pass').innerText;
        
        // Write to clipboard
        navigator.clipboard.writeText(pass).then(() => {
            showToast('Senha copiada para a área de transferência!');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            // Fallback for older browsers
            const el = document.createElement('textarea');
            el.value = pass;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            showToast('Senha copiada!');
        });
    });
}

/**
 * Helper: toast messages
 */
function showToast(message) {
    let toast = document.querySelector('.toast-msg');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-msg';
        document.body.appendChild(toast);
    }
    toast.innerText = message;
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}

/**
 * 3. Overlay Sheets Control (Checkin, Rules, Contacts)
 */
function initOverlaySheets() {
    const triggers = [
        { btnId: 'btn-show-checkin', sheetId: 'sheet-checkin' },
        { btnId: 'btn-show-rules', sheetId: 'sheet-rules' },
        { btnId: 'btn-show-contacts', sheetId: 'sheet-contacts' }
    ];

    triggers.forEach(t => {
        const btn = document.getElementById(t.btnId);
        const sheet = document.getElementById(t.sheetId);

        if (!btn || !sheet) return;

        btn.addEventListener('click', () => {
            sheet.classList.add('active');
            document.body.style.overflow = 'hidden'; // Lock background scrolling
        });

        // Close triggers
        const closeBtn = sheet.querySelector('.btn-close-sheet');
        const backdrop = sheet.querySelector('.sheet-backdrop');

        const closeSheet = () => {
            sheet.classList.remove('active');
            document.body.style.overflow = ''; // Restore scroll
        };

        if (closeBtn) closeBtn.addEventListener('click', closeSheet);
        if (backdrop) backdrop.addEventListener('click', closeSheet);
    });
}

/**
 * 4. How To Use Accordion Toggles
 */
function initHowToAccordions() {
    const headers = document.querySelectorAll('.how-to-header');

    headers.forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.getAttribute('data-toggle');
            const body = document.getElementById(targetId);
            const arrow = header.querySelector('.toggle-arrow i');

            if (!body) return;

            const isVisible = window.getComputedStyle(body).display !== 'none';

            if (isVisible) {
                body.style.display = 'none';
                arrow.className = 'fa-solid fa-chevron-down';
            } else {
                body.style.display = 'block';
                arrow.className = 'fa-solid fa-chevron-up';
            }
        });
    });
}

/**
 * 5. Category Filters for Region/Tourism Items
 */
function initRegionFilters() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    const cards = document.querySelectorAll('.place-card');

    if (filterTabs.length === 0) return;

    filterTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const filter = tab.getAttribute('data-filter');

            // Update active tab styles
            filterTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Filter place cards
            cards.forEach(card => {
                const category = card.getAttribute('data-category');
                if (filter === 'all' || category === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
}

/**
 * 6. Checkout Checkout list and notify host via WhatsApp
 */
function initCheckoutWhatsapp() {
    const notifyBtn = document.getElementById('btn-notify-checkout');
    if (!notifyBtn) return;

    notifyBtn.addEventListener('click', () => {
        // Collect checked and unchecked items
        const checkboxes = document.querySelectorAll('input[name="checkout_step"]');
        const checkedList = [];
        const uncheckedList = [];

        checkboxes.forEach(cb => {
            const labelText = cb.parentElement.querySelector('.checklist-text').innerText;
            // Extract the core title (up to the colon)
            const cleanText = labelText.split(':')[0] || labelText;

            if (cb.checked) {
                checkedList.push(`✅ ${cleanText}`);
            } else {
                uncheckedList.push(`❌ ${cleanText}`);
            }
        });

        // Get config details localized from template PHP
        const hostName = guestGuideConfig.hostName || 'Anfitrião';
        const hostPhone = guestGuideConfig.hostPhone || '';
        const hostWhatsapp = guestGuideConfig.hostWhatsapp || '';
        const guestName = guestGuideConfig.guestName || 'Hóspede';
        const propertyName = guestGuideConfig.propertyName || 'Hospedagem';

        // Prepare message
        let text = `Olá, *${hostName}*! 👋\n\n`;
        text += `Aqui é o *${guestName}*. Acabamos de realizar o checkout do imóvel *${propertyName}*.\n\n`;
        
        if (checkedList.length > 0) {
            text += `*Procedimentos concluídos:*\n${checkedList.join('\n')}\n\n`;
        }

        if (uncheckedList.length > 0) {
            text += `*Pendências / Não verificados:*\n${uncheckedList.join('\n')}\n\n`;
        }

        text += `Obrigado por nos receber, a hospedagem foi incrível! até a próxima! 🏨✨`;

        // Format whatsapp url
        const targetNumber = hostWhatsapp ? hostWhatsapp : hostPhone;
        // Clean digits
        const cleanNumber = targetNumber.replace(/\D/g, '');
        // Standard DDI fallback for Brazil if missing 55
        const ddi = cleanNumber.length <= 11 ? '55' : '';
        
        const waUrl = `https://api.whatsapp.com/send?phone=${ddi}${cleanNumber}&text=${encodeURIComponent(text)}`;
        
        // Redirect to WhatsApp
        window.open(waUrl, '_blank');
    });
}
