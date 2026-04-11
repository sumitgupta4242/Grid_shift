    </div><!-- end .page-content -->
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
