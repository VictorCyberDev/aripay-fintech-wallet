<?php
ini_set('display_errors', 1);
ini_set('display_config', 1); // For deep environmental parsing
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';

// Optimized query using explicit column selections for execution efficiency
$stmt = $conn->prepare("SELECT fiat_balance, ari_balance, btc_balance, blockchain_address, solana_address FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
$stmt->execute([$user_id, $mode]);
$wallet = $stmt->fetch();

include 'sidebar.php';
?>

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-900 pb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">Institutional Asset Vault</h1>
            <p class="text-xs text-slate-400 mt-1">Cross-chain liquidity matrix & smart contract deployment terminal.</p>
        </div>
        <div class="flex items-center gap-3">
            <div id="solana-status" class="flex items-center gap-2 bg-[#14F195]/10 border border-[#14F195]/20 px-3 py-1.5 rounded-xl font-mono text-[10px] text-[#14F195]">
                <div class="w-1.5 h-1.5 rounded-full bg-[#14F195] animate-pulse"></div>
                <span id="wallet-status-text">SOLANA MAINNET-BETA</span>
            </div>
            <button id="connect-wallet-btn" class="flex items-center gap-2 bg-gradient-to-r from-[#9945FF] to-[#14F195] hover:opacity-90 text-slate-950 font-bold font-mono text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition-all duration-200 shadow-lg shadow-[#9945FF]/10">
                <i data-lucide="wallet" class="w-3.5 h-3.5 stroke-[2.5]"></i>
                <span id="btn-text">Connect Solana Wallet</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Available Cash Reserves</span>
                <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800 text-slate-400"><i data-lucide="banknote" class="w-3.5 h-3.5"></i></div>
            </div>
            <div class="text-3xl font-bold tracking-tight text-white font-mono"><?= number_format($wallet['fiat_balance'] ?? 0.00, 2) ?> <span class="text-xs font-sans text-slate-500 font-normal"><?= $base_currency ?></span></div>
        </div>

        <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Gas Protocols Token</span>
                <div class="w-7 h-7 rounded-lg bg-cyan-950/20 flex items-center justify-center border border-cyan-900/20 text-cyan-400"><i data-lucide="coins" class="w-3.5 h-3.5"></i></div>
            </div>
            <div class="text-3xl font-bold tracking-tight text-cyan-400 font-mono"><?= number_format($wallet['ari_balance'] ?? 0.00, 2) ?> <span class="text-xs font-sans text-slate-500 font-normal">ARI</span></div>
        </div>

        <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300 border-[#9945FF]/10 bg-[#9945FF]/5">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-mono text-[#9945FF] uppercase tracking-widest block font-bold">Solana Layer-1 Balance</span>
                <div class="w-7 h-7 rounded-lg bg-[#9945FF]/20 flex items-center justify-center border border-[#9945FF]/30 text-[#9945FF]"><i data-lucide="cpu" class="w-3.5 h-3.5"></i></div>
            </div>
            <div class="text-3xl font-bold tracking-tight text-white font-mono" id="sol-balance-display">0.0000 <span class="text-xs font-sans text-slate-500 font-normal">SOL</span></div>
            <span class="text-[9px] font-mono text-slate-500 block mt-1" id="sol-usd-value">≈ $0.00 USD</span>
        </div>

        <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
            <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Bitcoin Settlement Assets</span>
                <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800 text-slate-400"><i data-lucide="pie-chart" class="w-3.5 h-3.5"></i></div>
            </div>
            <div class="text-3xl font-bold tracking-tight text-white font-mono"><?= sprintf('%.6f', $wallet['btc_balance'] ?? 0.00) ?> <span class="text-xs font-sans text-slate-500 font-normal">BTC</span></div>
        </div>
    </div>

    <div class="glass-panel rounded-2xl overflow-hidden p-6 space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2"><i data-lucide="layers" class="w-3.5 h-3.5 text-cyan-400"></i>Cryptographic Network Routing Tables</h3>
        
        <div class="grid grid-cols-1 gap-3">
            <div class="p-4 rounded-xl bg-[#060a13] border border-slate-900 font-mono text-[10px] text-slate-500 flex flex-col sm:flex-row gap-2 items-center justify-between hover:bg-[#080d1a] transition-all">
                <div class="flex items-center gap-2"><i data-lucide="link" class="w-3.5 h-3.5 text-slate-400"></i><span>EVM Clearing Address (Ethereum/Ari-Chain Base Line):</span></div>
                <span class="text-cyan-400 bg-slate-950 px-3 py-1 rounded-md border border-slate-900 select-all tracking-tight font-bold"><?= $wallet['blockchain_address'] ?? '0x0000000000000000000000000000000000000000' ?></span>
            </div>

            <div class="p-4 rounded-xl bg-[#060a13] border border-slate-900 font-mono text-[10px] text-slate-500 flex flex-col sm:flex-row gap-2 items-center justify-between hover:bg-[#080d1a] transition-all border-[#9945FF]/10">
                <div class="flex items-center gap-2"><i data-lucide="zap" class="w-3.5 h-3.5 text-[#14F195]"></i><span class="text-slate-300">Solana Programmatic Key (Connected Wallet Account):</span></div>
                <span id="solana-pubkey-display" class="text-[#14F195] bg-slate-950 px-3 py-1 rounded-md border border-slate-900 select-all tracking-tight font-bold">Not Connected</span>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const connectBtn = document.getElementById("connect-wallet-btn");
        const btnText = document.getElementById("btn-text");
        const pubkeyDisplay = document.getElementById("solana-pubkey-display");
        const solBalanceDisplay = document.getElementById("sol-balance-display");
        const solUsdDisplay = document.getElementById("sol-usd-value");
        const walletStatusText = document.getElementById("wallet-status-text");

        let walletAddress = null;

        // Establish connection parameter directly to high-throughput Solana Mainnet RPC Endpoints
        const connection = new solanaWeb3.Connection("https://api.mainnet-beta.solana.com", "confirmed");

        // Real-Time Portfolio Liquidity Fetch Engine
        async function fetchOnChainSolBalance(publicKeyStr) {
            try {
                const pubKey = new solanaWeb3.PublicKey(publicKeyStr);
                const lamports = await connection.getBalance(pubKey);
                const solValue = lamports / solanaWeb3.LAMPORTS_PER_SOL;
                
                // Formulate presentation tracking structures
                solBalanceDisplay.innerHTML = `${solValue.toFixed(4)} <span class="text-xs font-sans text-slate-500 font-normal">SOL</span>`;
                
                // Fetch basic estimation pricing variables (can map to CoinGecko endpoint dynamically later)
                const fallbackPriceEst = solValue * 145.00; 
                solUsdDisplay.innerText = `≈ $${fallbackPriceEst.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} USD`;
            } catch (err) {
                console.error("On-chain extraction layer execution fault:", err);
            }
        }

        // Web3 Connection Router Strategy
        async function connectWallet() {
            if (window.solana && window.solana.isPhantom) {
                try {
                    walletStatusText.innerText = "AUTHENTICATING NODE...";
                    const response = await window.solana.connect();
                    walletAddress = response.publicKey.toString();
                    
                    // Rewrite state management layout variables
                    btnText.innerText = "Wallet Linked";
                    pubkeyDisplay.innerText = walletAddress;
                    walletStatusText.innerText = "CONNECTED // SECURE SOL DATA STREAM";
                    
                    // Execution balancing metrics
                    await fetchOnChainSolBalance(walletAddress);

                    // Optional: Push this connected address back to database seamlessly via background AJAX payload tracking
                    fetch('update_solana_address.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ address: walletAddress })
                    });

                } catch (err) {
                    walletStatusText.innerText = "CONNECTION REJECTED";
                    console.error("User closed authorization pipeline matrix:", err);
                }
            } else {
                alert("Solana Non-Custodial Engine Pipeline Interrupted: Please install Phantom Wallet or a compatible SPL provider extension to access VC parameters.");
                window.open("https://phantom.app/", "_blank");
            }
        }

        connectBtn.addEventListener("click", connectWallet);
    });
</script>

<?php include 'footer.php'; ?>