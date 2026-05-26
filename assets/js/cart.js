/* ============================================
   Frank Gadgets — Cart JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Format price helper ──────────────────────────────────────
    function fmt(n) {
        return '₦' + parseFloat(n).toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ── Update cart badge in navbar ──────────────────────────────
    function updateBadge(count) {
        let badge = document.getElementById('cart-badge');
        if (!badge) badge = document.querySelector('.cart-badge');

        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else {
                badge = document.createElement('span');
                badge.id = 'cart-badge';
                badge.className = 'cart-badge';
                badge.textContent = count;
                const cartLink = document.querySelector('a[href="cart.php"]');
                if (cartLink) cartLink.appendChild(badge);
            }
        } else {
            if (badge) badge.remove();
        }
    }

    // ── Update quantity ──────────────────────────────────────────
    window.updateQty = function (cartId, delta, currentQty, maxStock) {
        const newQty = Math.max(1, Math.min(maxStock || 999, currentQty + delta));
        if (newQty === currentQty) return;

        const qtyDisplay  = document.getElementById('qty-' + cartId);
        const subtotalEl  = document.getElementById('subtotal-' + cartId);
        const totalEl     = document.getElementById('cart-total');
        const subtotalNav = document.getElementById('cart-subtotal');

        // Optimistic UI update
        if (qtyDisplay) qtyDisplay.textContent = newQty;

        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update&cart_id=' + cartId + '&quantity=' + newQty
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (subtotalEl)  subtotalEl.textContent  = fmt(data.subtotal);
                if (totalEl)     totalEl.textContent     = fmt(data.cart_total);
                if (subtotalNav) subtotalNav.textContent = fmt(data.subtotal);
                updateBadge(data.cart_count);

                // Update all onclick references for this item's buttons
                document.querySelectorAll('[onclick*="updateQty(' + cartId + ',"]').forEach(function (btn) {
                    const onclick = btn.getAttribute('onclick');
                    const updated = onclick.replace(
                        /updateQty\(\s*\d+\s*,\s*(-?\d+)\s*,\s*\d+/,
                        'updateQty(' + cartId + ', $1, ' + newQty
                    );
                    btn.setAttribute('onclick', updated);
                });
            } else {
                // Revert on failure
                if (qtyDisplay) qtyDisplay.textContent = currentQty;
            }
        })
        .catch(function () {
            if (qtyDisplay) qtyDisplay.textContent = currentQty;
        });
    };

    // ── Remove item ──────────────────────────────────────────────
    window.removeItem = function (cartId) {
        const el = document.getElementById('item-' + cartId);
        if (!el) return;

        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        el.style.opacity    = '0';
        el.style.transform  = 'translateX(20px)';

        setTimeout(function () {
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=remove&cart_id=' + cartId
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                el.remove();

                const totalEl = document.getElementById('cart-total');
                if (totalEl) totalEl.textContent = fmt(data.cart_total);

                updateBadge(data.cart_count);

                // Update item count in header
                const remaining = document.querySelectorAll('.cart-item').length;
                const countEl = document.querySelector('[data-cart-count]');
                if (countEl) {
                    countEl.textContent = remaining + ' item' + (remaining !== 1 ? 's' : '');
                }

                // If cart is now empty, reload to show empty state
                if (data.cart_count === 0) {
                    setTimeout(function () { location.reload(); }, 300);
                }
            })
            .catch(function () {
                // Restore item on error
                el.style.opacity   = '1';
                el.style.transform = 'none';
            });
        }, 300);
    };

    // ── Keyboard shortcut: Enter on qty input ────────────────────
    document.querySelectorAll('[id^="qty-"]').forEach(function (el) {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') el.blur();
        });
    });

    // ── Proceed to checkout button loading state ──────────────────
    const checkoutBtn = document.querySelector('a[href="checkout.php"]');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            this.textContent = 'Loading...';
        });
    }

    // ── Navbar scroll shadow ─────────────────────────────────────
    const nav = document.querySelector('nav');
    if (nav) {
        window.addEventListener('scroll', function () {
            nav.style.boxShadow = window.scrollY > 10
                ? '0 1px 20px rgba(0,0,0,0.08)'
                : 'none';
        }, { passive: true });
    }

});