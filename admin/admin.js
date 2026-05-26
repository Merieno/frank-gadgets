/* ============================================
   Frank Gadgets — Admin Panel JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Auto-dismiss alerts ──────────────────────────────────────
    document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 3500);
    });

    // ── Confirm delete links ─────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // ── Image upload preview ─────────────────────────────────────
    const imageInput = document.getElementById('images');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            previewImages(this, 'preview');
        });
    }

    const newImageInput = document.getElementById('new_images');
    if (newImageInput) {
        newImageInput.addEventListener('change', function () {
            previewImages(this, 'new-preview');
        });
    }

    // ── Drag & drop upload ───────────────────────────────────────
    const uploadArea = document.querySelector('.upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', function () {
            uploadArea.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const input = document.getElementById('images');
            if (input) {
                input.files = e.dataTransfer.files;
                previewImages(input, 'preview');
            }
        });
    }

    // ── Auto-generate slug from product name ─────────────────────
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput && document.querySelector('input[name="slug"]')) {
        nameInput.addEventListener('input', function () {
            const slug = this.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            document.querySelector('input[name="slug"]').value = slug;
        });
    }

    // ── Price formatter display ──────────────────────────────────
    document.querySelectorAll('input[name="price"], input[name="old_price"]').forEach(function (input) {
        input.addEventListener('blur', function () {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });

    // ── Select all checkboxes ────────────────────────────────────
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // ── Sidebar active link highlight ────────────────────────────
    const currentPath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && href === currentPath) {
            link.classList.add('active');
        }
    });

    // ── Table row click to navigate ──────────────────────────────
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function (e) {
            if (!e.target.closest('a, button, select, input, form')) {
                window.location.href = row.dataset.href;
            }
        });
    });

    // ── Toast notification ───────────────────────────────────────
    window.showAdminToast = function (msg, type) {
        type = type || 'success';
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 right-6 z-50 px-5 py-3 rounded-2xl shadow-2xl text-sm font-semibold text-white transition-all duration-300 translate-y-20 opacity-0';
        toast.style.background = type === 'success' ? '#16a34a' : '#dc2626';
        toast.textContent = msg;
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.remove('translate-y-20', 'opacity-0');
        });

        setTimeout(function () {
            toast.classList.add('translate-y-20', 'opacity-0');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    };

    // ── Inline status update feedback ────────────────────────────
    document.querySelectorAll('select[name="status"][data-ajax]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const form = sel.closest('form');
            if (!form) return;
            const data = new FormData(form);
            fetch(window.location.href, { method: 'POST', body: data })
                .then(function () {
                    showAdminToast('Status updated!');
                })
                .catch(function () {
                    showAdminToast('Update failed', 'error');
                });
        });
    });

    // ── Smooth scroll to errors ──────────────────────────────────
    const firstError = document.querySelector('.alert-error, .form-input.error');
    if (firstError) {
        setTimeout(function () {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }

    // ── Character counter for textarea ───────────────────────────
    document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
        const counter = document.createElement('p');
        counter.className = 'text-xs text-gray-400 text-right mt-1';
        counter.textContent = ta.value.length + ' / ' + ta.maxLength;
        ta.parentNode.appendChild(counter);
        ta.addEventListener('input', function () {
            counter.textContent = ta.value.length + ' / ' + ta.maxLength;
        });
    });

});

// ── Image preview function ────────────────────────────────────────
function previewImages(input, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';

    Array.from(input.files).forEach(function (file, i) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const wrapper = document.createElement('div');
            wrapper.className = 'img-preview-item' + (i === 0 ? ' main' : '');

            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = file.name;
            wrapper.appendChild(img);

            if (i === 0) {
                const tag = document.createElement('span');
                tag.className = 'main-tag';
                tag.textContent = 'Main';
                wrapper.appendChild(tag);
            }

            container.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });
}

// ── Category edit modal ───────────────────────────────────────────
function editCat(id, name, icon, sort) {
    const modal = document.getElementById('edit-modal');
    if (!modal) return;
    document.getElementById('edit-cat-id').value   = id;
    document.getElementById('edit-cat-name').value = name;
    document.getElementById('edit-cat-icon').value = icon;
    document.getElementById('edit-cat-sort').value = sort;
    modal.classList.remove('hidden');
}

// ── Confirm action ────────────────────────────────────────────────
function confirmAction(msg) {
    return confirm(msg || 'Are you sure?');
}

// ── Format currency display ───────────────────────────────────────
function formatNaira(amount) {
    return '₦' + parseFloat(amount).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}