/* ============================================
   Frank Gadgets — Main JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile menu toggle ───────────────────────────────────────
    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobile-menu');
    if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', function () {
            mobileMenu.classList.toggle('hidden');
        });
        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!menuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
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

    // ── Scroll reveal ────────────────────────────────────────────
    const revealEls = document.querySelectorAll('.reveal');
    if (revealEls.length) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealEls.forEach(function (el) {
            revealObserver.observe(el);
        });
    }

    // ── Search bar: Enter key ────────────────────────────────────
    document.querySelectorAll('#searchInput, .search-bar').forEach(function (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && this.value.trim()) {
                window.location.href = 'shop.php?q=' + encodeURIComponent(this.value.trim());
            }
        });
    });

    // ── Cart badge update ────────────────────────────────────────
    window.updateCartBadge = function (count) {
        let badge = document.querySelector('.cart-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else {
                badge = document.createElement('span');
                badge.className = 'cart-badge';
                badge.textContent = count;
                const cartLink = document.querySelector('a[href="cart.php"]');
                if (cartLink) {
                    cartLink.style.position = 'relative';
                    cartLink.appendChild(badge);
                }
            }
        } else {
            if (badge) badge.remove();
        }
    };

    // ── Toast notification ───────────────────────────────────────
    window.showToast = function (msg, isError) {
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toast-msg');
        if (!toast) return;

        if (toastMsg) toastMsg.textContent = msg;
        toast.classList.remove('translate-y-20', 'opacity-0');
        toast.classList.add('show');

        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(function () {
            toast.classList.add('translate-y-20', 'opacity-0');
            toast.classList.remove('show');
        }, 3000);
    };

    // ── Add to cart (global) ─────────────────────────────────────
    window.addToCart = function (productId, btn, qty) {
        qty = qty || 1;
        const original = btn.innerHTML;
        btn.innerHTML = '<svg class="w-4 h-4 spin inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>';
        btn.disabled = true;

        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=add&product_id=' + productId + '&quantity=' + qty
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showToast('Added to cart!');
                updateCartBadge(data.cart_count);
            } else {
                showToast(data.message || 'Something went wrong', true);
            }
        })
        .catch(function () {
            showToast('Network error — please try again.', true);
        })
        .finally(function () {
            btn.innerHTML = original;
            btn.disabled = false;
        });
    };

    // ── Lazy load images ─────────────────────────────────────────
    if ('IntersectionObserver' in window) {
        const lazyImgs = document.querySelectorAll('img[loading="lazy"]');
        const imgObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    imgObserver.unobserve(img);
                }
            });
        });
        lazyImgs.forEach(function (img) { imgObserver.observe(img); });
    }

    // ── Smooth anchor scrolling ──────────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Back to top button ───────────────────────────────────────
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            backToTop.classList.toggle('hidden', window.scrollY < 400);
        }, { passive: true });
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ── Image fallback ───────────────────────────────────────────
    document.querySelectorAll('img[data-fallback]').forEach(function (img) {
        img.addEventListener('error', function () {
            this.src = this.dataset.fallback;
        });
    });

});

/* ── Utility: format price ───────────────────────────────────────── */
function formatPrice(amount) {
    return '₦' + parseFloat(amount).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/* ── Utility: debounce ───────────────────────────────────────────── */
function debounce(fn, delay) {
    let timer;
    return function () {
        const args = arguments;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(this, args); }, delay);
    };
}