<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : components/footer.php
// PURPOSE   : Site-wide HTML footer shell.
//             Included at the BOTTOM of every protected PHP page via:
//               require_once __DIR__ . '/../components/footer.php';
//
//             This file CLOSES:
//               • </main>        (opened by header.php)
//               • <footer>...</footer>
//               • </body> and </html>
//
//             Any page-specific JavaScript can be injected by setting the
//             $extra_footer_js variable before including this file.
//
// OBJECTIVE : Obj 4 – Consistent responsive UI; Obj 5 – Quick links to
//             invoice and report pages in footer navigation.
// =============================================================================
?>

</main><!-- END .page-wrapper (opened in header.php) -->


<!-- =============================================================================
     SITE FOOTER
     Contains brand info, quick links (role-aware), and copyright.
     Quick links to reports and invoices align with Obj 5.
     ============================================================================= -->
<footer class="site-footer" role="contentinfo">
    <div class="container">

        <div class="footer-grid">

            <!-- ── Column 1: Brand ──────────────────────────────── -->
            <div>
                <!-- Brand logo (mirrors navbar brand) -->
                <a href="<?= BASE_URL ?>/<?= (($_SESSION['role'] ?? '') === 'farmer') ? 'farmer/dashboard.php' : 'customer/catalog.php' ?>"
                   class="navbar-brand" style="margin-bottom:0;">
                    <div class="brand-icon" aria-hidden="true">🌿</div>
                    GreenThumb
                </a>

                <p class="footer-brand-text">
                    Sri Lanka's premier online rubber plant nursery. We connect plant
                    lovers with quality-certified Ficus Elastica varieties — from classic
                    Robusta to rare Abidjan cultivars.
                </p>

                <!-- Contact info -->
                <div style="margin-top:var(--space-md);display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.85rem;color:var(--clr-text-muted);">
                        📍 No 12,   Okkampitiya Road,Monaragala,Sri Lanka
                    </span>
                    <span style="font-size:0.85rem;color:var(--clr-text-muted);">
                        📞 +94 77 123 4567
                    </span>
                    <span style="font-size:0.85rem;color:var(--clr-text-muted);">
                        ✉️ info@greenthumb.com
                    </span>
                </div>
            </div>

            <!-- ── Column 2: Quick Links (role-aware) ─────────── -->
            <div>
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-links">

                    <?php if (($_SESSION['role'] ?? '') === 'farmer'): ?>
                    <!-- Farmer quick links (Obj 3 & Obj 5) -->
                    <li><a href="<?= BASE_URL ?>/farmer/dashboard.php">📊 Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/farmer/manage_plants.php">🌱 Manage Plants</a></li>
                    <li><a href="<?= BASE_URL ?>/farmer/orders.php">📦 Manage Orders</a></li>
                    <li>
                        <!-- Income report link — Obj 5 (Invoice & Reports) -->
                        <a href="<?= BASE_URL ?>/farmer/income_report.php">📈 Income Report</a>
                    </li>

                    <?php elseif (($_SESSION['role'] ?? '') === 'customer'): ?>
                    <!-- Customer quick links (Obj 1 & Obj 2) -->
                    <li><a href="<?= BASE_URL ?>/customer/catalog.php">🌿 Plant Catalog</a></li>
                    <li><a href="<?= BASE_URL ?>/customer/my_orders.php">📋 My Orders</a></li>

                    <?php else: ?>
                    <!-- Guest links -->
                    <li><a href="<?= BASE_URL ?>/login.html">🔐 Login</a></li>
                    <li><a href="<?= BASE_URL ?>/register.html">🌱 Register</a></li>
                    <?php endif; ?>

                </ul>
            </div>

            <!-- ── Column 3: Plant Categories ────────────────────── -->
            <div>
                <h3 class="footer-heading">Plant Types</h3>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>/customer/catalog.php?cat=1">🏠 Indoor Varieties</a></li>
                    <li><a href="<?= BASE_URL ?>/customer/catalog.php?cat=2">🌳 Outdoor Varieties</a></li>
                    <li><a href="<?= BASE_URL ?>/customer/catalog.php?cat=3">💎 Rare Cultivars</a></li>
                    <li><a href="<?= BASE_URL ?>/customer/catalog.php?cat=4">🌱 Starter Plants</a></li>
                </ul>
            </div>

        </div><!-- end .footer-grid -->

        <!-- ── Footer Bottom Bar ──────────────────────────────────── -->
        <div class="footer-bottom">
            <span>
                &copy; <?= date('Y') ?> GreenThumb Rubber Plant Nursery. All rights reserved.
            </span>
            <span>
                🔒 Secured with PHP Prepared Statements &amp; bcrypt encryption
            </span>
        </div>

    </div>
</footer>
<!-- END SITE FOOTER -->


<!-- =============================================================================
     GLOBAL JAVASCRIPT: Page utilities
     ============================================================================= -->
<script>
'use strict';

// ── Auto-dismiss alerts after 6 seconds ───────────────────────────────────────
// Any .alert element rendered by a page will fade out after 6 seconds.
// This prevents stale success/error messages from confusing users.
(function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
            alert.style.opacity = '0';
            alert.style.maxHeight = '0';
            alert.style.overflow = 'hidden';
            setTimeout(function () { alert.remove(); }, 500);
        }, 6000);
    });
})();

// ── Confirm delete actions ───────────────────────────────────────────────────
// Any link or button with class .confirm-delete shows a confirmation dialog.
// This prevents accidental deletion of plant records or orders (Obj 3).
document.querySelectorAll('.confirm-delete').forEach(function (el) {
    el.addEventListener('click', function (e) {
        const target = this.dataset.target || 'this item';
        if (!confirm('Are you sure you want to delete ' + target + '? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});

// ── Scroll-to-top button ─────────────────────────────────────────────────────
(function () {
    const btn = document.createElement('button');
    btn.id = 'scrollTopBtn';
    btn.setAttribute('aria-label', 'Scroll back to top');
    btn.innerHTML = '↑';
    btn.style.cssText = `
        position: fixed; bottom: 28px; right: 28px;
        width: 44px; height: 44px; border-radius: 50%;
        background: var(--clr-primary); color: #071407;
        font-size: 1.1rem; font-weight: 800;
        border: none; cursor: pointer;
        box-shadow: 0 4px 20px rgba(74,222,128,0.35);
        opacity: 0; transition: opacity 0.3s ease;
        z-index: 500; display: grid; place-items: center;
    `;
    document.body.appendChild(btn);

    window.addEventListener('scroll', function () {
        btn.style.opacity = window.scrollY > 400 ? '1' : '0';
        btn.style.pointerEvents = window.scrollY > 400 ? 'auto' : 'none';
    });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>

<!-- Page-specific scripts injected by individual pages if needed -->
<?= $extra_footer_js ?? '' ?>

</body>
</html>
