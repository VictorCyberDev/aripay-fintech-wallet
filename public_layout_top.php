<?php
// ==========================================
// PUBLIC (PRE-AUTH) PAGE CHROME — TOP
// Shared <head>, background, brand header and
// card wrapper used by the login/register views.
// Expects: $page_title, $card_title, $card_subtitle
// Optional: $error, $success_message
// ==========================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-[#090D16] text-slate-200 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] pointer-events-none opacity-[0.10]"></div>

    <div class="relative w-full max-w-md z-10">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight text-white focus:outline-none">
                <span class="h-5 w-2 bg-cyan-400 rounded-sm"></span>ARI-PAY
            </a>
            <p class="text-xs text-slate-500 mt-2 uppercase tracking-widest font-mono">Unified Fintech Ledger Protocol</p>
        </div>

        <div class="border border-slate-800/80 bg-slate-950/60 p-6 sm:p-8 rounded-xl shadow-2xl backdrop-blur-md">
            <h2 class="text-xl font-bold text-white tracking-tight mb-1"><?= $card_title ?></h2>
            <p class="text-xs text-slate-400 mb-6 font-light"><?= $card_subtitle ?></p>

            <?php if(!empty($success_message)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs px-4 py-3 rounded-lg mb-5 flex items-start gap-2 font-mono">
                    <span class="font-bold text-emerald-500">[SUCCESS]</span>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if(!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs px-4 py-3 rounded-lg mb-5 flex items-start gap-2 font-mono">
                    <span class="font-bold text-red-500">[ERROR]</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
