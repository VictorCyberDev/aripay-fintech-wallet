<?php
require 'bootstrap.php';
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';
$msg = "";

// NOTE: This page needs TWO new tables. Run this SQL once in phpMyAdmin:
//
// 1) Stores submitted physical cheque deposits:
// CREATE TABLE physical_cheques (
//   id INT AUTO_INCREMENT PRIMARY KEY,
//   submitted_by INT NOT NULL,
//   cheque_type ENUM('normal','other_bank') NOT NULL,
//   bank_name VARCHAR(100) NOT NULL,
//   cheque_owner_acc_no VARCHAR(30) NOT NULL,
//   receiver_acc_no VARCHAR(30) NOT NULL,
//   receiver_name VARCHAR(150) NULL,
//   amount DECIMAL(18,2) NOT NULL,
//   image_path VARCHAR(255) NULL,
//   status ENUM('pending','approved','rejected') DEFAULT 'pending',
//   wallet_type VARCHAR(10) NOT NULL,
//   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );
//
// 2) Mock bank account directory used ONLY for demo name-lookup (not a real bank connection):
// CREATE TABLE mock_bank_accounts (
//   acc_no VARCHAR(30) PRIMARY KEY,
//   acc_name VARCHAR(150) NOT NULL,
//   bank_name VARCHAR(100) NOT NULL
// );
// INSERT INTO mock_bank_accounts (acc_no, acc_name, bank_name) VALUES
// ('0123456789', 'Chinedu Okafor', 'Ari-Pay'),
// ('9876543210', 'Amaka Johnson', 'GTBank'),
// ('1122334455', 'Ibrahim Musa', 'Access Bank');

// AJAX-style lookup endpoint (called via fetch from the JS below)
if (isset($_GET['lookup_acc'])) {
    header('Content-Type: application/json');
    $acc = trim($_GET['lookup_acc']);
    try {
        $stmt = $conn->prepare("SELECT acc_name FROM mock_bank_accounts WHERE acc_no = ? LIMIT 1");
        $stmt->execute([$acc]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['found' => (bool)$result, 'name' => $result['acc_name'] ?? null]);
    } catch (Exception $e) {
        log_app_error('Bank account lookup failed', $e);
        http_response_code(500);
        echo json_encode(['found' => false, 'name' => null, 'error' => 'Lookup unavailable']);
    }
    exit();
}

$banks = ['Ari-Pay', 'GTBank', 'Access Bank', 'Zenith Bank', 'UBA', 'First Bank', 'Kuda', 'Opay'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cheque'])) {
    $cheque_type = $_POST['cheque_type'];
    $bank_name = $_POST['bank_name'];
    $owner_acc = trim($_POST['cheque_owner_acc']);
    $receiver_acc = trim($_POST['receiver_acc']);
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $amount = floatval($_POST['amount']);

    if ($amount <= 0 || empty($owner_acc) || empty($receiver_acc)) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-circle' class='w-4 h-4 shrink-0'></i><span>Please fill all required fields correctly.</span></div>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO physical_cheques (submitted_by, cheque_type, bank_name, cheque_owner_acc_no, receiver_acc_no, receiver_name, amount, wallet_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $cheque_type, $bank_name, $owner_acc, $receiver_acc, $receiver_name, $amount, $mode]);
            $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='check-circle' class='w-4 h-4 shrink-0'></i><span>Cheque submitted for processing. Status: Pending review.</span></div>";
        } catch (Exception $e) {
            log_app_error('Physical cheque submission failed', $e);
            $msg = user_error_notice('Could not submit the cheque. Please try again.');
        }
    }
}

