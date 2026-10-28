<?php
$cur = $_GET['page'] ?? 'dashboard';
$u = currentUser();
?>
<style>
/* ── Premium Sidebar Base ── */
.sidebar-container {
    width: 250px;
    background: #f8fafc;
    border-right: 1.5px solid #e2e8f0;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    position: relative;
    flex-shrink: 0;
    z-index: 1020;
}
.sidebar-container.collapsed {
    width: 76px;
}

/* Header Section */
.sidebar-header {
    height: 72px;
    padding: 0 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px dashed #e2e8f0;
}
.sidebar-logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
    white-space: nowrap;
    transition: opacity 0.25s;
}
.sidebar-logo {
    height: 38px;
    width: auto;
}
.sidebar-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.2;
}
.sidebar-subtitle {
    font-size: 0.65rem;
    font-weight: 600;
    color: #64748b;
}
.sidebar-toggle-btn {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.95rem;
    padding: 6px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, color 0.2s;
}
.sidebar-toggle-btn:hover {
    background: #f1f5f9;
    color: #475569;
}

/* Nav Menu Items */
.sidebar-menu {
    list-style: none;
    padding: 14px 10px;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}
.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Section Header Labels */
.menu-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 10px 12px 4px;
    white-space: nowrap;
    transition: opacity 0.2s;
}
.sidebar-container.collapsed .menu-section-label {
    opacity: 0;
    height: 0;
    padding: 0;
    overflow: hidden;
}

/* Nav Link Styling */
.menu-item {
    position: relative;
}
.menu-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #475569;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}
.menu-link i {
    font-size: 1.15rem;
    color: #64748b;
    transition: color 0.2s;
}

/* Hover & Active states */
.menu-link:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.menu-link:hover i {
    color: #334155;
}

/* Glowing Blue Active State (Mockup style) */
.menu-link.active {
    background: linear-gradient(135deg, #3b82f6, #4f46e5);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
}
.menu-link.active i {
    color: #ffffff !important;
}

/* Dot indicator on active */
.active-dot {
    width: 6px;
    height: 6px;
    background-color: #22c55e;
    border-radius: 50%;
    margin-left: auto;
}
.menu-link.active .active-dot {
    background-color: #ffffff;
}

/* Collapsed Sidebar overrides */
.sidebar-container.collapsed .menu-link {
    justify-content: center;
    padding: 10px 0;
    width: 56px;
    margin: 0 auto;
}
.sidebar-container.collapsed .menu-link span,
.sidebar-container.collapsed .active-dot {
    display: none;
}

/* Floating Tooltips for Collapsed View */
.menu-item[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 85px;
    top: 50%;
    transform: translateY(-50%) scale(0.95);
    background: #1e293b;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s ease, transform 0.15s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1090;
}
.sidebar-container.collapsed .menu-item[data-tooltip]:hover::after {
    opacity: 1;
    transform: translateY(-50%) scale(1);
}

/* ── Interactive Flyout Dropdown (AI Insights) ── */
.flyout-menu {
    position: absolute;
    left: 242px;
    top: 0;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px;
    width: 200px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    display: none;
    flex-direction: column;
    gap: 4px;
    z-index: 1080;
    animation: flyoutFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes flyoutFadeIn {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}
.sidebar-container.collapsed .flyout-menu {
    left: 68px;
}
.flyout-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.2s, color 0.2s;
}
.flyout-link i {
    font-size: 1rem;
    color: #64748b;
}
.flyout-link:hover {
    background: #f0f4ff;
    color: #4f8ef7;
}
.flyout-link:hover i {
    color: #4f8ef7;
}

/* User Card Footer */
.sidebar-footer {
    padding: 14px;
    border-top: 1px dashed #e2e8f0;
}
.user-profile-card {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: background 0.2s;
    position: relative;
}
.user-profile-card:hover {
    background: #e2e8f0;
}
.sidebar-container.collapsed .user-profile-card {
    justify-content: center;
    padding: 10px 0;
}
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #3b82f6;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.user-details {
    flex: 1;
    min-width: 0;
    transition: opacity 0.2s;
}
.sidebar-container.collapsed .user-details {
    display: none;
}
.user-name {
    font-size: 0.78rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-truncate: ellipsis;
}
.user-role {
    font-size: 0.65rem;
    font-weight: 600;
    color: #64748b;
}
.profile-dropdown-arrow {
    font-size: 0.8rem;
    color: #94a3b8;
    transition: transform 0.2s;
}
.sidebar-container.collapsed .profile-dropdown-arrow {
    display: none;
}

