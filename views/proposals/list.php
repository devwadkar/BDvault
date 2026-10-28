<?php $pageTitle = 'Proposals'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<!-- Premium Header Styling -->
<style>
.proposals-heading {
    color: #1e293b;
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.proposals-subheading {
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
    text-decoration: none;
}
.btn-premium-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
}

/* Premium Filter Card */
.filter-card {
    background: #ffffff;
    border: 1px solid #f0f2f5;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    padding: 16px 20px;
}
.filter-input {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
    outline: none;
    width: 100%;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.filter-input:focus {
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
    width: 100%;
    transition: background 0.2s;
}
.btn-filter-apply:hover { background: #3b82f6; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="proposals-heading mb-0">Proposals</h4>
        <p class="proposals-subheading mb-0"><?= number_format($pag['total']) ?> total records</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=proposal_add" class="btn-premium-action">
            <i class="bi bi-plus-lg"></i> Add
        </a>
    </div>
</div>

<!-- Filter bar -->
<form class="filter-card mb-4" method="GET" action="index.php">
    <input type="hidden" name="page" value="proposals">
    <div class="row g-2 align-items-center">
        <div class="col-md-2">
            <input type="text" name="search" class="filter-input"
                   placeholder="Search name / region…" value="<?= e($filters['search']) ?>">
        </div>
        <div class="col-md-2">
            <select name="proposal_type" class="filter-input">
                <option value="">All Types</option>
                <?php foreach (PROPOSAL_TYPES as $t): ?>
                <option value="<?= e($t) ?>" <?= $filters['proposal_type']===$t?'selected':'' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="stage1_status" class="filter-input">
                <option value="">All Stage 1</option>
                <option value="eligible" <?= $filters['stage1_status']==='eligible'?'selected':'' ?>>Eligible</option>
                <option value="rejected" <?= $filters['stage1_status']==='rejected'?'selected':'' ?>>Rejected</option>
                <option value="pending"  <?= $filters['stage1_status']==='pending' ?'selected':'' ?>>Pending</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="filter-input">
                <option value="">All Decisions</option>
                <option value="Ready for Review" <?= $filters['status']==='Ready for Review'?'selected':'' ?>>Ready for Review</option>
                <option value="Accepted"         <?= $filters['status']==='Accepted'?'selected':''         ?>>Accepted</option>
                <option value="Rejected"         <?= $filters['status']==='Rejected'?'selected':''         ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="category" class="filter-input">
                <option value="">All Categories</option>
                <option value="High Potential" <?= $filters['category']==='High Potential'?'selected':'' ?>>High Potential</option>
                <option value="Moderate"        <?= $filters['category']==='Moderate'?'selected':''        ?>>Moderate</option>
                <option value="Low"             <?= $filters['category']==='Low'?'selected':''             ?>>Low</option>
            </select>
        </div>
        <div class="col-md-1">
            <select name="region" class="filter-input">
                <option value="">All Regions</option>
                <?php foreach ($regions as $r): ?>
                <option value="<?= e($r) ?>" <?= $filters['region']===$r?'selected':'' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="_sort_combo" id="sortCombo" class="filter-input">
                <option value="p.created_at|DESC" <?= ($filters['sort']==='p.created_at'&&$filters['dir']==='DESC')?'selected':'' ?>>Newest First</option>
                <option value="p.created_at|ASC"  <?= ($filters['sort']==='p.created_at'&&$filters['dir']==='ASC') ?'selected':'' ?>>Oldest First</option>
                <option value="p.date_month|DESC" <?= ($filters['sort']==='p.date_month'&&$filters['dir']==='DESC')?'selected':'' ?>>Submission Date ↓</option>
                <option value="p.date_month|ASC"  <?= ($filters['sort']==='p.date_month'&&$filters['dir']==='ASC') ?'selected':'' ?>>Submission Date ↑</option>
                <option value="total_score|DESC"  <?= ($filters['sort']==='total_score') ?'selected':'' ?>>Score ↓</option>
                <option value="avg_sale_price_sqft|DESC" <?= ($filters['sort']==='avg_sale_price_sqft')?'selected':'' ?>>Sale Rate ↓</option>
            </select>
            <input type="hidden" name="sort" id="sortField" value="<?= e($filters['sort']) ?>">
            <input type="hidden" name="dir"   id="sortDir"   value="<?= e($filters['dir'])  ?>">
        </div>
        <div class="col-md-1 mt-2 mt-md-0">
            <button class="btn-filter-apply">Filter</button>
        </div>
    </div>
</form>

<script>
// Split 'field|DIR' from combo and populate hidden inputs, then submit
document.getElementById('sortCombo')?.addEventListener('change', function() {
    const [field, dir] = this.value.split('|');
    document.getElementById('sortField').value = field;
    document.getElementById('sortDir').value   = dir || 'DESC';
    this.closest('form').submit();
});
</script>

<!-- Table -->
<style>
.custom-table-card {
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}
.custom-table th {
    font-size: 0.7rem;
    font-weight: 700;
    color: #64748b !important;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 10px 8px !important;
    background: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.custom-table td {
    padding: 10px 8px !important;
    font-size: 0.78rem;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}
.custom-table tbody tr:hover td {
    background-color: #fafcff !important;
}

/* Upgraded modern score bar */
.mini-score-bar-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.mini-score-track {
    width: 60px;
    height: 6px;
    background: #f1f5f9;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}
.mini-score-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
}

/* Action icon buttons */
.btn-list-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    transition: background 0.2s, border-color 0.2s;
}
.btn-list-action:hover { background: #f8fafc; color: #1e293b; }
.btn-list-action.view:hover { color: #3b82f6; border-color: #3b82f6; }
.btn-list-action.edit:hover { color: #059669; border-color: #059669; }
.btn-list-action.delete:hover { color: #dc2626; border-color: #fca5a5; background: #fef2f2; }
</style>

<div class="card border-0 custom-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table custom-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4" style="min-width:180px"><?= sortLink('proposal_name','Proposal',$filters) ?></th>
                    <th>Type</th>
                    <th>Region</th>
                    <th><?= sortLink('avg_sale_price_sqft','Sale Rate',$filters) ?></th>
                    <th><?= sortLink('p.date_month','Date',$filters) ?></th>
                    <th>Stage 1</th>
                    <th>Decision</th>
                    <th><?= sortLink('total_score','Score',$filters) ?></th>
                    <th><?= sortLink('probability_pct','Probability',$filters) ?></th>
                    <th>Category</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-5">
                        No proposals found. <a href="index.php?page=upload">Import Excel</a> or
                        <a href="index.php?page=proposal_add">Add a proposal</a>.
                    </td>
                </tr>
                <?php else: foreach ($rows as $row): ?>
                <?php
                    $s1    = $row['is_eligible'];
                    $cat   = $row['category'] ?? '';
                    $colours = CATEGORY_COLOURS;
                    $score = (float)$row['total_score'];
                    $prob = (float)$row['probability_pct'];
                ?>
                <tr>
                    <!-- Proposal Column -->
                    <td class="ps-4">
                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <a href="index.php?page=proposal_detail&id=<?= $row['id'] ?>"
                               class="fw-bold text-decoration-none text-dark hover-primary" style="font-size: 0.85rem;" title="<?= e($row['proposal_name']) ?>">
                                <?= e($row['proposal_name']) ?>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-1 mt-1">
                            <?php if ($score >= 70): ?>
                                <span class="badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-1" style="font-size:0.58rem; padding: 2px 4px;"><i class="bi bi-stars"></i> AI Pick</span>
                            <?php endif; ?>
                            <?php if ($row['is_sra']):  ?><span class="badge bg-info-subtle text-info" style="font-size: 0.58rem; padding: 2px 4px;">SRA</span><?php endif; ?>
                            <?php if ($row['is_mhada']): ?><span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.58rem; padding: 2px 4px;">MHADA</span><?php endif; ?>
                        </div>
                    </td>
                    
                    <!-- Type Column -->
                    <td class="text-muted" style="font-size: 0.8rem; font-weight: 500;"><?= e($row['proposal_type'] ?? '—') ?></td>
                    
                    <!-- Region Column -->
                    <td>
                        <span class="text-muted smaller"><i class="bi bi-geo-alt-fill me-1"></i><?= e($row['region'] ?? '—') ?></span>
                    </td>
                    
                    <!-- Sale Rate Column -->
                    <td>
                        <?php if ($row['avg_sale_price_sqft']): ?>
                        <span class="<?= $row['avg_sale_price_sqft'] >= 60000 ? 'text-success' : 'text-danger' ?> fw-bold">
                            <?= formatINR((float)$row['avg_sale_price_sqft']) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    
                    <!-- Date Column (Formatted day/month/year) -->
                    <td>
                        <span class="text-muted smaller">
                            <?= $row['date_month'] ? date('d/m/Y', strtotime($row['date_month'])) : '—' ?>
                        </span>
                    </td>
                    
                    <!-- Stage 1 Column -->
                    <td>
                        <?php if ($s1 === null): ?>
                            <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-clock me-1"></i>Pending</span>
                        <?php elseif ($s1): ?>
                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-check-lg me-1"></i>Eligible</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-x-lg me-1"></i>Rejected</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Decision Column -->
                    <td>
                        <?php
                        $dec = $row['status'] ?? 'Ready for Review';
                        if (empty($dec) || $dec === '0') {
                            $dec = 'Ready for Review';
                        }
                        if ($dec === 'Accepted'): ?>
                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-check-circle me-1"></i>Accepted</span>
                        <?php elseif ($dec === 'Rejected'): ?>
                            <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning px-2.5 py-1.5" style="border-radius: 6px; font-size: 0.72rem; font-weight: 600;"><i class="bi bi-clock me-1"></i>Pending</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Score Column (Circular Ring Gauges Only) -->
                    <td>
                        <?php if ($row['total_score'] !== null): ?>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            // Choose colors based on score
                            if ($score >= 70) {
                                $ringBg = '#dbeafe'; $ringFill = '#2563eb'; $scoreLabel = 'Good'; $labelTextClass = 'text-primary';
                            } elseif ($score >= 50) {
                                $ringBg = '#f3e8ff'; $ringFill = '#a855f7'; $scoreLabel = 'Average'; $labelTextClass = 'text-purple';
                            } else {
                                $ringBg = '#ffedd5'; $ringFill = '#ea580c'; $scoreLabel = 'Low'; $labelTextClass = 'text-warning';
                            }
                            ?>
                            <div style="position:relative; width:34px; height:34px;">
                                <svg viewBox="0 0 36 36" width="34" height="34">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="<?= $ringBg ?>" stroke-width="3"/>
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="<?= $ringFill ?>" stroke-width="3"
                                            stroke-dasharray="<?= $score ?> 100" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                                </svg>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:0.8rem; line-height:1;"><?= number_format($score, 1) ?></div>
                                <span class="fw-bold <?= $labelTextClass ?>" style="font-size:0.62rem;"><?= $scoreLabel ?></span>
                            </div>
                        </div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    
                    <!-- Probability Column (Circular Ring Gauges Only) -->
                    <td>
                        <?php if ($row['probability_pct'] !== null): ?>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            // Choose colors based on probability
                            if ($prob >= 80) {
                                $probBg = '#d1fae5'; $probFill = '#10b981'; $probLabel = 'Very High'; $probTextClass = 'text-success';
                            } elseif ($prob >= 50) {
                                $probBg = '#ccfbf1'; $probFill = '#0d9488'; $probLabel = 'High'; $probTextClass = 'text-teal';
                            } else {
                                $probBg = '#fee2e2'; $probFill = '#ef4444'; $probLabel = 'Low'; $probTextClass = 'text-danger';
                            }
                            ?>
                            <div style="position:relative; width:34px; height:34px;">
                                <svg viewBox="0 0 36 36" width="34" height="34">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="<?= $probBg ?>" stroke-width="3"/>
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="<?= $probFill ?>" stroke-width="3"
                                            stroke-dasharray="<?= $prob ?> 100" stroke-linecap="round" transform="rotate(-90 18 18)"/>
                                </svg>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size:0.8rem; line-height:1;"><?= number_format($prob, 1) ?>%</div>
                                <span class="fw-bold <?= $probTextClass ?>" style="font-size:0.62rem;"><?= $probLabel ?></span>
                            </div>
                        </div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    
                    <!-- Category Column -->
                    <td>
                        <?php if ($cat): ?>
                        <span class="badge bg-<?= $colours[$cat] ?? 'secondary' ?>-subtle text-<?= $colours[$cat] ?? 'secondary' ?> px-2.5 py-1.5" style="border-radius:20px; font-size: 0.7rem; font-weight:700;">
                            <?= e($cat) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    
                    <!-- Actions Column -->
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-1.5">
                            <a href="index.php?page=proposal_detail&id=<?= $row['id'] ?>"
                               class="btn-list-action view" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (can('edit_proposals')): ?>
                            <a href="index.php?page=proposal_edit&id=<?= $row['id'] ?>"
                               class="btn-list-action edit" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (can('delete_proposals')): ?>
                            <a href="index.php?page=proposal_delete&id=<?= $row['id'] ?>"
                               class="btn-list-action delete" title="Delete"
                               onclick="return confirm('Are you sure you want to delete this proposal?')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer bg-transparent d-flex align-items-center justify-content-between">
        <span class="text-muted small">
            Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['perPage'],$pag['total']) ?>
            of <?= number_format($pag['total']) ?>
        </span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qBase = http_build_query(array_merge($filters, ['page' => 'proposals']));
                for ($i = max(1,$pag['page']-2); $i <= min($pag['pages'],$pag['page']+2); $i++):
                ?>
                <li class="page-item <?= $i===$pag['page']?'active':'' ?>">
                    <a class="page-link" href="index.php?<?= $qBase ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
function sortLink(string $col, string $label, array $filters): string {
    $cur = $filters['sort'] ?? '';
    $dir = $filters['dir'] ?? 'DESC';
    $newDir = ($cur === $col && $dir === 'DESC') ? 'ASC' : 'DESC';
    $icon = ($cur === $col) ? ($dir === 'DESC' ? '↓' : '↑') : '';
    $q = http_build_query(array_merge($filters, ['page' => 'proposals', 'sort' => $col, 'dir' => $newDir]));
    return "<a href=\"index.php?{$q}\" class=\"text-decoration-none text-dark\">{$label} {$icon}</a>";
}
?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
