<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ari-Pay is a next-gen hybrid blockchain fintech platform offering multi-currency borderless wallets and instant global settlement.">
    <title>Ari-Pay | Next-Gen Blockchain Fintech</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-slate-100 font-sans antialiased selection:bg-cyan-500 selection:text-slate-900 min-h-screen flex flex-col justify-between relative overflow-x-hidden">

    <div class="absolute inset-0 bg-[linear-gradient(to_right,#0f172a_1px,transparent_1px),linear-gradient(to_bottom,#0f172a_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] pointer-events-none opacity-40"></div>

    <nav class="relative z-10 flex justify-between items-center px-6 py-5 max-w-7xl w-full mx-auto" aria-label="Global Navigation">
        <a href="#" class="text-2xl font-black tracking-wider text-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400 rounded-md">
            ARI-PAY<span class="text-white">.</span>
        </a>
        <div class="flex items-center space-x-2 sm:space-x-4">
            <a href="login.php" class="text-slate-300 hover:text-white font-medium px-4 py-2 rounded-lg hover:bg-slate-800/50 transition duration-200 focus:outline-none focus:ring-2 focus:ring-slate-700">
                Login
            </a>
            <a href="register.php" class="bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold px-5 py-2.5 rounded-xl transition duration-200 transform active:scale-95 shadow-md shadow-cyan-500/10 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">
                Get Started
            </a>
        </div>
    </nav>

    <main class="relative z-10 max-w-7xl mx-auto px-6 py-12 md:py-24 my-auto w-full grid md:grid-cols-2 gap-12 items-center">
        
        <section class="space-y-6 text-center md:text-left">
            <div class="inline-flex items-center gap-2 bg-cyan-500/10 text-cyan-400 text-xs sm:text-sm font-semibold px-3 py-1.5 rounded-full border border-cyan-500/20 shadow-inner">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                Powered by Hybrid Blockchain
            </div>
            
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.15]">
                Send Money Globally, Settled in <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-teal-300">Ari</span>.
            </h1>
            
            <p class="text-slate-400 text-base sm:text-lg max-w-xl mx-auto md:mx-0 leading-relaxed">
                The multi-currency borderless wallet that automatically converts any global currency instantly. Create an account to get a free demo testing wallet.
            </p>
            
            <div class="pt-4 flex flex-col sm:flex-row justify-center md:justify-start gap-4">
                <a href="register.php" class="inline-block bg-cyan-500 hover:bg-cyan-400 text-slate-950 text-base sm:text-lg font-bold px-8 py-4 rounded-xl shadow-lg shadow-cyan-500/20 transition duration-200 transform active:scale-98 text-center focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900">
                    Open Your Free Account
                </a>
            </div>
        </section>

        <section class="flex justify-center items-center relative" aria-label="Token Live Analytics Preview">
            <div class="absolute w-72 h-72 bg-gradient-to-tr from-cyan-500 to-blue-600 rounded-full blur-[100px] opacity-25 animate-pulse-slow pointer-events-none"></div>
            
            <div class="relative border border-slate-800 bg-slate-900/60 p-6 sm:p-8 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm transform hover:-translate-y-1 transition duration-300">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-xs sm:text-sm font-medium text-slate-400">Ari-Pay Native Token</span>
                    <span class="inline-flex items-center gap-1.5 text-xs bg-emerald-500/10 text-emerald-400 px-2.5 py-1 rounded-full border border-emerald-500/20 font-semibold tracking-wide">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                        LIVE
                    </span>
                </div>
                
                <div class="text-3xl sm:text-4xl font-mono font-bold tracking-tight text-white mb-1">1.00 ARI</div>
                <div class="text-sm text-slate-400 mb-6 font-medium">≈ $1.25 USD <span class="text-cyan-400 text-xs ml-1">(Dynamic)</span></div>
                
                <div class="border-t border-slate-800/80 pt-5 space-y-3 text-xs sm:text-sm text-slate-400">
                    <div class="flex justify-between items-center">
                        <span>Cross-Border Fee</span>
                        <span class="text-emerald-400 font-semibold font-mono">0%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Settlement Speed</span>
                        <span class="text-white font-medium font-mono">~2.4 Seconds</span>
                    </div>
                </div>
            </div>
        </section>
        
    </main>

    <footer class="relative z-10 text-center py-6 text-xs text-slate-500">
        &copy; 2026 Ari-Pay. All rights reserved.
    </footer>

</body>
</html>