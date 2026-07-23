<?php
// ==========================================
// SYSTEM SECURITY & TERMINAL INITIALIZATION
// ==========================================
require 'bootstrap.php';
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'sidebar.php';

// Define target enterprise assets to track (54 structural pairs mapped to USDT)
$ticker_symbols = [
    'BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOT', 'AVAX', 'LINK', 'MATIC',
    'LTC', 'BCH', 'UNI', 'ATOM', 'XLM', 'ICP', 'FIL', 'HBAR', 'GRT', 'LDO',
    'NEAR', 'MKR', 'OP', 'ARB', 'RUNE', 'INJ', 'TIA', 'SUI', 'APT', 'SEI',
    'IMX', 'BEAM', 'FET', 'AGIX', 'RENDER', 'WIF', 'BONK', 'FLOKI', 'PEPE', 'SHIB',
    'DOGE', 'STX', 'THETA', 'VET', 'EGLD', 'FLOW', 'FTM', 'GALA', 'SAND', 'MANA',
    'AXS', 'CHZ', 'CRV', 'AAVE'
];
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-900 pb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">Market Intelligence Terminal</h1>
            <p class="text-xs text-slate-400 mt-1 font-light">Interactive tracking workspaces paired with high-frequency WebSocket pricing clusters.</p>
        </div>
        <div class="flex items-center gap-2 font-mono text-[10px] bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-900 text-slate-500">
            <div class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></div>
            <span>NETWORK STREAM: <span class="text-cyan-400 font-bold">ONLINE</span></span>
        </div>
    </div>

    <div class="glass-panel rounded-2xl border border-[#9945FF]/20 bg-slate-950/40 p-4 shadow-2xl relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-900 pb-4 mb-4 gap-2">
            <div class="flex items-center gap-3">
                <div class="w-2.5 h-2.5 rounded-full bg-[#14F195] animate-pulse"></div>
                <h2 id="active-chart-title" class="text-xs font-bold font-mono tracking-wider text-slate-200 uppercase">
                    ACTIVE MONITOR: BINANCE:BTCUSDT SPOT
                </h2>
            </div>
            <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-900 font-mono text-[10px]">
                <button onclick="switchChartStyle('1')" class="chart-style-btn px-2.5 py-1 rounded-lg text-cyan-400 bg-slate-900 border border-slate-800 font-bold tracking-tight">Candles</button>
                <button onclick="switchChartStyle('0')" class="chart-style-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-300 transition-colors">Bars/Sticks</button>
                <button onclick="switchChartStyle('2')" class="chart-style-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-300 transition-colors">Line</button>
                <button onclick="switchChartStyle('3')" class="chart-style-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-300 transition-colors">Area</button>
            </div>
        </div>
        <div class="w-full h-[380px] rounded-xl overflow-hidden border border-slate-900" id="tv-container">
            <div id="tradingview_workspace"></div>
        </div>
    </div>

    <div>
        <h3 class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block mb-4">Liquidity Board Index (Select Asset to view analytics)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="market-board">
            <?php foreach ($ticker_symbols as $symbol): ?>
                <div id="card-<?= strtolower($symbol) ?>" 
                     onclick="loadTargetAssetChart('<?= $symbol ?>')"
                     class="glass-panel p-4 rounded-xl border border-slate-900 bg-slate-950/20 hover:border-slate-700/80 hover:bg-slate-900/10 cursor-pointer transition-all duration-200 relative overflow-hidden group select-none">
                    
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800 text-[10px] font-bold text-slate-300 font-mono">
                                <?= substr($symbol, 0, 2) ?>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-white block tracking-tight font-sans"><?= $symbol ?></span>
                                <span class="text-[9px] font-mono text-slate-500 uppercase">USDT Pair</span>
                            </div>
                        </div>
                        <a href="trade.php?asset=<?= $symbol ?>" class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 flex items-center bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/20 text-cyan-400 rounded-md px-2 py-0.5 text-[9px] font-mono font-bold">
                            SWAP
                        </a>
                    </div>
                    
                    <div class="flex items-baseline justify-between mt-3 font-mono">
                        <span id="price-<?= strtolower($symbol) ?>" class="text-base font-bold text-slate-200 transition-all">
                            Loading...
                        </span>
                        <span id="change-<?= strtolower($symbol) ?>" class="text-[10px] font-bold text-slate-500">
                            0.00%
                        </span>
                    </div>
                    
                    <div id="pulse-<?= strtolower($symbol) ?>" class="absolute bottom-0 left-0 right-0 h-[2px] bg-transparent transition-all"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
