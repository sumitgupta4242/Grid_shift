    </div><!-- end .page-content -->

    <footer class="app-footer">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?= date('Y') ?> <strong>Grid shift</strong>. All rights reserved.</p>
                <p class="dev-by">Developed by <span class="highlight">Sumit Gupta</span></p>
            </div>
            <div class="footer-right">
                <div class="footer-contact">
                    <a href="tel:8354944670" title="Call me"><i class="fas fa-phone"></i> 8354944670</a>
                    <a href="mailto:stgupta424242@gmail.com" title="Email me"><i class="fas fa-envelope"></i> stgupta424242@gmail.com</a>
                    <a href="https://www.linkedin.com/in/sumit-gupta-877666253/" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i> LinkedIn</a>
                </div>
            </div>
        </div>
    </footer>
</div><!-- end .main-content -->
</div><!-- end .app-shell -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
<script>
// Sidebar responsive toggle
if (window.innerWidth <= 700) {
    document.getElementById('sidebar-toggle') &&
        (document.getElementById('sidebar-toggle').style.display = 'flex');
}
// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e) {
    const sb = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    if (sb && sb.classList.contains('open') && !sb.contains(e.target) && e.target !== toggle) {
        sb.classList.remove('open');
    }
});
</script>
</body>
</html>
