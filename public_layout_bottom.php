<?php
// ==========================================
// PUBLIC (PRE-AUTH) PAGE CHROME — BOTTOM
// Closes the card wrapper opened in
// public_layout_top.php and renders the footer.
// Expects: $footer_prompt (HTML), $footer_note
// ==========================================
?>
            <div class="border-t border-slate-900 mt-6 pt-5 text-center">
                <p class="text-xs text-slate-400 font-light">
                    <?= $footer_prompt ?>
                </p>
            </div>
        </div>

        <footer class="text-center mt-8 text-[10px] text-slate-600 font-mono">
            <?= $footer_note ?>
        </footer>
    </div>

</body>
</html>
