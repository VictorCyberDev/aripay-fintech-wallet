<?php
require 'bootstrap.php';
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';

$stmt = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
$stmt->execute([$user_id, $mode]);
$wallet = $stmt->fetch();

// Same rate table used across the app - keep consistent everywhere
$rates = [
    'USD' => 1.0, 'NGN' => 1500.0, 'GBP' => 0.78, 'EUR' => 0.92,
    'ARI' => 0.80, 'BTC' => 0.000015, 'ETH' => 0.00028,
    'SOL' => 0.0068, 'USDC' => 1.00, 'USDT' => 1.00
];

$fiat_pairs = ['USD', 'NGN', 'GBP', 'EUR'];
$crypto_pairs = ['BTC', 'ETH', 'SOL', 'USDC', 'USDT'];
// Extra direct pair requested: BTC/USDC, shown separately with a live price feed below
$live_pairs = ['BTCUSDC' => 'BTC/USDC', 'BTCUSDT' => 'BTC/USDT'];

include 'sidebar.php';
?>

<div class="space-y-6">

    <!-- TOGGLE HEADER -->
    <div class="flex items-center justify-between border-b border-slate-900 pb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Wallet Overview</h1>
            <p class="text-xs text-slate-400 mt-1">Swipe or tap to switch between fiat and crypto view.</p>
        </div>
        <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-900 font-mono text-[10px]">
            <button onclick="switchView('fiat')" id="btn-fiat-view" class="view-btn px-3 py-1.5 rounded-lg text-white bg-slate-900 border border-slate-800 font-bold tracking-tight">FIAT</button>
            <button onclick="switchView('crypto')" id="btn-crypto-view" class="view-btn px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-300 transition-colors">CRYPTO</button>
        </div>
    </div>

    <!-- ============== FIAT DASHBOARD ============== -->
    <div id="view-fiat" class="wallet-view space-y-6">

        <!-- Balance card -->
        <div class="glass-panel p-6 rounded-2xl">
            <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block mb-2">Available Balance</span>
            <div class="text-4xl font-bold tracking-tight text-white font-mono">
                <?= number_format($wallet['fiat_balance'] ?? 0.00, 2) ?>
                <span class="text-sm font-sans text-slate-500 font-normal"><?= $base_currency ?></span>
            </div>
        </div>

        <!-- Action icons: transfer / withdraw / convert -->
        <div class="grid grid-cols-3 gap-4">
            <a href="remittance.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-cyan-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                    <i data-lucide="send" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Transfer</span>
            </a>
            <a href="withdraw.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-emerald-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i data-lucide="banknote" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Withdraw</span>
            </a>
            <a href="trade.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-amber-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i data-lucide="repeat" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Convert</span>
            </a>
        </div>

        <!-- Scrollable FX pairs -->
        <div class="glass-panel rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-900 bg-slate-950/10">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">FX Exchange Rates</h3>
            </div>
            <div class="divide-y divide-slate-900 max-h-72 overflow-y-auto">
                <?php foreach ($fiat_pairs as $cur):
                    if ($cur === $base_currency) continue;
                    $pair_rate = $rates[$cur] / $rates[$base_currency];
                ?>
                <div class="p-4 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-300"><?= $base_currency ?>/<?= $cur ?></span>
                    <span class="text-xs font-mono text-cyan-400 font-bold"><?= number_format($pair_rate, 4) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ============== CRYPTO DASHBOARD ============== -->
    <div id="view-crypto" class="wallet-view space-y-6" style="display:none;">

        <!-- ARI balance card -->
        <div class="glass-panel p-6 rounded-2xl border-cyan-500/10 bg-cyan-500/5">
            <span class="text-[10px] font-mono text-cyan-400 uppercase tracking-widest block mb-2 font-bold">ARI Balance (Base Asset)</span>
            <div class="text-4xl font-bold tracking-tight text-white font-mono">
                <?= number_format($wallet['ari_balance'] ?? 0.00, 2) ?>
                <span class="text-sm font-sans text-slate-500 font-normal">ARI</span>
            </div>
            <span class="text-xs font-mono text-slate-500 mt-1 block">
                ≈ <?= number_format(($wallet['ari_balance'] ?? 0) / $rates['ARI'], 2) ?> USDC (1 USDC = 1 USD)
            </span>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <a href="remittance.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-cyan-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                    <i data-lucide="send" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Transfer</span>
            </a>
            <a href="withdraw.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-emerald-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i data-lucide="banknote" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Withdraw</span>
            </a>
            <a href="trade.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-amber-500/30 transition-all">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i data-lucide="repeat" class="w-5 h-5"></i>
                </div>
                <span class="text-xs font-semibold text-slate-300">Convert</span>
            </a>
        </div>

        <!-- Crypto pairs vs ARI -->
        <div class="glass-panel rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-900 bg-slate-950/10">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Crypto Pairs (Base: ARI)</h3>
            </div>
            <div class="divide-y divide-slate-900 max-h-72 overflow-y-auto">
                <?php foreach ($crypto_pairs as $cur):
                    $pair_rate = $rates[$cur] / $rates['ARI'];
                ?>
                <div class="p-4 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-300">ARI/<?= $cur ?></span>
                    <span class="text-xs font-mono text-cyan-400 font-bold"><?= number_format($pair_rate, 6) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Live market pairs: real price feed from Binance's free public stream -->
        <div class="glass-panel rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-900 bg-slate-950/10 flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Live Market Pairs</h3>
                <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></div>
                    <span class="text-[9px] font-mono text-emerald-400">LIVE</span>
                </div>
            </div>
            <div class="divide-y divide-slate-900">
                <?php foreach ($live_pairs as $stream => $label): ?>
                <div class="p-4 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-300"><?= $label ?></span>
                    <div class="text-right">
                        <span id="live-price-<?= strtolower($stream) ?>" class="text-xs font-mono font-bold text-slate-200 block">Loading...</span>
                        <span id="live-change-<?= strtolower($stream) ?>" class="text-[10px] font-mono text-slate-500">0.00%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
    // Live BTC/USDC and BTC/USDT prices via Binance's free public WebSocket
    // (same approach already used in prices.php)
    const liveStreams = <?= json_encode(array_keys($live_pairs)) ?>.map(s => s.toLowerCase());
    const liveSocketUrl = `wss://stream.binance.com:9443/stream?streams=${liveStreams.map(s => s + '@ticker').join('/')}`;
    const liveSocket = new WebSocket(liveSocketUrl);

    liveSocket.onmessage = function(event) {
        const msg = JSON.parse(event.data);
        const stream = msg.stream.split('@')[0]; // e.g. "btcusdc"
        const data = msg.data;

        const priceNode = document.getElementById(`live-price-${stream}`);
        const changeNode = document.getElementById(`live-change-${stream}`);
        if (!priceNode || !data) return;

        const price = parseFloat(data.c);
        const changePercent = parseFloat(data.P);

        priceNode.innerText = `$${price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        if (changePercent >= 0) {
            changeNode.innerText = `+${changePercent.toFixed(2)}%`;
            changeNode.className = "text-[10px] font-mono text-emerald-400 font-bold block";
        } else {
            changeNode.innerText = `${changePercent.toFixed(2)}%`;
            changeNode.className = "text-[10px] font-mono text-rose-400 font-bold block";
        }
    };
</script>

<script>
    function switchView(view) {
        document.querySelectorAll('.wallet-view').forEach(el => el.style.display = 'none');
        document.getElementById('view-' + view).style.display = 'block';

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('bg-slate-900', 'text-white', 'border', 'border-slate-800');
            btn.classList.add('text-slate-500');
        });
        const activeBtn = document.getElementById('btn-' + view + '-view');
        activeBtn.classList.add('bg-slate-900', 'text-white', 'border', 'border-slate-800');
        activeBtn.classList.remove('text-slate-500');
    }

    // Basic swipe support for mobile (left/right between fiat and crypto)
    let touchStartX = 0;
    document.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].screenX; });
    document.addEventListener('touchend', e => {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchEndX - touchStartX;
        if (Math.abs(diff) < 50) return;
        const fiatVisible = document.getElementById('view-fiat').style.display !== 'none';
        if (diff < 0 && fiatVisible) switchView('crypto');
        if (diff > 0 && !fiatVisible) switchView('fiat');
    });

    lucide.createIcons();
</script>

<?php include 'footer.php'; ?>