$stmt_list = $conn->prepare("SELECT * FROM physical_cheques WHERE submitted_by = ? AND wallet_type = ? ORDER BY created_at DESC LIMIT 10");
$stmt_list->execute([$user_id, $mode]);
$my_submissions = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Cheque In</h1>
        <p class="text-xs text-slate-400 mt-1 font-light">Lodge a physical cheque for processing without visiting a bank.</p>
        <p class="text-[10px] text-amber-400 mt-2 bg-amber-500/5 border border-amber-500/10 rounded-lg px-3 py-2">
            Demo mode: account name lookup uses a test directory, not a live bank connection.
        </p>
    </div>

    <?= $msg ?>

    <div class="glass-panel p-6 rounded-2xl shadow-2xl">
        <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-900 font-mono text-[10px] mb-6">
            <button type="button" onclick="switchChequeType('normal')" id="btn-normal" class="cheque-type-btn flex-1 px-3 py-2 rounded-lg text-white bg-slate-900 border border-slate-800 font-bold tracking-tight">NORMAL CHEQUE</button>
            <button type="button" onclick="switchChequeType('other_bank')" id="btn-other_bank" class="cheque-type-btn flex-1 px-3 py-2 rounded-lg text-slate-500 hover:text-slate-300 transition-colors">OTHER BANK CHEQUE</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="cheque_type" id="cheque_type_input" value="normal">

            <!-- Shared: bank selection -->
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Bank</label>
                <select name="bank_name" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Shared: cheque image upload -->
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Upload Cheque Image</label>
                <div class="relative flex items-center">
                    <i data-lucide="image" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                    <input type="file" name="cheque_image" accept="image/*" class="input-dark w-full text-xs p-3 pl-11 rounded-xl font-mono file:bg-slate-900 file:text-slate-300 file:border-0 file:rounded-md file:px-2 file:py-1 file:mr-3">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Cheque Owner Account Number</label>
                <input type="text" name="cheque_owner_acc" required placeholder="0123456789" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono">
            </div>

            <!-- Only shown for "Other Bank Cheque" -->
            <div id="pay_to_section" style="display:none;">
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Pay To (Bank)</label>
                <select name="pay_to_bank" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Receiver's Account Number</label>
                <input type="text" name="receiver_acc" id="receiver_acc" required placeholder="9876543210" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono">
                <div id="verify_status" class="text-[10px] mt-2 font-mono"></div>
                <input type="hidden" name="receiver_name" id="receiver_name">
            </div>

            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Amount (<?= $base_currency ?>)</label>
                <input type="number" step="0.01" name="amount" required placeholder="0.00" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono">
            </div>

            <button type="submit" name="submit_cheque" id="submit_btn" disabled class="w-full bg-slate-700 text-slate-400 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono cursor-not-allowed">
                Verify Account to Continue
            </button>
        </form>
    </div>

    <!-- Submission history -->
    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-900 bg-slate-950/10">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Your Submitted Cheques</h3>
        </div>
        <div class="divide-y divide-slate-900">
            <?php if (empty($my_submissions)): ?>
                <div class="p-10 text-center text-slate-600 text-xs">No cheques submitted yet.</div>
            <?php else: ?>
                <?php foreach ($my_submissions as $c): ?>
                <div class="p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold text-slate-200 block"><?= htmlspecialchars($c['bank_name']) ?> &rarr; <?= htmlspecialchars($c['receiver_acc_no']) ?></span>
                        <span class="text-[10px] text-slate-500"><?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-white block"><?= number_format($c['amount'], 2) ?></span>
                        <span class="text-[9px] font-mono uppercase <?= $c['status'] == 'approved' ? 'text-emerald-400' : ($c['status'] == 'pending' ? 'text-amber-400' : 'text-rose-400') ?>">
                            <?= $c['status'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchChequeType(type) {
        document.getElementById('cheque_type_input').value = type;
        document.getElementById('pay_to_section').style.display = (type === 'other_bank') ? 'block' : 'none';

        document.querySelectorAll('.cheque-type-btn').forEach(btn => {
            btn.classList.remove('bg-slate-900', 'text-white', 'border', 'border-slate-800');
            btn.classList.add('text-slate-500');
        });
        const activeBtn = document.getElementById('btn-' + type);
        activeBtn.classList.add('bg-slate-900', 'text-white', 'border', 'border-slate-800');
        activeBtn.classList.remove('text-slate-500');
    }

    // Live account name lookup as the user types the receiver's account number
    const receiverAccInput = document.getElementById('receiver_acc');
    const verifyStatus = document.getElementById('verify_status');
    const receiverNameHidden = document.getElementById('receiver_name');
    const submitBtn = document.getElementById('submit_btn');
    let lookupTimeout;

    receiverAccInput.addEventListener('input', function() {
        clearTimeout(lookupTimeout);
        const acc = this.value.trim();
        submitBtn.disabled = true;
        submitBtn.innerText = "Verify Account to Continue";
        submitBtn.className = "w-full bg-slate-700 text-slate-400 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono cursor-not-allowed";
        verifyStatus.innerText = "";

        if (acc.length < 5) return;

        lookupTimeout = setTimeout(() => {
            verifyStatus.innerText = "Checking account...";
            verifyStatus.className = "text-[10px] mt-2 font-mono text-slate-500";

            fetch('cheque_in.php?lookup_acc=' + encodeURIComponent(acc))
                .then(res => res.json())
                .then(data => {
                    if (data.found) {
                        verifyStatus.innerText = "✓ Verified: " + data.name;
                        verifyStatus.className = "text-[10px] mt-2 font-mono text-emerald-400 font-bold";
                        receiverNameHidden.value = data.name;
                        submitBtn.disabled = false;
                        submitBtn.innerText = "Submit Cheque";
                        submitBtn.className = "w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono";
                    } else {
                        verifyStatus.innerText = "✗ No account found with that number";
                        verifyStatus.className = "text-[10px] mt-2 font-mono text-rose-400 font-bold";
                    }
                });
        }, 500);
    });

    lucide.createIcons();
</script>

<?php include 'footer.php'; ?>
