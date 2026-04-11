<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['mock_notification'])):
    $mock = $_SESSION['mock_notification'];
?>
<div id="mock-toast" style="position:fixed; bottom:20px; right:20px; width:340px; background:var(--card-bg, #1a231e); border:1px solid var(--accent, #50c878); border-radius:12px; padding:16px; box-shadow:0 10px 40px rgba(0,0,0,0.6); z-index:9999; animation:slideUp 0.4s ease forwards;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
        <div style="font-weight:700; color:var(--text-primary, #fff);">
            <i class="fas <?= $mock['type'] === 'email' ? 'fa-envelope' : 'fa-comment-sms' ?>" style="color:var(--accent, #50c878);"></i> 
            Mock <?= ucfirst($mock['type']) ?> Sent
        </div>
        <button onclick="document.getElementById('mock-toast').remove()" style="background:none;border:none;color:var(--text-muted,#8ba995);cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>
    <div style="font-size:0.8rem; color:var(--text-muted,#8ba995); margin-bottom:6px;"><strong>To:</strong> <?= htmlspecialchars($mock['to']) ?></div>
    <div style="font-size:0.8rem; color:var(--text-muted,#8ba995); margin-bottom:10px;"><strong>Sub:</strong> <?= htmlspecialchars($mock['subject']) ?></div>
    <div style="font-size:0.85rem; color:var(--text-primary,#fff); background:rgba(255,255,255,0.05); padding:10px; border-radius:6px; white-space:pre-wrap; word-break:break-all;"><?= htmlspecialchars($mock['body']) ?></div>
    
    <?php if ($mock['type'] === 'email'): 
        // Try to parse out the link automatically for easy clicking
        preg_match('/http[s]?:\/\/[^\s]+/', $mock['body'], $matches);
        if (!empty($matches[0])):
    ?>
        <a href="<?= $matches[0] ?>" class="btn btn-primary" style="margin-top:12px; display:block; text-align:center; padding:8px; font-size:0.85rem; border-radius:6px;">Click Simulated Link</a>
    <?php 
        endif;
    endif; 
    ?>
</div>
<style>
@keyframes slideUp { from { transform:translateY(100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
</style>
<?php 
    unset($_SESSION['mock_notification']);
endif;
?>
