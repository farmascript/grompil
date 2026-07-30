</main> <!-- Einde van main-content uit header.php -->

<footer class="footer mt-auto py-4 bg-body border-top">
    <div class="container text-center">
        <div class="row align-items-center">
            <div class="col-md-6 text-md-start">
                <span class="text-body-secondary">&copy; <?= date('Y') ?> Uw Applicatie.</span>
            </div>
            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <!-- Knop om de keuzes te resetten (Zet de banner weer aan via ?ck=n) -->
                <a href="?ck=n" class="btn btn-xs btn-outline-danger px-2 py-1 small" style="font-size: 0.75rem;">
                    Cookievoorkeuren Resetten
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- HIER WORDT DE BANNER INGEVOEGD INDIEN NODIG -->
<?php if (!empty($__cookieConsentFooter)): ?>
    <?= $__cookieConsentFooter ?>
<?php endif; ?>

<!-- Bootstrap 5.3.8 JS Bundle -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
