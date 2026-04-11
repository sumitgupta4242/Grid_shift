// devices.js – IoT device toggle and add logic
'use strict';

const API_DEVICE = typeof API !== 'undefined' ? API : '/final_year_project/api/device_toggle.php';

// ── Toast notification ────────────────────────────────────
function toast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `
        position:fixed;bottom:24px;right:24px;z-index:9999;
        background:${type==='success'?'rgba(80,200,120,0.15)':'rgba(239,68,68,0.15)'};
        border:1px solid ${type==='success'?'rgba(80,200,120,0.4)':'rgba(239,68,68,0.4)'};
        color:${type==='success'?'#86efac':'#fca5a5'};
        padding:12px 20px;border-radius:10px;font-size:.85rem;font-family:inherit;
        box-shadow:0 8px 24px rgba(0,0,0,0.4);animation:fadeIn .3s ease;
        display:flex;align-items:center;gap:8px;max-width:300px;
    `;
    t.innerHTML = `<i class="fas fa-${type==='success'?'circle-check':'circle-xmark'}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// ── Device toggle ─────────────────────────────────────────
document.addEventListener('change', async function(e) {
    if (!e.target.classList.contains('device-toggle')) return;
    const id      = e.target.dataset.id;
    const checked = e.target.checked;
    const card    = document.getElementById('device-' + id);

    e.target.disabled = true;
    try {
        const fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('id', id);
        const res  = await fetch(API_DEVICE, { method:'POST', body:fd });
        const data = await res.json();

        if (data.success) {
            card.classList.toggle('on',  data.is_on === 1);
            card.classList.toggle('off', data.is_on === 0);
            // Update status text
            const statusEl = card.querySelector('[class]');
            toast(data.is_on ? `${data.device} turned ON` : `${data.device} turned OFF`, 'success');
            updateLoadCounter();
        } else {
            e.target.checked = !checked; // revert
            toast(data.message || 'Toggle failed', 'error');
        }
    } catch(err) {
        e.target.checked = !checked;
        toast('Network error', 'error');
    }
    e.target.disabled = false;
});

// ── Delete device ─────────────────────────────────────────
window.deleteDevice = async function(id) {
    if (!confirm('Remove this device from your system?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const res  = await fetch(API_DEVICE, { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById('device-' + id);
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity = '0'; card.style.transform = 'scale(0.9)';
            setTimeout(() => card.remove(), 300);
            toast('Device removed', 'success');
            updateLoadCounter();
        } else {
            toast(data.message || 'Delete failed', 'error');
        }
    } catch(e) { toast('Network error','error'); }
};

// ── Add device form ───────────────────────────────────────
const addForm = document.getElementById('add-device-form');
if (addForm) {
    addForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd  = new FormData(addForm);
        fd.append('action','add');
        const btn = addForm.querySelector('button[type=submit]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        btn.disabled  = true;
        try {
            const res  = await fetch(API_DEVICE, { method:'POST', body:fd });
            const data = await res.json();
            if (data.success) {
                toast('Device added! Refreshing...', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                toast(data.message || 'Failed to add device', 'error');
            }
        } catch(err) { toast('Network error','error'); }
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Device';
        btn.disabled  = false;
    });
}

// ── Update load counter ───────────────────────────────────
async function updateLoadCounter() {
    try {
        const res  = await fetch(API_DEVICE + '?action=load');
        const data = await res.json();
        const el   = document.getElementById('total-load');
        if (el) el.textContent = parseFloat(data.load_kw).toFixed(2);

        const activeCards = document.querySelectorAll('.device-card.on').length;
        const el2 = document.getElementById('active-count');
        if (el2) el2.textContent = activeCards;
    } catch(e) {}
}
