<?php $pageTitle = 'Dashboard'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<style>
    .dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .locality-list::-webkit-scrollbar { width: 4px; }
    .locality-list::-webkit-scrollbar-thumb { background: #e0e2e5; border-radius: 4px; }
    .last-child-no-border:last-child { border-bottom: none !important; }
    .smaller { font-size: 0.75rem; }
    .border-start-sm { border-left: 1px solid #eee; }
    @media (max-width: 575.98px) { .border-start-sm { border-left: none; border-top: 1px solid #eee; padding-top: 1rem; } }
</style>

<!-- Premium Header Title block -->
<style>
.dashboard-heading {
    color: #1e293b;
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.dashboard-subheading {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 500;
}
.btn-premium-action {
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
    color: #ffffff !important;
    border: none;
    border-radius: 10px;
    padding: 8px 18px;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-premium-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
}

/* Premium Date filter card */
.filter-card {
    background: #ffffff;
    border: 1px solid #f0f2f5;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    padding: 10px 20px;
}
.filter-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
}
.filter-date-input {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.filter-date-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.btn-filter-apply {
    background: #4f8ef7;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 6px 16px;
    font-size: 0.78rem;
    font-weight: 700;
    transition: background 0.2s;
}
.btn-filter-apply:hover { background: #3b82f6; }
.btn-filter-clear {
    font-size: 0.78rem;
    font-weight: 700;
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.2s;
}
.btn-filter-clear:hover { color: #64748b; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="dashboard-heading mb-0">BD Proposal Dashboard</h4>
        <p class="dashboard-subheading mb-0">Overview of all classified proposals</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=proposal_add" class="btn-premium-action">
            <i class="bi bi-plus-lg"></i> Add Proposal
        </a>
    </div>
</div>

<!-- Premium Date Filter -->
<div class="filter-card mb-4">
    <form class="row g-2 align-items-center" method="GET">
        <input type="hidden" name="page" value="dashboard">
        <div class="col-auto">
            <span class="filter-label"><i class="bi bi-funnel me-1"></i>Filter by Period:</span>
        </div>
        <div class="col-auto">
            <input type="date" name="from_date" class="filter-date-input" value="<?= e($filters['from_date']) ?>">
        </div>
        <div class="col-auto text-muted small px-1">to</div>
        <div class="col-auto">
            <input type="date" name="to_date" class="filter-date-input" value="<?= e($filters['to_date']) ?>">
        </div>
        <div class="col-auto ms-2 d-flex align-items-center gap-2">
            <button type="submit" class="btn-filter-apply">Apply</button>
            <?php if ($filters['from_date'] || $filters['to_date']): ?>
                <a href="index.php?page=dashboard" class="btn-filter-clear">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── Premium KPI Sparkline Cards ── -->
<style>
.kpi-card {
    background: #fff;
    border-radius: 14px;
    padding: 18px 20px 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f2f5;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.kpi-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.10); }
.kpi-label { font-size: 0.72rem; font-weight: 600; color: #9aa3b0; letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 6px; }
.kpi-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; }
.kpi-value { font-size: 1.7rem; font-weight: 800; line-height: 1.1; margin-bottom: 2px; }
.kpi-delta { font-size: 0.72rem; font-weight: 600; }
.kpi-delta.up   { color: #22c55e; }
.kpi-delta.down { color: #ef4444; }
.kpi-delta.neutral { color: #9aa3b0; }
.kpi-sparkline { margin-top: 10px; }
.kpi-sparkline svg { display: block; }
.ai-status-ring { position: relative; display: flex; align-items: center; justify-content: center; }
.ai-status-ring .ring-label { position: absolute; font-size: 1.1rem; font-weight: 800; color: #22c55e; }
</style>
<div class="row g-3 mb-4">
    <!-- Card 1: New Proposals Today -->
    <div class="col-6 col-xl">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">New Proposals Today</div>
                <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-envelope-plus"></i></div>
            </div>
            <div class="kpi-value text-dark"><?= number_format($todayCount) ?></div>
            <?php $dir = $todayDelta >= 0 ? 'up' : 'down'; $icon = $todayDelta >= 0 ? 'arrow-up' : 'arrow-down'; ?>
            <div class="kpi-delta <?= $dir ?>"><i class="bi bi-<?= $icon ?>-short"></i> <?= abs($todayDelta) ?>% vs yesterday</div>
            <div class="kpi-sparkline"><svg viewBox="0 0 80 28" width="80" height="28"><polyline points="0,22 13,18 26,20 40,12 53,16 66,8 80,10" fill="none" stroke="#4f8ef7" stroke-width="2" stroke-linejoin="round"/></svg></div>
        </div>
    </div>
    <!-- Card 2: AI Success Rate -->
    <div class="col-6 col-xl">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">AI Success Rate</div>
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-stars"></i></div>
            </div>
            <div class="kpi-value" style="color:#22c55e"><?= $aiSuccessRate ?>%</div>
            <div class="kpi-delta up"><i class="bi bi-arrow-up-short"></i> Stage 1 pass rate</div>
            <div class="kpi-sparkline"><svg viewBox="0 0 80 28" width="80" height="28"><polyline points="0,24 13,20 26,16 40,18 53,10 66,12 80,6" fill="none" stroke="#22c55e" stroke-width="2" stroke-linejoin="round"/></svg></div>
        </div>
    </div>
    <!-- Card 3: High Potential Deals -->
    <div class="col-6 col-xl">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">High Potential Deals</div>
                <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-trophy"></i></div>
            </div>
            <div class="kpi-value" style="color:#f59e0b"><?= number_format($stats['high'] ?? 0) ?></div>
            <?php $hwDir = $highNewThisWeek >= 0 ? 'up' : 'down'; ?>
            <div class="kpi-delta <?= $hwDir ?>"><i class="bi bi-arrow-<?= $hwDir ?>-short"></i> <?= abs($highNewThisWeek) ?> new this week</div>
            <div class="kpi-sparkline"><svg viewBox="0 0 80 28" width="80" height="28"><polyline points="0,20 13,18 26,22 40,14 53,10 66,14 80,8" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linejoin="round"/></svg></div>
        </div>
    </div>
    <!-- Card 4: Total Potential Value -->
    <div class="col-6 col-xl">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Total Potential Value</div>
                <div class="kpi-icon bg-info-subtle text-info"><i class="bi bi-currency-rupee"></i></div>
            </div>
            <div class="kpi-value" style="color:#7c5cfc">&#8377;<?= number_format($stats['potential_value_cr'] ?? 0, 1) ?><span style="font-size:1rem;font-weight:500;color:#9aa3b0"> Cr</span></div>
            <?php $vDir = $valDelta >= 0 ? 'up' : 'down'; ?>
            <div class="kpi-delta <?= $vDir ?>"><i class="bi bi-arrow-<?= $vDir ?>-short"></i> <?= abs($valDelta) ?>% vs last month</div>
            <div class="kpi-sparkline"><svg viewBox="0 0 80 28" width="80" height="28"><polyline points="0,26 13,20 26,24 40,16 53,18 66,10 80,12" fill="none" stroke="#7c5cfc" stroke-width="2" stroke-linejoin="round"/></svg></div>
        </div>
    </div>
    <!-- Card 5: Avg Feasibility Score -->
    <div class="col-6 col-xl">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Avg Feasibility Score</div>
                <div class="kpi-icon" style="background:#fdf2fa;color:#ec4899"><i class="bi bi-graph-up"></i></div>
            </div>
            <div class="kpi-value" style="color:#ec4899"><?= $stats['avg_score'] ?? '—' ?></div>
            <?php $sDir = $avgScoreDelta >= 0 ? 'up' : 'down'; ?>
            <div class="kpi-delta <?= $sDir ?>"><i class="bi bi-arrow-<?= $sDir ?>-short"></i> <?= abs($avgScoreDelta) ?> pts vs last month</div>
            <div class="kpi-sparkline"><svg viewBox="0 0 80 28" width="80" height="28"><polyline points="0,18 13,22 26,14 40,16 53,10 66,14 80,8" fill="none" stroke="#ec4899" stroke-width="2" stroke-linejoin="round"/></svg></div>
        </div>
    </div>
</div>

<!-- ── Proposal Journey ── -->
<?php
$total      = (int)($stats['total']        ?? 0);
$aiAnalyzed = (int)($stats['total']        ?? 0); // All proposals undergo AI Analysis
$rejected   = (int)($stats['rejected']     ?? 0);
$eligible   = (int)($stats['eligible']     ?? 0); // Passed Stage 1
$high       = (int)($stats['high']         ?? 0);
$accepted   = (int)($stats['dec_accepted'] ?? 0);

$connFill2  = $total   > 0 ? round($rejected / $total   * 100) : 0;
$connFill3  = $total   > 0 ? round($eligible / $total   * 100) : 0;
$connFill4  = $eligible > 0 ? round($high     / $eligible * 100) : 0;
$connFill5  = $total   > 0 ? round($accepted / $total   * 100) : 0;
?>
<style>
.journey-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px 28px 28px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    margin-bottom: 1.5rem;
}
.journey-header {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #8b95a5;
    margin-bottom: 24px;
}
.journey-stepper {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    overflow-x: auto;
    padding-bottom: 4px;
}
.journey-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 72px;
    animation: jFadeUp 0.5s ease both;
}
.journey-step:nth-child(1)  { animation-delay: 0.05s; }
.journey-step:nth-child(3)  { animation-delay: 0.12s; }
.journey-step:nth-child(5)  { animation-delay: 0.19s; }
.journey-step:nth-child(7)  { animation-delay: 0.26s; }
.journey-step:nth-child(9)  { animation-delay: 0.33s; }
.journey-step:nth-child(11) { animation-delay: 0.40s; }
@keyframes jFadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.journey-icon {
    width: 58px; height: 58px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    border: 2.5px solid;
    background: #fff;
    position: relative; z-index: 2;
    transition: transform 0.2s, box-shadow 0.2s;
}
.journey-icon:hover { transform: translateY(-3px) scale(1.08); }
.step-received  .journey-icon { border-color:#4f8ef7; color:#4f8ef7; box-shadow:0 4px 14px rgba(79,142,247,.20); }
.step-ai        .journey-icon { border-color:#7c5cfc; color:#7c5cfc; box-shadow:0 4px 14px rgba(124,92,252,.20); }
.step-rejected  .journey-icon { border-color:#ef4444; color:#ef4444; box-shadow:0 4px 14px rgba(239,68,68,.20); }
.step-eligible  .journey-icon { border-color:#22c55e; color:#22c55e; box-shadow:0 4px 14px rgba(34,197,94,.20); }
.step-high      .journey-icon { border-color:#f97316; color:#f97316; box-shadow:0 4px 14px rgba(249,115,22,.20); }
.step-accepted  .journey-icon { border-color:#10b981; color:#10b981; box-shadow:0 4px 14px rgba(16,185,129,.20); }
.journey-count {
    font-size: 1.45rem; font-weight: 800;
    margin-top: 9px; line-height: 1;
}
.step-received  .journey-count { color:#4f8ef7; }
.step-ai        .journey-count { color:#7c5cfc; }
.step-rejected  .journey-count { color:#ef4444; }
.step-eligible  .journey-count { color:#22c55e; }
.step-high      .journey-count { color:#f97316; }
.step-accepted  .journey-count { color:#10b981; }
.journey-label {
    font-size: 0.70rem; font-weight: 600;
    color: #9aa3b0; margin-top: 4px;
    text-align: center; white-space: nowrap;
}
.journey-conn {
    flex: 1; height: 3px;
    margin-top: 27px;
    background: #edf0f4;
    border-radius: 2px;
    position: relative; overflow: hidden;
    min-width: 14px;
}
.journey-conn-fill {
    position: absolute; left:0; top:0; bottom:0;
    border-radius: 2px;
    width: var(--fw, 100%);
    animation: connGrow 0.7s ease both;
}
@keyframes connGrow { from { width:0; } to { width: var(--fw,100%); } }
.journey-conn.c1 .journey-conn-fill { background:linear-gradient(90deg,#4f8ef7,#7c5cfc); animation-delay:.08s; }
.journey-conn.c2 .journey-conn-fill { background:linear-gradient(90deg,#7c5cfc,#ef4444); animation-delay:.15s; }
.journey-conn.c3 .journey-conn-fill { background:linear-gradient(90deg,#ef4444,#22c55e); animation-delay:.22s; }
.journey-conn.c4 .journey-conn-fill { background:linear-gradient(90deg,#22c55e,#f97316); animation-delay:.29s; }
.journey-conn.c5 .journey-conn-fill { background:linear-gradient(90deg,#f97316,#10b981); animation-delay:.36s; }
</style>

<div class="journey-card">
    <div class="journey-header"><i class="bi bi-signpost-split me-2"></i>Proposal Journey</div>
    <div class="journey-stepper">

        <div class="journey-step step-received">
            <div class="journey-icon"><i class="bi bi-envelope-open"></i></div>
            <div class="journey-count"><?= number_format($total) ?></div>
            <div class="journey-label">Received</div>
        </div>

        <div class="journey-conn c1"><div class="journey-conn-fill" style="--fw:100%"></div></div>

        <div class="journey-step step-ai">
            <div class="journey-icon"><i class="bi bi-cpu"></i></div>
            <div class="journey-count"><?= number_format($aiAnalyzed) ?></div>
            <div class="journey-label">AI Analysis</div>
        </div>

        <div class="journey-conn c2"><div class="journey-conn-fill" style="--fw:<?= $connFill2 ?>%"></div></div>

        <div class="journey-step step-rejected">
            <div class="journey-icon"><i class="bi bi-x-circle"></i></div>
            <div class="journey-count"><?= number_format($rejected) ?></div>
            <div class="journey-label">Rejected</div>
        </div>

        <div class="journey-conn c3"><div class="journey-conn-fill" style="--fw:<?= $connFill3 ?>%"></div></div>

        <div class="journey-step step-eligible">
            <div class="journey-icon"><i class="bi bi-patch-check"></i></div>
            <div class="journey-count"><?= number_format($eligible) ?></div>
            <div class="journey-label">Passed Stage 1</div>
        </div>

        <div class="journey-conn c4"><div class="journey-conn-fill" style="--fw:<?= $connFill4 ?>%"></div></div>

        <div class="journey-step step-high">
            <div class="journey-icon"><i class="bi bi-star"></i></div>
            <div class="journey-count"><?= number_format($high) ?></div>
            <div class="journey-label">High Potential</div>
        </div>

        <div class="journey-conn c5"><div class="journey-conn-fill" style="--fw:<?= $connFill5 ?>%"></div></div>

        <div class="journey-step step-accepted">
            <div class="journey-icon"><i class="bi bi-check-circle"></i></div>
            <div class="journey-count"><?= number_format($accepted) ?></div>
            <div class="journey-label">Accepted</div>
        </div>

    </div>
</div>

<!-- ── BD Intelligence Assistant ── -->
<style>
.bd-assist-card {
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    border: 1.5px solid #e4eaf6;
    border-radius: 16px;
    padding: 22px 28px;
    box-shadow: 0 2px 16px rgba(79,142,247,0.07);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.bd-assist-badge {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 2px 8px;
    border-radius: 20px;
    background: #e0e8ff;
    color: #4f8ef7;
    margin-left: 8px;
    vertical-align: middle;
}
.bd-assist-greeting { font-size: 0.82rem; color: #6b7a99; margin: 6px 0 16px; }
.bd-assist-stat { }
.bd-assist-stat .val { font-size: 1.15rem; font-weight: 800; }
.bd-assist-stat .lbl { font-size: 0.7rem; color: #9aa3b0; font-weight: 500; }
.bd-assist-robot-wrap {
    flex-shrink: 0;
    width: 160px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    overflow: visible;
}
.bd-assist-robot {
    height: 160px;
    width: auto;
    object-fit: contain;
    mix-blend-mode: multiply;
    pointer-events: none;
    animation: robotFloat 3s ease-in-out infinite;
}
@keyframes robotFloat {
    0%, 100% { transform: translateY(0px) scale(1); }
    50%       { transform: translateY(-7px) scale(1.02); }
}
.bd-assist-input-wrap {
    background: #f0f4ff;
    border-radius: 12px;
    padding: 4px 4px 4px 16px;
    display: flex;
    align-items: center;
    margin-top: 16px;
    border: 1.5px solid #dde6f9;
}
.bd-assist-input-wrap input {
    background: transparent;
    border: none;
    outline: none;
    flex: 1;
    font-size: 0.85rem;
    color: #3a4a6b;
    padding: 6px 0;
}
.bd-assist-input-wrap input::placeholder { color: #b0bcd0; }
.bd-assist-ask-btn {
    background: #4f8ef7;
    color: #fff;
    border: none;
    border-radius: 9px;
    padding: 8px 18px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: background 0.2s;
}
.bd-assist-ask-btn:hover { background: #3a7ae0; }
.bd-assist-response {
    margin-top: 12px;
    padding: 12px 16px;
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e4eaf6;
    font-size: 0.82rem;
    color: #3a4a6b;
    display: none;
}
</style>
<div class="bd-assist-card" style="display:flex;gap:0;align-items:stretch;overflow:visible;">
    <!-- Left: Content -->
    <div style="flex:1;min-width:0;">
    <div class="d-flex align-items-center">
        <i class="bi bi-robot text-primary" style="font-size:1.3rem"></i>
        <span class="fw-bold ms-2" style="font-size:1rem;color:#1a2a4a">BD Intelligence Assistant</span>
        <span class="bd-assist-badge">BETA</span>
    </div>
    <?php
        $hour = (int)date('H');
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    ?>
    <div class="bd-assist-greeting"><?= $greeting ?>! I've analyzed your proposals and found some interesting insights.</div>

    <div class="row g-3" style="max-width:600px">
        <div class="col-6 col-md-3 bd-assist-stat">
            <div class="val text-warning"><?= number_format($stats['high'] ?? 0) ?></div>
            <div class="lbl">High Potential Projects</div>
        </div>
        <div class="col-6 col-md-3 bd-assist-stat">
            <div class="val" style="color:#7c5cfc">&#8377;<?= number_format($stats['potential_value_cr'] ?? 0, 1) ?> Cr</div>
            <div class="lbl">Total Opportunity Value</div>
        </div>
        <div class="col-6 col-md-3 bd-assist-stat">
            <div class="val text-success"><?= $aiSuccessRate ?>%</div>
            <div class="lbl">AI Confidence Score</div>
        </div>
        <div class="col-6 col-md-3 bd-assist-stat">
            <div class="val text-primary"><?= $stats['avg_prob'] ?? '—' ?>%</div>
            <div class="lbl">Avg Success Probability</div>
        </div>
    </div>

    <div class="bd-assist-input-wrap">
        <input type="text" id="ai_chat_input" placeholder="Ask me anything about your proposals...">
        <button class="bd-assist-ask-btn" id="btn_ai_chat">
            <span id="ai_chat_btn_text"><i class="bi bi-send-fill"></i> Ask AI</span>
            <span id="ai_chat_spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
        </button>
    </div>
    <div id="ai_chat_response" class="bd-assist-response"></div>
    </div><!-- /left -->
    <!-- Right: Robot -->
    <div class="bd-assist-robot-wrap">
        <img src="assets/img/ai_robot.png" alt="AI Assistant" class="bd-assist-robot">
    </div>
</div>

<!-- ── Charts + Top proposals ── -->
<div class="row g-3 mb-4">
    <!-- Proposal Type pie -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3">
                <i class="bi bi-pie-chart me-1"></i>Proposal Type Breakdown
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-6">
                        <div style="height: 180px; position: relative;">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="small">
                            <?php 
                            $colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6610f2', '#6f42c1', '#fd7e14'];
                            foreach ($typeDist as $i => $row): 
                                $label = $row['proposal_type'];
                                $cnt = $row['cnt'];
                            ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="dot" style="background-color: <?= $colors[$i] ?? '#6c757d' ?>;"></span>
                                <span class="text-muted text-truncate" title="<?= e($label) ?>"><?= e($label) ?></span>
                                <span class="ms-auto fw-bold"><?= $cnt ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Score Distribution (Premium) -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <span class="fw-semibold" style="font-size:0.95rem;color:#1a2a4a">
                    <i class="bi bi-bar-chart-line me-1 text-primary"></i>Score Distribution
                </span>
                <button id="distChartExpandBtn" title="Expand" style="background:none;border:none;padding:6px 8px;border-radius:8px;cursor:pointer;color:#9aa3b0;font-size:1rem;transition:background 0.2s" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='none'">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>
            <div class="card-body pt-2" style="position:relative;">
                <!-- Custom floating tooltip -->
                <div id="distTooltip" style="display:none;position:absolute;z-index:100;background:#fff;border:1px solid #e4eaf6;border-radius:12px;padding:12px 16px;box-shadow:0 8px 24px rgba(0,0,0,0.12);pointer-events:none;min-width:150px;">
                    <div id="distTipRange" style="font-size:0.78rem;font-weight:700;color:#1a2a4a;margin-bottom:4px;"></div>
                    <div id="distTipCount" style="font-size:0.82rem;color:#4f8ef7;font-weight:700;"></div>
                    <div id="distTipPct" style="font-size:0.78rem;color:#9aa3b0;font-weight:600;"></div>
                </div>
                <canvas id="distChart" style="max-height:240px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Score Distribution Fullscreen Modal -->
<div id="distModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,0.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;padding:32px;width:90vw;max-width:900px;max-height:90vh;overflow:auto;position:relative;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fw-bold mb-0" style="color:#1a2a4a"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Score Distribution</h5>
            <button id="distModalClose" style="background:none;border:none;font-size:1.4rem;color:#9aa3b0;cursor:pointer;padding:0 6px;">&times;</button>
        </div>
        <canvas id="distChartModal" style="max-height:500px;"></canvas>
    </div>
</div>

<!-- ── Geographic & Locality Analysis with Side-by-Side Interactive Maps ── -->
<style>
.geo-split-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    overflow: hidden;
}
.geo-split-body {
    display: flex;
    height: 380px;
}
.geo-map-container {
    width: 45%;
    height: 100%;
    position: relative;
    border-right: 1px solid #edf0f4;
}
.geo-stats-container {
    width: 55%;
    height: 100%;
    overflow-y: auto;
    padding: 16px 20px;
}
.locality-item {
    padding: 10px 0;
    border-bottom: 1px solid #f5f7fa;
    transition: background 0.2s;
}
.locality-item:last-child { border-bottom: none; }
.locality-item:hover { background: #fafcff; }
.geo-map-el {
    width: 100%;
    height: 100%;
    background: #f8fafc;
}
@media (max-width: 991.98px) {
    .geo-split-body { display: block; height: auto; }
    .geo-map-container { width: 100%; height: 220px; border-right: none; border-bottom: 1px solid #edf0f4; }
    .geo-stats-container { width: 100%; height: auto; }
}
</style>

<div class="row g-3 mb-4">
    <!-- Card 1: Proposals by Zone (City) -->
    <div class="col-lg-6">
        <div class="card border-0 geo-split-card h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-map me-1 text-primary"></i>Proposals by Zone (City)</span>
                <span class="text-muted smaller">Regional distribution</span>
            </div>
            <div class="geo-split-body">
                <div class="geo-map-container">
                    <div id="zone-mini-map" class="geo-map-el"></div>
                </div>
                <div class="geo-stats-container locality-list">
                    <?php if (empty($cityDist)): ?>
                        <p class="text-muted small py-4 text-center">No zone data available.</p>
                    <?php else: ?>
                        <?php 
                        $zoneColors = ['#4f8ef7', '#7c5cfc', '#22c55e', '#f59e0b', '#ec4899', '#14b8a6'];
                        foreach($cityDist as $i => $c): 
                            $pct = ($stats['total'] > 0) ? ($c['cnt'] / $stats['total'] * 100) : 0;
                            $color = $zoneColors[$i % count($zoneColors)];
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold text-dark"><?= e($c['city']) ?></span>
                                <span class="fw-bold" style="color: <?= $color ?>"><?= $c['cnt'] ?> <span class="text-muted fw-normal smaller">(<?= round($pct,1) ?>%)</span></span>
                            </div>
                            <div class="progress" style="height: 6px; background-color: #f0f2f5; border-radius: 4px;">
                                <div class="progress-bar" style="width: <?= $pct ?>%; border-radius: 4px; background-color: <?= $color ?>;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Top Localities (Region) -->
    <div class="col-lg-6">
        <div class="card border-0 geo-split-card h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-geo-alt me-1 text-success"></i>Top Localities (Region)</span>
                <span class="text-muted smaller">Micro-market density</span>
            </div>
            <div class="geo-split-body">
                <div class="geo-map-container order-lg-2" style="border-right:none; border-left: 1px solid #edf0f4;">
                    <div id="locality-mini-map" class="geo-map-el"></div>
                </div>
                <div class="geo-stats-container locality-list order-lg-1">
                    <?php if (empty($regionDist)): ?>
                        <p class="text-muted small py-4 text-center">No locality data available.</p>
                    <?php else: ?>
                        <?php foreach($regionDist as $r): 
                            $rpct = ($stats['total'] > 0) ? ($r['cnt'] / $stats['total'] * 100) : 0;
                        ?>
                        <div class="locality-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-dark small fw-semibold text-truncate" style="max-width:160px;"><?= e($r['region']) ?></span>
                                <span class="text-muted smaller fw-bold"><?= $r['cnt'] ?></span>
                            </div>
                            <div class="progress" style="height: 4px; background-color: #f0f2f5;">
                                <div class="progress-bar bg-success" style="width: <?= $rpct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Proposal Heatmap (Mumbai) ── -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius:16px; overflow:hidden;">
            <div class="card-header bg-transparent fw-semibold border-0 pt-3 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-geo-alt-fill me-1 text-danger"></i>Proposal Heatmap <span class="text-muted fw-normal">(Mumbai Region)</span></span>
                <span class="badge bg-primary-subtle text-primary"><?= count($heatmapPoints) ?> regions mapped</span>
            </div>
            <div class="card-body p-0" style="position:relative;">
                <div id="proposal-heatmap" style="height:400px; z-index:1;"></div>
                <!-- Tooltip popup -->
                <div id="heatmap-tooltip" style="display:none;position:absolute;z-index:1000;background:#fff;border:1px solid #e0e6f0;border-radius:12px;padding:14px 18px;box-shadow:0 8px 28px rgba(0,0,0,0.13);min-width:170px;pointer-events:none;">
                    <div id="ht-region" style="font-weight:700;font-size:0.95rem;color:#1a2a4a;margin-bottom:6px;"></div>
                    <div style="font-size:0.8rem;color:#6b7a99;" id="ht-proposals"></div>
                    <div style="font-size:0.8rem;color:#7c5cfc;font-weight:700;" id="ht-value"></div>
                    <div style="font-size:0.8rem;color:#22c55e;font-weight:600;" id="ht-score"></div>
                </div>
                <!-- Legend -->
                <div style="position:absolute;bottom:20px;left:20px;z-index:999;background:rgba(255,255,255,0.92);backdrop-filter:blur(4px);border-radius:8px;padding:8px 14px;font-size:0.72rem;font-weight:600;color:#6b7a99;box-shadow:0 2px 8px rgba(0,0,0,0.10);">
                    <div class="mb-1">Proposal Density</div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span>Low</span>
                        <div style="width:100px;height:8px;border-radius:4px;background:linear-gradient(90deg,#00f,#0ff,#0f0,#ff0,#f00);"></div>
                        <span>High</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet + Heatmap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
<script>
(function() {
    var mapEl = document.getElementById('proposal-heatmap');
    if (!mapEl) return;

    var map = L.map('proposal-heatmap', {
        center: [19.09, 72.87],
        zoom: 11,
        zoomControl: true,
        attributionControl: false
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19
    }).addTo(map);

    // Force correct size after render
    setTimeout(function() { map.invalidateSize(); }, 300);

    // Heatmap data from PHP
    var rawPoints = <?= json_encode($heatmapPoints) ?>;
    if (!rawPoints || rawPoints.length === 0) {
        console.warn('Heatmap: No region points matched. Check regionCoords in DashboardController.');
        return;
    }
    var maxCnt = Math.max.apply(null, rawPoints.map(function(p){ return p[2]; })) || 1;

    // Normalized heat layer
    var heatPoints = rawPoints.map(function(p) {
        return [p[0], p[1], Math.max(0.2, p[2] / maxCnt)]; // min 0.2 so even sparse regions show color
    });

    L.heatLayer(heatPoints, {
        radius: 45,
        blur: 30,
        max: 1.0,
        gradient: {0.0: '#0000cc', 0.3: '#00ccff', 0.5: '#00ff88', 0.7: '#ffff00', 1.0: '#ff2200'}
    }).addTo(map);

    <?php
    // Build region name array matching heatmapPoints order using same case-insensitive key
    $heatmapRegionNames = [];
    foreach ($heatmapPoints as $pt) {
        $matched = '';
        foreach ($allRegionDist as $r) {
            $key = strtolower(trim($r['region']));
            if (isset($regionCoords[$key]) &&
                abs($regionCoords[$key][0] - $pt[0]) < 0.0001 &&
                abs($regionCoords[$key][1] - $pt[1]) < 0.0001) {
                $matched = $r['region'];
                break;
            }
        }
        $heatmapRegionNames[] = $matched;
    }
    ?>
    var regionNames = <?= json_encode($heatmapRegionNames) ?>;
    var tooltip     = document.getElementById('heatmap-tooltip');
    var mapContainer = document.getElementById('proposal-heatmap');

    rawPoints.forEach(function(p, i) {
        var rName = regionNames[i] || '';
        var cnt   = p[2];
        var icon  = L.divIcon({
            className: '',
            html: '<div style="background:rgba(255,255,255,0.90);border:1.5px solid #c8d6f0;border-radius:20px;padding:3px 10px;font-size:0.68rem;font-weight:700;color:#1a2a4a;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.13);line-height:1.4">'
                  + rName + '<br><span style="color:#4f8ef7;font-size:0.75rem">' + cnt + '</span></div>',
            iconAnchor: [40, 10]
        });
        var marker = L.marker([p[0], p[1]], {icon: icon}).addTo(map);

        marker.on('mouseover', function() {
            document.getElementById('ht-region').textContent    = rName;
            document.getElementById('ht-proposals').textContent = cnt + ' Proposals';
            document.getElementById('ht-value').textContent     = '';
            document.getElementById('ht-score').textContent     = '';
            var containerPt = map.latLngToContainerPoint([p[0], p[1]]);
            tooltip.style.left    = (containerPt.x + 16) + 'px';
            tooltip.style.top     = (containerPt.y - 40) + 'px';
            tooltip.style.display = 'block';
        });
        marker.on('mouseout', function() { tooltip.style.display = 'none'; });
    });
})();
</script>

<!-- ── Top Scored Proposals (Premium list view) ── -->
<style>
.proposal-list-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    padding: 20px 24px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
}
.proposal-list-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}
.rank-badge {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 800;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    position: relative;
}
.rank-badge.top-1 { background: #fffbeb; border-color: #f59e0b; color: #d97706; }
.rank-badge.top-2 { background: #f8fafc; border-color: #94a3b8; color: #475569; }
.rank-badge.top-3 { background: #fff7ed; border-color: #ea580c; color: #c2410c; }

/* Medal icon overlay for top 3 */
.rank-badge.top-1::after,
.rank-badge.top-2::after,
.rank-badge.top-3::after {
    content: "★";
    position: absolute;
    bottom: -6px;
    right: -4px;
    font-size: 0.7rem;
}
.rank-badge.top-1::after { color: #f59e0b; }
.rank-badge.top-2::after { color: #94a3b8; }
.rank-badge.top-3::after { color: #ea580c; }

/* Circular Ring Charts */
.circle-chart {
    position: relative;
    width: 58px;
    height: 58px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.circle-chart-text {
    position: absolute;
    font-size: 0.72rem;
    font-weight: 700;
    text-align: center;
    line-height: 1;
}

.delta-lbl {
    font-size: 0.65rem;
    font-weight: 600;
    margin-top: 3px;
    text-align: center;
}

/* Insight Box */
.insight-box {
    background: #f8faff;
    border-radius: 12px;
    padding: 10px 14px;
    border: 1px solid #eef2ff;
    max-width: 250px;
    font-size: 0.75rem;
}

/* Layout Columns */
.prop-meta-col { width: 220px; }
.prop-region-col { width: 100px; text-align: center; }
.prop-score-col { width: 70px; display: flex; flex-direction: column; align-items: center; }
.prop-prob-col { width: 70px; display: flex; flex-direction: column; align-items: center; }
.prop-value-col { width: 130px; text-align: center; }
.prop-cat-col { width: 110px; text-align: center; }
.prop-insight-col { width: 260px; }
.prop-action-col { width: 44px; text-align: right; }

@media (max-width: 1200px) {
    .proposal-list-card { flex-wrap: wrap; gap: 16px; }
    .prop-insight-col { width: 100%; }
}
</style>

<div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
    <div class="card-header bg-transparent fw-semibold border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-trophy-fill me-2 text-warning"></i>Top Scored Proposals</span>
        <a href="index.php?page=proposals&sort=total_score&dir=DESC&stage1_status=eligible" class="btn btn-sm btn-outline-primary px-3" style="border-radius:8px;">View All</a>
    </div>
    
    <div class="card-body px-4 pb-4 pt-2">
        <!-- Column header indicators -->
        <div class="d-none d-xl-flex justify-content-between text-muted px-4 mb-2" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">
            <div style="width: 38px;">#</div>
            <div class="prop-meta-col">Proposal</div>
            <div class="prop-region-col">Region</div>
            <div class="prop-score-col">Score</div>
            <div class="prop-prob-col">Probability</div>
            <div class="prop-value-col">Potential Value</div>
            <div class="prop-cat-col">Category</div>
            <div class="prop-insight-col">AI Insight</div>
            <div class="prop-action-col">Action</div>
        </div>

        <?php if (empty($topScored)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-folder-x display-6 mb-2"></i>
                <div>No scored proposals yet. Import or add proposals to begin.</div>
            </div>
        <?php else: $rank = 1; foreach ($topScored as $p): 
            $topClass = $rank <= 3 ? 'top-' . $rank : '';
            $score = (float)$p['total_score'];
            $prob = (float)$p['probability_pct'];
            
            // Calculate potential value (Opportunity Value)
            $plotArea = (float)($p['plot_area_sqm'] ?? 0);
            $priceSqft = (float)($p['avg_sale_price_sqft'] ?? 0);
            $oppValueCr = ($plotArea * 10.764 * $priceSqft) / 10000000;
            
            // Dynamic insight generator
            $insights = [];
            if ($oppValueCr > 500) $insights[] = "High opportunity value";
            if ($score >= 70) $insights[] = "Excellent FSI potential";
            else if ($score >= 50) $insights[] = "Viable development metrics";
            else $insights[] = "Low FSI limits";

            if ($prob >= 80) $insights[] = "strong market demand";
            else if ($prob >= 50) $insights[] = "moderate risk factor";
            else $insights[] = "complex approval approvals";

            $insightText = ucfirst(implode(" with ", $insights)) . ".";
        ?>
        <div class="proposal-list-card gap-2 px-3 py-3 align-items-center">
            
            <!-- Rank Column -->
            <div class="rank-badge <?= $topClass ?>"><?= $rank++ ?></div>

            <!-- Metadata Column -->
            <div class="prop-meta-col">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fw-bold text-dark text-truncate" style="font-size:0.9rem;" title="<?= e($p['proposal_name']) ?>"><?= e($p['proposal_name']) ?></span>
                    <?php if ($score >= 70): ?>
                        <span class="badge bg-success-subtle text-success d-flex align-items-center gap-1" style="font-size:0.6rem;"><i class="bi bi-stars"></i> AI Pick</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted smaller mb-1"><i class="bi bi-geo-alt-fill me-1"></i><?= e($p['site_address'] ?: 'Mumbai, Maharashtra') ?></div>
                <span class="badge bg-primary-subtle text-primary" style="font-size:0.65rem; font-weight:600;"><?= e($p['proposal_type'] ?: 'Redevelopment') ?></span>
            </div>

            <!-- Region Column -->
            <div class="prop-region-col">
                <span class="text-dark small fw-semibold"><?= e($p['region']) ?></span>
            </div>

            <!-- Score Circle Column -->
            <div class="prop-score-col">
                <div class="circle-chart">
                    <svg viewBox="0 0 36 36" width="48" height="48">
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#edf2f7" stroke-width="2.5"/>
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#3b82f6" stroke-width="2.5"
                                stroke-dasharray="<?= $score ?> 100" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                    </svg>
                    <div class="circle-chart-text">
                        <div class="fw-bold" style="font-size:0.75rem; color:#1e293b;"><?= number_format($score, 1) ?></div>
                        <div class="text-muted" style="font-size:0.5rem;">/ 100</div>
                    </div>
                </div>
                <?php $scoreDelta = round($score * 0.1, 1); // Mocked delta ?>
                <div class="delta-lbl text-success"><i class="bi bi-arrow-up-short"></i> <?= $scoreDelta ?>%</div>
            </div>

            <!-- Probability Circle Column -->
            <div class="prop-prob-col">
                <div class="circle-chart">
                    <svg viewBox="0 0 36 36" width="48" height="48">
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#edf2f7" stroke-width="2.5"/>
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#22c55e" stroke-width="2.5"
                                stroke-dasharray="<?= $prob ?> 100" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                    </svg>
                    <div class="circle-chart-text">
                        <div class="fw-bold" style="font-size:0.7rem; color:#1e293b;"><?= number_format($prob, 1) ?>%</div>
                    </div>
                </div>
                <?php $probDelta = round($prob * 0.12, 1); ?>
                <div class="delta-lbl text-success"><i class="bi bi-arrow-up-short"></i> <?= $probDelta ?>%</div>
            </div>

            <!-- Potential Value Column -->
            <div class="prop-value-col">
                <div class="fw-extrabold text-dark" style="font-size:1.05rem;">&#8377;<?= $oppValueCr > 0 ? number_format($oppValueCr, 0) : '—' ?><span style="font-size:0.75rem; font-weight:600; color:#64748b"> Cr</span></div>
                <div class="text-muted smaller mb-1">Est. Value</div>
                <svg viewBox="0 0 80 20" width="80" height="20">
                    <path d="M 0 16 Q 20 8 40 12 T 80 4" fill="none" stroke="#22c55e" stroke-width="2"/>
                </svg>
            </div>

            <!-- Category Column -->
            <div class="prop-cat-col">
                <?php $colours = CATEGORY_COLOURS; $cat = $p['category'] ?? 'Low'; ?>
                <span class="badge bg-<?= $colours[$cat] ?? 'secondary' ?>-subtle text-<?= $colours[$cat] ?? 'secondary' ?> px-2.5 py-1.5" style="border-radius:20px; font-size:0.7rem; font-weight:700;">
                    <?= e($cat) ?>
                </span>
            </div>

            <!-- AI Insight Column -->
            <div class="prop-insight-col">
                <div class="insight-box">
                    <div class="text-dark fw-semibold mb-1" style="line-height:1.3; font-size:0.72rem;"><i class="bi bi-robot text-primary me-1"></i><?= $insightText ?></div>
                    <div class="text-primary fw-bold" style="font-size:0.65rem;">AI Confidence: <?= round($prob * 1.05 > 99 ? 99 : $prob * 1.05) ?>%</div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="prop-action-col">
                <a href="index.php?page=proposal_detail&id=<?= $p['id'] ?>" class="btn btn-light btn-sm border-0 d-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:10px; background:#f8fafc;">
                    <i class="bi bi-chevron-right text-primary" style="font-size: 1rem;"></i>
                </a>
            </div>

        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ── Premium Filter and Decision Summaries (Mockup Style) ── -->
<style>
.summary-widget-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #f0f2f5;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    height: 100%;
}
.summary-widget-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 24px;
}
.header-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.header-icon-box.filter { background: #eff6ff; color: #3b82f6; }
.header-icon-box.decision { background: #f0fdf4; color: #22c55e; }

.header-dropdown {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Flex Split Layout */
.summary-widget-body {
    display: flex;
    gap: 16px;
    align-items: center;
    min-height: 250px;
}
.body-left-graphic {
    width: 45%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.body-right-stats {
    width: 55%;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Stat row cards */
.stat-row-tile {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: box-shadow 0.2s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.stat-row-tile:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.stat-tile-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-tile-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.stat-tile-val {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.1;
}

.trend-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.trend-badge.up { background: #f0fdf4; color: #22c55e; }
.trend-badge.down { background: #fef2f2; color: #ef4444; }
.trend-badge.neutral { background: #f1f5f9; color: #64748b; }

/* SVG Funnel Slicing */
.funnel-graphic-wrap {
    width: 100%;
    max-width: 160px;
    height: auto;
}

/* Donut text overlay */
.donut-center-lbl {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.2;
}

/* Footer row bar */
.widget-footer-bar {
    background: #f8fafc;
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.widget-footer-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.78rem;
    color: #475569;
}

.widget-action-btn {
    width: 100%;
    background: none;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #4f8ef7;
    text-align: center;
    text-decoration: none;
    display: block;
    margin-top: 16px;
    transition: background 0.2s, border-color 0.2s;
}
.widget-action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #3b82f6;
}

@media (max-width: 768px) {
    .summary-widget-body { display: block; }
    .body-left-graphic, .body-right-stats { width: 100%; }
    .body-left-graphic { margin-bottom: 16px; }
}
</style>

<div class="row g-3 mb-4">
    <!-- Card 1: Stage 1 Filter Summary -->
    <div class="col-lg-6">
        <div class="summary-widget-card">
            
            <!-- Header -->
            <div class="summary-widget-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon-box filter"><i class="bi bi-funnel"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0" style="color:#1e293b;">Stage 1 Filter Summary</h6>
                        <span class="text-muted smaller">Track proposals through Stage 1 filtering process</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="header-dropdown"><i class="bi bi-calendar3"></i> This Month <i class="bi bi-chevron-down"></i></div>
                    <button style="background:none; border:none; color:#94a3b8;"><i class="bi bi-three-dots-vertical"></i></button>
                </div>
            </div>

            <!-- Body -->
            <div class="summary-widget-body">
                <!-- Graphic left -->
                <div class="body-left-graphic">
                    <div class="funnel-graphic-wrap">
                        <svg viewBox="0 0 160 180" width="100%">
                            <!-- Floating icon circles top -->
                            <circle cx="35" cy="20" r="10" fill="#f0fdf4" stroke="#22c55e" stroke-width="1"/>
                            <path d="M32 20 L35 23 L38 17" fill="none" stroke="#22c55e" stroke-width="1.2"/>
                            
                            <circle cx="70" cy="12" r="10" fill="#eff6ff" stroke="#3b82f6" stroke-width="1"/>
                            <path d="M67 15 L73 15 M70 12 L70 16" fill="none" stroke="#3b82f6" stroke-width="1.2"/>

                            <circle cx="120" cy="22" r="10" fill="#fdf2fa" stroke="#ec4899" stroke-width="1"/>
                            <path d="M117 22 H123" fill="none" stroke="#ec4899" stroke-width="1.2"/>

                            <!-- Sliced Funnel polygon shapes -->
                            <!-- Slice 1 (Top Blue - Passed) -->
                            <polygon points="15,45 145,45 125,90 35,90" fill="url(#blueFunnelGrad)"/>
                            <!-- Slice 2 (Middle Red - Rejected) -->
                            <polygon points="38,95 122,95 106,135 54,135" fill="url(#redFunnelGrad)"/>
                            <!-- Slice 3 (Bottom Light - Pending) -->
                            <polygon points="56,140 104,140 92,175 68,175" fill="url(#whiteFunnelGrad)"/>
                            
                            <!-- Gradients -->
                            <defs>
                                <linearGradient id="blueFunnelGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#60a5fa"/>
                                    <stop offset="100%" stop-color="#2563eb"/>
                                </linearGradient>
                                <linearGradient id="redFunnelGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f87171"/>
                                    <stop offset="100%" stop-color="#dc2626"/>
                                </linearGradient>
                                <linearGradient id="whiteFunnelGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f8fafc"/>
                                    <stop offset="100%" stop-color="#cbd5e1"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                </div>

                <!-- Stats right -->
                <div class="body-right-stats">
                    
                    <!-- Passed -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="dot" style="background:#3b82f6;"></span>
                            <div>
                                <div class="text-muted smaller">Stage 1 Passed</div>
                                <div class="stat-tile-val text-dark"><?= number_format($stats['eligible'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="trend-badge up"><i class="bi bi-caret-up-fill"></i> 12.4%</span>
                            <span class="text-muted smaller">vs last month</span>
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="dot" style="background:#ef4444;"></span>
                            <div>
                                <div class="text-muted smaller">Stage 1 Rejected</div>
                                <div class="stat-tile-val text-dark"><?= number_format($stats['rejected'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="trend-badge down"><i class="bi bi-caret-up-fill"></i> 8.7%</span>
                            <span class="text-muted smaller">vs last month</span>
                        </div>
                    </div>

                    <!-- Pending -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="dot" style="background:#94a3b8;"></span>
                            <div>
                                <div class="text-muted smaller">Pending Review</div>
                                <div class="stat-tile-val text-dark"><?= number_format($stats['pending_s1'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="trend-badge neutral">— 0%</span>
                            <span class="text-muted smaller">vs last month</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer -->
            <?php
            $tot = max(1, ($stats['total'] ?? 0));
            $passRate = round((($stats['eligible'] ?? 0) / $tot) * 100, 1);
            ?>
            <div class="widget-footer-bar">
                <div class="widget-footer-left">
                    <i class="bi bi-stars text-primary" style="font-size:1.15rem"></i>
                    <div>
                        <div class="fw-bold text-dark">Stage 1 Conversion Rate</div>
                        <div class="text-muted small"><?= $passRate ?>% of proposals passed Stage 1</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <svg viewBox="0 0 60 20" width="60" height="20">
                        <path d="M0,15 Q15,5 30,12 T60,3" fill="none" stroke="#3b82f6" stroke-width="1.8"/>
                    </svg>
                    <span class="trend-badge up">▲ 4.2%</span>
                </div>
            </div>

            <a href="index.php?page=proposals&stage1_status=eligible" class="widget-action-btn">View Stage 1 Analytics <i class="bi bi-chevron-right ms-1"></i></a>
        </div>
    </div>

    <!-- Card 2: User Review Decision Summary -->
    <div class="col-lg-6">
        <div class="summary-widget-card">
            
            <!-- Header -->
            <div class="summary-widget-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon-box decision"><i class="bi bi-check-square"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0" style="color:#1e293b;">User Review Decision Summary</h6>
                        <span class="text-muted smaller">Overview of user decisions on reviewed proposals</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="header-dropdown"><i class="bi bi-calendar3"></i> This Month <i class="bi bi-chevron-down"></i></div>
                    <button style="background:none; border:none; color:#94a3b8;"><i class="bi bi-three-dots-vertical"></i></button>
                </div>
            </div>

            <!-- Body -->
            <div class="summary-widget-body">
                <!-- Graphic left -->
                <div class="body-left-graphic">
                    <div class="circle-chart" style="width:140px; height:140px;">
                        <svg viewBox="0 0 36 36" width="130" height="130">
                            <!-- Base Ring -->
                            <circle cx="18" cy="18" r="16" fill="none" stroke="#f1f5f9" stroke-width="3"/>
                            <!-- Segment 1: Pending (Yellow) -->
                            <?php
                            $totalDec = ($stats['dec_pending']??0) + ($stats['dec_accepted']??0) + ($stats['dec_rejected']??0);
                            $totalDec = max(1, $totalDec);
                            $pctPend = (($stats['dec_pending']??0) / $totalDec) * 100;
                            $pctAcc = (($stats['dec_accepted']??0) / $totalDec) * 100;
                            $pctRej = (($stats['dec_rejected']??0) / $totalDec) * 100;
                            ?>
                            <circle cx="18" cy="18" r="16" fill="none" stroke="#f59e0b" stroke-width="3"
                                    stroke-dasharray="<?= $pctPend ?> 100" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                            <!-- Segment 2: Accepted (Green) -->
                            <circle cx="18" cy="18" r="16" fill="none" stroke="#10b981" stroke-width="3"
                                    stroke-dasharray="<?= $pctAcc ?> 100" stroke-linecap="round" transform="rotate(<?= ($pctPend/100)*360 - 90 ?> 18 18)"/>
                        </svg>
                        <div class="donut-center-lbl">
                            <span class="text-dark fw-extrabold" style="font-size:1.55rem;"><?= number_format($stats['dec_pending'] ?? 0) ?></span>
                            <span class="text-muted smaller" style="font-size:0.58rem; font-weight:600;">Total<br>Pending Review</span>
                        </div>
                    </div>
                </div>

                <!-- Stats right -->
                <div class="body-right-stats">
                    
                    <!-- Review Pending -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="stat-tile-icon" style="background:#fffbeb; color:#d97706;"><i class="bi bi-hourglass-split"></i></span>
                            <div>
                                <div class="text-muted smaller">Review Pending</div>
                                <div class="stat-tile-val" style="color:#d97706;"><?= number_format($stats['dec_pending'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="fw-bold text-warning" style="font-size:0.8rem;"><?= round($pctPend, 1) ?>%</span>
                            <svg viewBox="0 0 50 12" width="50" height="12">
                                <path d="M0,8 Q12,2 25,6 T50,4" fill="none" stroke="#f59e0b" stroke-width="1.2"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Accepted -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="stat-tile-icon" style="background:#ecfdf5; color:#10b981;"><i class="bi bi-check-circle-fill"></i></span>
                            <div>
                                <div class="text-muted smaller">Accepted</div>
                                <div class="stat-tile-val text-success"><?= number_format($stats['dec_accepted'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="fw-bold text-success" style="font-size:0.8rem;"><?= round($pctAcc, 1) ?>%</span>
                            <line x1="0" y1="6" x2="50" y2="6" stroke="#10b981" stroke-width="1.2"/>
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div class="stat-row-tile">
                        <div class="stat-tile-left">
                            <span class="stat-tile-icon" style="background:#fef2f2; color:#ef4444;"><i class="bi bi-x-circle-fill"></i></span>
                            <div>
                                <div class="text-muted smaller">Rejected</div>
                                <div class="stat-tile-val text-danger"><?= number_format($stats['dec_rejected'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="fw-bold text-danger" style="font-size:0.8rem;"><?= round($pctRej, 1) ?>%</span>
                            <line x1="0" y1="6" x2="50" y2="6" stroke="#ef4444" stroke-width="1.2"/>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer -->
            <?php
            $resolved = ($stats['dec_accepted']??0) + ($stats['dec_rejected']??0);
            $totalReviews = $totalDec;
            $decisionEfficiency = round(($resolved / $totalReviews) * 100, 1);
            ?>
            <div class="widget-footer-bar">
                <div class="widget-footer-left">
                    <i class="bi bi-lightning-charge text-indigo" style="color:#6366f1; font-size:1.15rem"></i>
                    <div>
                        <div class="fw-bold text-dark">Decision Efficiency</div>
                        <div class="text-muted small"><?= $decisionEfficiency ?>% of pending reviews have been resolved</div>
                    </div>
                </div>
                <!-- Circular progress mini-indicator -->
                <div class="circle-chart" style="width:36px; height:36px;">
                    <svg viewBox="0 0 36 36" width="32" height="32">
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#f1f5f9" stroke-width="3"/>
                        <circle cx="18" cy="18" r="16" fill="none" stroke="#6366f1" stroke-width="3"
                                stroke-dasharray="<?= $decisionEfficiency ?> 100" transform="rotate(-90 18 18)"/>
                    </svg>
                    <div class="circle-chart-text" style="font-size:0.5rem; color:#6366f1; font-weight:800;"><?= $decisionEfficiency ?>%</div>
                </div>
            </div>

            <a href="index.php?page=proposals&status=Accepted" class="widget-action-btn">View Review Analytics <i class="bi bi-chevron-right ms-1"></i></a>
        </div>
    </div>
</div>

<script>
// Proposal Type pie chart
const typeCtx = document.getElementById('typeChart');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($typeLabels) ?>,
            datasets: [{
                data: <?= json_encode($typeValues) ?>,
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6610f2', '#6f42c1', '#fd7e14'],
                borderWidth: 0,
            }]
        },
        options: { 
            plugins: { legend: { display: false } },
            maintainAspectRatio: false,
            responsive: true
        }
    });
}

// ── Score Distribution (Premium) ──────────────────────────────────────
(function() {
    var distLabels = <?= json_encode($distLabels) ?>;
    var distValues = <?= json_encode($distValues) ?>;
    var totalProposals = distValues.reduce(function(a,b){ return a+b; }, 0) || 1;

    function makeGradient(ctx, chartArea) {
        var gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        gradient.addColorStop(0,   'rgba(79,142,247,0.92)');
        gradient.addColorStop(0.5, 'rgba(79,142,247,0.55)');
        gradient.addColorStop(1,   'rgba(79,142,247,0.08)');
        return gradient;
    }
    function makeHoverGradient(ctx, chartArea) {
        var gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        gradient.addColorStop(0,   'rgba(47,108,220,1.0)');
        gradient.addColorStop(0.5, 'rgba(47,108,220,0.75)');
        gradient.addColorStop(1,   'rgba(47,108,220,0.15)');
        return gradient;
    }

    var distTooltip   = document.getElementById('distTooltip');
    var distTipRange  = document.getElementById('distTipRange');
    var distTipCount  = document.getElementById('distTipCount');
    var distTipPct    = document.getElementById('distTipPct');

    // Custom external tooltip
    function customTooltip(context) {
        var tooltip = context.tooltip;
        if (tooltip.opacity === 0) {
            distTooltip.style.display = 'none';
            return;
        }
        var idx   = tooltip.dataPoints[0].dataIndex;
        var count = distValues[idx];
        var pct   = ((count / totalProposals) * 100).toFixed(1);
        var range = distLabels[idx] + ' Score Range';
        distTipRange.textContent = range;
        distTipCount.textContent = 'Count: ' + count.toLocaleString();
        distTipPct.textContent   = pct + '%';

        var canvasRect = context.chart.canvas.getBoundingClientRect();
        var cardBody   = distTooltip.parentElement.getBoundingClientRect();
        var x = tooltip.caretX + 12;
        var y = tooltip.caretY - 20;
        // Flip left if near right edge
        if (x + 160 > cardBody.width) x = tooltip.caretX - 170;
        distTooltip.style.left    = x + 'px';
        distTooltip.style.top     = y + 'px';
        distTooltip.style.display = 'block';
    }

    function buildDistChart(canvasId, maxH) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: distLabels,
                datasets: [{
                    label: 'Proposals',
                    data: distValues,
                    backgroundColor: function(context) {
                        var chart = context.chart;
                        var ca = chart.chartArea;
                        if (!ca) return 'rgba(79,142,247,0.6)';
                        return makeGradient(chart.ctx, ca);
                    },
                    hoverBackgroundColor: function(context) {
                        var chart = context.chart;
                        var ca = chart.chartArea;
                        if (!ca) return 'rgba(47,108,220,0.9)';
                        return makeHoverGradient(chart.ctx, ca);
                    },
                    borderWidth: 0,
                    borderRadius: { topLeft: 6, topRight: 6 },
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: canvasId !== 'distChart', // only native tooltip for modal
                        external: canvasId === 'distChart' ? customTooltip : undefined,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, family: 'Inter,system-ui,sans-serif' }, color: '#9aa3b0' },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f4fa', drawBorder: false },
                        ticks: { font: { size: 11, family: 'Inter,system-ui,sans-serif' }, color: '#9aa3b0', stepSize: Math.ceil(Math.max(...distValues) / 5) || 1 },
                        border: { display: false }
                    }
                },
                onHover: function(event, elements) {
                    if (canvasId === 'distChart') {
                        event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                    }
                }
            }
        });
    }

    var mainDistChart = buildDistChart('distChart', 240);

    // Expand button
    var expandBtn  = document.getElementById('distChartExpandBtn');
    var modal      = document.getElementById('distModal');
    var modalClose = document.getElementById('distModalClose');
    var modalChart = null;

    if (expandBtn && modal) {
        expandBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
            if (!modalChart) {
                modalChart = buildDistChart('distChartModal', 500);
            } else {
                modalChart.update();
            }
        });
        modalClose.addEventListener('click', function() { modal.style.display = 'none'; });
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.style.display = 'none'; });
    }
})();

// ── Proposals by Zone (City) Map Setup ──────────────────────────────────
(function() {
    var zoneMapEl = document.getElementById('zone-mini-map');
    if (!zoneMapEl) return;

    var map = L.map('zone-mini-map', {
        center: [19.06, 72.88],
        zoom: 9.5,
        zoomControl: false,
        attributionControl: false,
        scrollWheelZoom: false,
        dragging: false
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);

    // Coordinate boundaries (Polygons) for clean map divisions
    var zonePolygons = {
        'Western': [
            [19.04, 72.82], [19.09, 72.82], [19.16, 72.80], [19.24, 72.85],
            [19.24, 72.88], [19.14, 72.87], [19.06, 72.84]
        ],
        'Eastern': [
            [19.04, 72.89], [19.11, 72.90], [19.19, 72.94], [19.18, 72.98],
            [19.09, 72.94], [19.04, 72.91]
        ],
        'South': [
            [19.00, 72.81], [19.04, 72.82], [19.04, 72.86], [19.00, 72.85]
        ],
        'South Bombay': [
            [18.89, 72.80], [18.96, 72.79], [19.00, 72.81], [18.99, 72.84],
            [18.89, 72.83]
        ],
        'Mumbai': [
            [19.04, 72.84], [19.08, 72.84], [19.08, 72.89], [19.04, 72.89]
        ],
        'Thane': [
            [19.18, 72.95], [19.25, 72.94], [19.27, 72.99], [19.19, 73.01]
        ],
        'Karjat': [
            [18.88, 73.30], [18.93, 73.30], [18.93, 73.35], [18.88, 73.35]
        ],
        'Pune': [
            [18.49, 73.81], [18.56, 73.81], [18.56, 73.89], [18.49, 73.89]
        ],
        'Talegaon': [
            [18.70, 73.65], [18.75, 73.65], [18.75, 73.71], [18.70, 73.71]
        ],
        'Lonavala': [
            [18.72, 73.37], [18.77, 73.37], [18.77, 73.43], [18.72, 73.43]
        ]
    };

    var cityDist = <?= json_encode($cityDist) ?>;
    var zoneColors = ['#4f8ef7', '#7c5cfc', '#22c55e', '#f59e0b', '#ec4899', '#14b8a6'];

    cityDist.forEach(function(c, i) {
        var zName = c.city;
        var polygonPath = zonePolygons[zName];
        if (polygonPath) {
            var color = zoneColors[i % zoneColors.length];
            L.polygon(polygonPath, {
                color: color,
                fillColor: color,
                fillOpacity: 0.35,
                weight: 2
            }).addTo(map).bindTooltip(zName + ': ' + c.cnt + ' proposals', {permanent: false, direction: 'center'});
        }
    });

    setTimeout(function() { map.invalidateSize(); }, 300);
})();

// ── Top Localities (Region) Map Setup ───────────────────────────────────
(function() {
    var locMapEl = document.getElementById('locality-mini-map');
    if (!locMapEl) return;

    var map = L.map('locality-mini-map', {
        center: [19.09, 72.85],
        zoom: 10,
        zoomControl: false,
        attributionControl: false,
        scrollWheelZoom: false,
        dragging: false
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);

    // Grab coordinates from parent controller's regionCoords map
    var regionCoords = <?= json_encode($regionCoords) ?>;
    var regionDist = <?= json_encode($regionDist) ?>;

    regionDist.forEach(function(r) {
        var rName = r.region;
        var key = rName.toLowerCase().trim();
        var coord = regionCoords[key];
        if (coord) {
            L.circleMarker(coord, {
                radius: 6,
                color: '#22c55e',
                fillColor: '#22c55e',
                fillOpacity: 0.8,
                weight: 2
            }).addTo(map).bindTooltip(rName + ': ' + r.cnt + ' proposals', {permanent: false, direction: 'top'});
        }
    });

    setTimeout(function() { map.invalidateSize(); }, 300);
})();
</script>

<!-- ── Floating AI Chatbot Widget ── -->
<style>
.ai-chat-trigger {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.35);
    z-index: 1050;
    transition: transform 0.2s;
}
.ai-chat-trigger:hover {
    transform: scale(1.05);
}
.ai-chat-panel {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 380px;
    height: 520px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    z-index: 1050;
    overflow: hidden;
    transform: translateY(20px);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.ai-chat-panel.open {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.ai-chat-header {
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: #ffffff;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ai-chat-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chat-message {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 0.8rem;
    line-height: 1.4;
}
.chat-message.ai {
    background: #ffffff;
    color: #334155;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
}
.chat-message.user {
    background: #2563eb;
    color: #ffffff;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.ai-chat-footer {
    padding: 12px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
}
.chat-suggestion-chip {
    background: #f1f5f9;
    border: none;
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: background 0.2s;
    margin-right: 6px;
    margin-bottom: 6px;
}
.chat-suggestion-chip:hover {
    background: #e2e8f0;
}
</style>

<!-- Trigger Button -->
<div class="ai-chat-trigger" id="chatTriggerBtn" title="Ask AI Assistant">
    <i class="bi bi-robot"></i>
</div>

<!-- Chat Interface window -->
<div class="ai-chat-panel" id="chatPanelContainer">
    <div class="ai-chat-header">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-stars text-indigo"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0" style="font-size:0.85rem;">BDvault AI Chatbot</h6>
                <span class="smaller opacity-75">Online</span>
            </div>
        </div>
        <button class="btn btn-close btn-close-white" id="chatPanelCloseBtn" aria-label="Close" style="font-size:0.75rem;"></button>
    </div>

    <!-- Messages Log Area -->
    <div class="ai-chat-body" id="chatMsgBody">
        <div class="chat-message ai">
            Hi! I am the BDvault AI Assistant. Ask me anything about your proposals, regions, or classification metrics.
        </div>
    </div>

    <!-- Footer Input & Suggestions -->
    <div class="ai-chat-footer">
        <div class="d-flex flex-wrap mb-2" id="chatSuggestionsBox">
            <button type="button" class="chat-suggestion-chip" onclick="sendSuggestion('Which proposals are High Potential?')">Show High Potential</button>
            <button type="button" class="chat-suggestion-chip" onclick="sendSuggestion('Count proposals by type')">Count by Type</button>
        </div>
        <form class="input-group input-group-sm" id="chatbotForm">
            <input type="text" id="chatbotInput" class="form-control" placeholder="Ask AI a question..." style="border-radius: 8px 0 0 8px;" required>
            <button class="btn btn-primary" id="chatbotSubmitBtn" type="submit" style="border-radius: 0 8px 8px 0;"><i class="bi bi-send-fill"></i></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('chatTriggerBtn');
    const panel = document.getElementById('chatPanelContainer');
    const closeBtn = document.getElementById('chatPanelCloseBtn');
    const form = document.getElementById('chatbotForm');
    const input = document.getElementById('chatbotInput');
    const submitBtn = document.getElementById('chatbotSubmitBtn');

    if (trigger && panel) {
        trigger.addEventListener('click', () => {
            panel.classList.toggle('open');
            scrollToBottom();
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                panel.classList.remove('open');
            });
        }
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleChatSubmit();
        });
    }

    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleChatSubmit();
            }
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleChatSubmit();
        });
    }

    // Auto-open chatbot if hash links match
    if (window.location.hash === '#openAiChat' && panel) {
        setTimeout(() => {
            panel.classList.add('open');
            scrollToBottom();
        }, 600);
    }

    // Horizontal BD Intelligence Assistant Widget handler
    const topInput = document.getElementById('ai_chat_input');
    const topBtn = document.getElementById('btn_ai_chat');
    
    if (topBtn && topInput) {
        const handleTopSubmit = async function() {
            const query = topInput.value.trim();
            if (!query) return;

            const textSpan = document.getElementById('ai_chat_btn_text');
            const spinner = document.getElementById('ai_chat_spinner');
            const responseDiv = document.getElementById('ai_chat_response');

            // Show loading state
            if (spinner) spinner.classList.remove('d-none');
            if (textSpan) textSpan.classList.add('d-none');
            if (responseDiv) {
                responseDiv.style.display = 'none';
                responseDiv.textContent = '';
            }

            try {
                const res = await fetch('index.php?page=api_ai_chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question: query })
                });
                const result = await res.json();

                if (responseDiv) {
                    responseDiv.style.display = 'block';
                    if (result.success && result.answer) {
                        responseDiv.textContent = result.answer;
                    } else if (result.success && result.data) {
                        let formatted = "";
                        if (Array.isArray(result.data)) {
                            result.data.forEach((row, i) => {
                                formatted += `[${i+1}] ` + Object.entries(row).map(([k, v]) => `${k}: ${v}`).join(', ') + "\n";
                            });
                        } else {
                            formatted = JSON.stringify(result.data, null, 2);
                        }
                        responseDiv.textContent = formatted;
                    } else {
                        responseDiv.textContent = result.error || "Could not find an answer.";
                    }
                }
            } catch (err) {
                if (responseDiv) {
                    responseDiv.style.display = 'block';
                    responseDiv.textContent = "Error connecting to AI: " + err.message;
                }
            } finally {
                if (spinner) spinner.classList.add('d-none');
                if (textSpan) textSpan.classList.remove('d-none');
            }
        };

        topBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleTopSubmit();
        });

        topInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleTopSubmit();
            }
        });
    }
});

function scrollToBottom() {
    const body = document.getElementById('chatMsgBody');
    if (body) {
        body.scrollTop = body.scrollHeight;
    }
}

function appendMessage(sender, text) {
    const body = document.getElementById('chatMsgBody');
    if (!body) return;

    const msg = document.createElement('div');
    msg.className = `chat-message ${sender}`;
    msg.textContent = text;
    body.appendChild(msg);
    scrollToBottom();
}

function sendSuggestion(text) {
    const input = document.getElementById('chatbotInput');
    if (input) {
        input.value = text;
        const form = document.getElementById('chatbotForm');
        if (form) {
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    }
}

async function handleChatSubmit() {
    const input = document.getElementById('chatbotInput');
    if (!input) return;

    const query = input.value.trim();
    if (!query) return;

    // Display user question
    appendMessage('user', query);
    input.value = '';

    // Loader bubble
    const loader = document.createElement('div');
    loader.className = 'chat-message ai text-muted';
    loader.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>AI is processing...';
    document.getElementById('chatMsgBody').appendChild(loader);
    scrollToBottom();

    try {
        const response = await fetch('index.php?page=api_ai_chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: query })
        });
        const result = await response.json();
        
        // Remove loader
        loader.remove();

        if (result.success && result.answer) {
            appendMessage('ai', result.answer);
        } else if (result.success && result.data) {
            // Render structured output
            let formatted = "Here is what I found:\n";
            if (Array.isArray(result.data)) {
                result.data.forEach((row, i) => {
                    formatted += `\n[${i+1}] ` + Object.entries(row).map(([k, v]) => `${k}: ${v}`).join(', ');
                });
            } else {
                formatted += JSON.stringify(result.data, null, 2);
            }
            appendMessage('ai', formatted);
        } else {
            appendMessage('ai', result.error || "Sorry, I couldn't find an answer to that question.");
        }
    } catch(err) {
        loader.remove();
        appendMessage('ai', "Error connecting to the AI server: " + err.message);
    }
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