/* User actions mini dropdown */
.user-actions-popup {
    position: absolute;
    bottom: 72px;
    left: 14px;
    right: 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.10);
    padding: 6px;
    display: none;
    flex-direction: column;
    z-index: 1090;
}
.sidebar-container.collapsed .user-actions-popup {
    left: 76px;
    width: 140px;
    bottom: 14px;
}
.user-action-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    font-size: 0.78rem;
    color: #ef4444;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background 0.2s;
}
.user-action-item:hover {
    background: #fef2f2;
}
</style>

<div class="sidebar-container" id="mainSidebar">
    
    <!-- Top Brand Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo-area" id="brandLogoArea">
            <img src="assets/img/bdvault_logo.png" alt="Logo" class="sidebar-logo">
            <div>
                <div class="sidebar-title">BDvault</div>
                <div class="sidebar-subtitle">BD Proposal Classifier</div>
            </div>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Collapse Sidebar">
            <i class="bi bi-chevron-double-left" id="toggleIcon"></i>
        </button>
    </div>

    <!-- Main Scrollable Links -->
    <ul class="sidebar-menu">
        
        <!-- Navigation Section -->
        <li class="menu-item" data-tooltip="Dashboard">
            <a href="index.php?page=dashboard" class="menu-link <?= $cur==='dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i>
                <span>Dashboard</span>
                <?php if($cur==='dashboard'): ?><span class="active-dot"></span><?php endif; ?>
            </a>
        </li>

        <li class="menu-item" data-tooltip="All Proposals">
            <a href="index.php?page=proposals" class="menu-link <?= $cur==='proposals'&&!isset($_GET['stage1_status'])&&!isset($_GET['category']) ? 'active' : '' ?>">
                <i class="bi bi-folder-fill"></i>
                <span>All Proposals</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Add Proposal">
            <a href="index.php?page=proposal_add" class="menu-link <?= $cur==='proposal_add' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Proposal</span>
            </a>
        </li>

        <?php if (can('import_proposals')): ?>
        <li class="menu-item" data-tooltip="Import Excel">
            <a href="index.php?page=upload" class="menu-link <?= $cur==='upload' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Import Excel</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ANALYSIS Categories -->
        <li class="menu-section-label">Analysis</li>

        <li class="menu-item" data-tooltip="Eligible">
            <a href="index.php?page=proposals&stage1_status=eligible&sort=total_score&dir=DESC" class="menu-link <?= ($cur==='proposals'&&($_GET['stage1_status']??'')==='eligible') ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill" style="color: #22c55e;"></i>
                <span>Eligible</span>
                <?php if($cur==='proposals'&&($_GET['stage1_status']??'')==='eligible'): ?><span class="active-dot"></span><?php endif; ?>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Rejected">
            <a href="index.php?page=proposals&stage1_status=rejected" class="menu-link <?= ($cur==='proposals'&&($_GET['stage1_status']??'')==='rejected') ? 'active' : '' ?>">
                <i class="bi bi-x-circle-fill" style="color: #ef4444;"></i>
                <span>Rejected</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="High Potential">
            <a href="index.php?page=proposals&category=High+Potential&sort=total_score&dir=DESC" class="menu-link <?= ($cur==='proposals'&&($_GET['category']??'')==='High Potential') ? 'active' : '' ?>">
                <i class="bi bi-star-fill" style="color: #f59e0b;"></i>
                <span>High Potential</span>
            </a>
        </li>

        <!-- INSIGHTS Flyout Section -->
        <li class="menu-section-label">Insights</li>

        <li class="menu-item" data-tooltip="AI Insights">
            <a href="index.php?page=dashboard#openAiChat" class="menu-link">
                <i class="bi bi-stars" style="color: #6366f1;"></i>
                <span>AI Insights</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="DCPR Rule Base">
            <a href="index.php?page=dcpr" class="menu-link <?= (in_array($cur, ['dcpr','dcpr_rule','dcpr_reload'], true)) ? 'active' : '' ?>">
                <i class="bi bi-buildings" style="color: #0ea5e9;"></i>
                <span>DCPR Rules</span>
            </a>
        </li>

        <li class="menu-section-label">Feasibility Workflow</li>

        <li class="menu-item" data-tooltip="Step 1: Area Analysis — pick proposal, run the DCPR calculator">
            <a href="index.php?page=area_analysis" class="menu-link <?= (in_array($cur, ['area_analysis','area_analysis_start','area_analysis_step','area_analysis_save_step','area_analysis_review','area_analysis_generate','area_analysis_dashboard'], true)) ? 'active' : '' ?>">
                <i class="bi bi-1-circle-fill" style="color: #4f46e5;"></i>
                <span>Area Analysis</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Area Analysis v2 (preview) — simplified 5-stage flow, regulation-specific forms">
            <a href="index.php?page=area_analysis_v2" class="menu-link <?= (in_array($cur, ['area_analysis_v2','area_analysis_v2_start','area_analysis_v2_stage1','area_analysis_v2_save_stage1','area_analysis_v2_stage2','area_analysis_v2_save_stage2','area_analysis_v2_stage3','area_analysis_v2_save_stage3','area_analysis_v2_stage4','area_analysis_v2_generate','area_analysis_v2_stage5'], true)) ? 'active' : '' ?>" style="padding-left: 32px;">
                <i class="bi bi-stars" style="color: #4f46e5;"></i>
                <span style="font-size:0.82rem;">Area Analysis v2 <span style="background:#eef2ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-size:0.6rem;font-weight:700;margin-left:4px;">PREVIEW</span></span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Step 2: Approval Costs — identify approvals, estimate costs, track status">
            <a href="index.php?page=approval_costs" class="menu-link <?= (in_array($cur, ['approval_costs','approval_costs_register','approval_costs_add','approval_costs_add_custom','approval_costs_detail','approval_costs_update','approval_costs_delete','approval_costs_master_library','approval_costs_applicability','approval_costs_apply_suggestions','approval_costs_calculate','approval_costs_set_input','approval_costs_tracker','approval_costs_update_timeline','approval_costs_add_followup'], true)) ? 'active' : '' ?>">
                <i class="bi bi-2-circle-fill" style="color: #0ea5e9;"></i>
                <span>Approval Costs</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Step 3: Financial Analysis — revenue, cost, profitability">
            <a href="index.php?page=area_analysis_financials_list" class="menu-link <?= (in_array($cur, ['area_analysis_financials_list','area_analysis_financials_view','area_analysis_financials_form','area_analysis_financials_save'], true)) ? 'active' : '' ?>">
                <i class="bi bi-3-circle-fill" style="color: #059669;"></i>
                <span>Financial Analysis</span>
            </a>
        </li>

        <li class="menu-item" data-tooltip="Step 4: Best Outcome — scenarios + sensitivity + GO/NO-GO verdict">
            <a href="index.php?page=area_analysis_best_outcome" class="menu-link <?= (in_array($cur, ['area_analysis_best_outcome','area_analysis_scenarios','area_analysis_sensitivity'], true)) ? 'active' : '' ?>">
                <i class="bi bi-4-circle-fill" style="color: #d97706;"></i>
                <span>Best Outcome</span>
            </a>
        </li>

        <!-- CONFIG Section -->
        <?php if (can('manage_weights') || can('manage_users')): ?>
        <li class="menu-section-label">Config</li>
        
        <?php if (can('manage_weights')): ?>
        <li class="menu-item" data-tooltip="Scoring Weights">
            <a href="index.php?page=weights" class="menu-link <?= $cur==='weights' ? 'active' : '' ?>">
                <i class="bi bi-sliders2-vertical"></i>
                <span>Scoring Weights</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can('manage_users')): ?>
        <li class="menu-item" data-tooltip="Settings">
            <a href="index.php?page=admin_users" class="menu-link <?= $cur==='admin_users' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

    </ul>

    <!-- Footer Profile card -->
    <?php if ($u): ?>
    <div class="sidebar-footer">
        <div class="user-profile-card" id="userProfileCard">
            <div class="user-avatar">
                <?= strtoupper(substr($u['username'], 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= e($u['full_name'] ?: $u['username']) ?></div>
                <div class="user-role"><?= ucfirst(e($u['role'])) ?></div>
            </div>
            <i class="bi bi-chevron-up profile-dropdown-arrow" id="dropdownArrow"></i>
            
            <!-- Hidden drop-up panel -->
            <div class="user-actions-popup" id="userActionsPopup">
                <a href="index.php?page=logout" class="user-action-item">
                    <i class="bi bi-box-arrow-right"></i> Log Out
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Sidebar Interaction JS Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    const brandLogoArea = document.getElementById('brandLogoArea');
    const userProfileCard = document.getElementById('userProfileCard');
    const userActionsPopup = document.getElementById('userActionsPopup');
    const dropdownArrow = document.getElementById('dropdownArrow');
    const aiInsightsItem = document.getElementById('aiInsightsItem');
    const aiInsightsFlyout = document.getElementById('aiInsightsFlyout');

    // Retrieve sidebar collapse preference
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        toggleIcon.className = 'bi bi-chevron-double-right';
        brandLogoArea.style.opacity = '0';
    }

    // Toggle Collapse
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const collapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', collapsed);

            if (collapsed) {
                toggleIcon.className = 'bi bi-chevron-double-right';
                brandLogoArea.style.opacity = '0';
            } else {
                toggleIcon.className = 'bi bi-chevron-double-left';
                brandLogoArea.style.opacity = '1';
            }
        });
    }

    // User Profile Dropdown Toggle
    if (userProfileCard) {
        userProfileCard.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = userActionsPopup.style.display === 'flex';
            if (isOpen) {
                userActionsPopup.style.display = 'none';
                if (dropdownArrow) dropdownArrow.style.transform = 'rotate(0deg)';
            } else {
                userActionsPopup.style.display = 'flex';
                if (dropdownArrow) dropdownArrow.style.transform = 'rotate(180deg)';
            }
        });
    }

    // AI Insights Hover/Click Flyout
    if (aiInsightsItem) {
        // Desktop Hover
        aiInsightsItem.addEventListener('mouseenter', function() {
            aiInsightsFlyout.style.display = 'flex';
        });
        aiInsightsItem.addEventListener('mouseleave', function() {
            aiInsightsFlyout.style.display = 'none';
        });

        // Toggle on click for tablet/mobile
        aiInsightsItem.addEventListener('click', function(e) {
            e.stopPropagation();
            const flyoutOpen = aiInsightsFlyout.style.display === 'flex';
            aiInsightsFlyout.style.display = flyoutOpen ? 'none' : 'flex';
        });
    }

    // Global close listener
    document.addEventListener('click', function() {
        if (userActionsPopup) userActionsPopup.style.display = 'none';
        if (dropdownArrow) dropdownArrow.style.transform = 'rotate(0deg)';
        if (aiInsightsFlyout) aiInsightsFlyout.style.display = 'none';
    });

    // Smooth scroll navigation target within dashboard
    const scrollLinks = document.querySelectorAll('.scroll-link');
    scrollLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId) || document.querySelector('.' + targetId);
            
            if (targetEl) {
                // If on dashboard, prevent default and smooth scroll
                if (window.location.search.includes('page=dashboard') || window.location.search === '') {
                    e.preventDefault();
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });
});
</script>