<script>
    // Global tracking variables for widget configuration state persistence
    let currentSymbol = "BTC";
    let currentStyle = "1"; // Default to candlesticks

    // TradingView Dynamic Embedding Routine Architecture
    function renderTradingViewWidget() {
        new TradingView.widget({
            "width": "100%",
            "height": "100%",
            "symbol": `BINANCE:${currentSymbol}USDT`,
            "interval": "60",
            "timezone": "Etc/UTC",
            "theme": "dark",
            "style": currentStyle, // 1=Candles, 0=Bars, 2=Line, 3=Area
            "locale": "en",
            "toolbar_bg": "#02040a",
            "enable_publishing": false,
            "hide_top_toolbar": false,
            "hide_legend": false,
            "save_image": false,
            "container_id": "tradingview_workspace",
            "studies": [
                "RSI@tv-basicstudies",
                "MASimple@tv-basicstudies"
            ],
            "disabled_features": ["header_symbol_search", "header_compare"],
            "overrides": {
                "paneProperties.background": "#030712",
                "paneProperties.vertGridProperties.color": "#090f1f",
                "paneProperties.horzGridProperties.color": "#090f1f"
            }
        });
        document.getElementById('active-chart-title').innerText = `ACTIVE MONITOR: BINANCE:${currentSymbol}USDT SPOT`;
    }

    // Triggered when any specific asset block card is clicked in the view panel
    function loadTargetAssetChart(symbol) {
        currentSymbol = symbol;
        renderTradingViewWidget();
        // Soft focus view back up to the charting layout window screen anchor seamlessly
        document.getElementById('tv-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Toggle switch handler logic for bar, candlestick, lines conversions
    function switchChartStyle(styleCode) {
        currentStyle = styleCode;
        renderTradingViewWidget();
        
        // Highlight active button layout styles 
        const buttons = document.querySelectorAll('.chart-style-btn');
        buttons.forEach((btn, index) => {
            if(btn.getAttribute('onclick').includes(`'${styleCode}'`)) {
                btn.className = "chart-style-btn px-2.5 py-1 rounded-lg text-cyan-400 bg-slate-900 border border-slate-800 font-bold tracking-tight";
            } else {
                btn.className = "chart-style-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-300 transition-colors";
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Render basic tracking anchor frame immediately on baseline document ready event hooks
        renderTradingViewWidget();

        // ----------------------------------------------------
        // HIGH FREQUENCY BINANCE WEBSOCKET TICKER CLUSTER FLOW
        // ----------------------------------------------------
        const symbols = <?= json_encode(array_map('strtolower', $ticker_symbols)) ?>;
        const streams = symbols.map(s => `${s}usdt@ticker`).join('/');
        const socketUrl = `wss://stream.binance.com:9443/stream?streams=${streams}`;
        const socket = new WebSocket(socketUrl);

        socket.onmessage = function(event) {
            const msgData = JSON.parse(event.data);
            const streamName = msgData.stream;
            const data = msgData.data;
            const symbol = streamName.split('usdt')[0];
            
            const priceNode  = document.getElementById(`price-${symbol}`);
            const changeNode = document.getElementById(`change-${symbol}`);
            const pulseNode  = document.getElementById(`pulse-${symbol}`);

            if (priceNode && data) {
                const currentPrice = parseFloat(data.c);
                const priceChangePercent = parseFloat(data.P);
                let formatPrecision = currentPrice > 100 ? 2 : (currentPrice > 1 ? 4 : 6);
                const oldPrice = parseFloat(priceNode.getAttribute('data-price') || "0");

                priceNode.innerText = `$${currentPrice.toFixed(formatPrecision)}`;
                priceNode.setAttribute('data-price', currentPrice);

                if (priceChangePercent >= 0) {
                    changeNode.innerText = `+${priceChangePercent.toFixed(2)}%`;
                    changeNode.className = "text-[10px] font-bold text-emerald-400";
                } else {
                    changeNode.innerText = `${priceChangePercent.toFixed(2)}%`;
                    changeNode.className = "text-[10px] font-bold text-rose-400";
                }

                if (oldPrice > 0 && currentPrice !== oldPrice) {
                    if (currentPrice > oldPrice) {
                        pulseNode.className = "absolute bottom-0 left-0 right-0 h-[2px] bg-emerald-500 animate-pulse";
                        setTimeout(() => pulseNode.className = "absolute bottom-0 left-0 right-0 h-[2px] bg-transparent", 300);
                    } else {
                        pulseNode.className = "absolute bottom-0 left-0 right-0 h-[2px] bg-rose-500 animate-pulse";
                        setTimeout(() => pulseNode.className = "absolute bottom-0 left-0 right-0 h-[2px] bg-transparent", 300);
                    }
                }
            }
        };
    });
</script>

<?php include 'footer.php'; ?>