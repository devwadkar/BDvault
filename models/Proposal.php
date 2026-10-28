<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class Proposal {

    // ─── Fetch helpers ───────────────────────────────────────────────────────

    public static function getById(int $id): ?array {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public static function getMarketAnalysis(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM market_analysis WHERE proposal_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * List proposals with optional filters and pagination.
     * Joins Stage 1 & Stage 2 results for the list view.
     */
    public static function getList(array $filters = [], int $page = 1, int $perPage = 20): array {
        $db     = getDB();
        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if (!empty($filters['search'])) {
            $where[]  = '(p.proposal_name LIKE ? OR p.region LIKE ? OR p.city LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$s, $s, $s]);
            $types   .= 'sss';
        }
        if (!empty($filters['proposal_type'])) {
            $where[]  = 'p.proposal_type = ?';
            $params[] = $filters['proposal_type'];
            $types   .= 's';
        }
        if (!empty($filters['stage1_status'])) {
            if ($filters['stage1_status'] === 'eligible') {
                $where[] = 's1.is_eligible = 1';
            } elseif ($filters['stage1_status'] === 'rejected') {
                $where[] = 's1.is_eligible = 0';
            } elseif ($filters['stage1_status'] === 'pending') {
                $where[] = 's1.id IS NULL';
            }
        }
        if (!empty($filters['category'])) {
            $where[]  = 's2.category = ?';
            $params[] = $filters['category'];
            $types   .= 's';
        }
        if (!empty($filters['region'])) {
            $where[]  = 'p.region = ?';
            $params[] = $filters['region'];
            $types   .= 's';
        }
        if (!empty($filters['status'])) {
            $where[]  = 'p.status = ?';
            $params[] = $filters['status'];
            $types   .= 's';
        }

        $whereSQL = implode(' AND ', $where);

        // Count total
        $countSQL = "
            SELECT COUNT(*) as cnt
            FROM proposals p
            LEFT JOIN stage1_results s1 ON s1.proposal_id = p.id
            LEFT JOIN stage2_scores  s2 ON s2.proposal_id = p.id
            WHERE $whereSQL
        ";
        $stmt = $db->prepare($countSQL);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)$stmt->get_result()->fetch_assoc()['cnt'];

        $pag    = paginate($total, $perPage, $page);
        $limit  = $pag['perPage'];
        $offset = $pag['offset'];

        $sort   = in_array($filters['sort'] ?? '', [
                    'total_score', 'probability_pct', 'proposal_name',
                    'avg_sale_price_sqft', 'p.created_at', 'p.date_month'
                  ], true)
                    ? $filters['sort'] : 'p.created_at';
        $dir    = ($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $dataSQL = "
            SELECT
                p.*,
                s1.is_eligible, s1.rejection_reasons, s1.processed_at AS s1_processed_at,
                s2.total_score, s2.probability_pct, s2.category,
                s2.score_profit_margin, s2.score_location, s2.score_risk,
                s2.score_cost_efficiency, s2.score_sale_velocity,
                s2.processed_at AS s2_processed_at
            FROM proposals p
            LEFT JOIN stage1_results s1 ON s1.proposal_id = p.id
            LEFT JOIN stage2_scores  s2 ON s2.proposal_id = p.id
            WHERE $whereSQL
            ORDER BY $sort $dir
            LIMIT ? OFFSET ?
        ";
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes  = $types . 'ii';
        $stmt = $db->prepare($dataSQL);
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['rows' => $rows, 'pagination' => $pag];
    }

    /** Full detail view — joins Stage 1, Stage 2 */
    public static function getDetail(int $id): ?array {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT
                p.*,
                s1.is_eligible, s1.filter_sale_rate, s1.filter_fsi,
                s1.filter_free_sale_area, s1.filter_density,
                s1.filter_timeline, s1.filter_cost_feasibility,
                s1.eval_sale_rate, s1.eval_fsi_used,
                s1.eval_free_sale_area_pct, s1.eval_density_ratio,
                s1.eval_timeline_yrs, s1.eval_profit_margin_pct,
                s1.rejection_reasons, s1.config_snapshot,
                s1.processed_at AS s1_processed_at,
                s2.total_score, s2.probability_pct, s2.category,
                s2.score_profit_margin, s2.score_location,
                s2.score_risk, s2.score_cost_efficiency, s2.score_sale_velocity,
                s2.contrib_profit_margin, s2.contrib_location, s2.contrib_risk,
                s2.contrib_cost_efficiency, s2.contrib_sale_velocity,
                s2.score_breakdown, s2.weights_used,
                s2.processed_at AS s2_processed_at
            FROM proposals p
            LEFT JOIN stage1_results s1 ON s1.proposal_id = p.id
            LEFT JOIN stage2_scores  s2 ON s2.proposal_id = p.id
            WHERE p.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Summary stats for dashboard ─────────────────────────────────────────

    public static function getSummaryStats(array $filters = []): array {
        $db = getDB();
        $where = self::buildFilterSql($filters);
        
        $sql = "
            SELECT
                COUNT(DISTINCT p.id)                                         AS total,
                SUM(s1.is_eligible = 1)                                      AS eligible,
                SUM(s1.is_eligible = 0)                                      AS rejected,
                SUM(s1.id IS NULL)                                           AS pending_s1,
                SUM(p.status = 'Ready for Review' OR p.status = '' OR p.status IS NULL OR p.status = '0') AS dec_pending,
                SUM(p.status = 'Accepted')                                   AS dec_accepted,
                SUM(p.status = 'Rejected')                                   AS dec_rejected,
                SUM(s2.category = 'High Potential')                          AS high,
                SUM(s2.category = 'Moderate')                                AS moderate,
                SUM(s2.category = 'Low')                                     AS low_cat,
                ROUND(AVG(s2.total_score), 1)                                AS avg_score,
                ROUND(AVG(s2.probability_pct), 1)                            AS avg_prob,
                ROUND(AVG(p.avg_sale_price_sqft), 0)                         AS avg_sale_rate,
                -- Financial Projection (Estimated Potential Value in Cr)
                SUM(CASE WHEN s2.category = 'High Potential' THEN p.plot_area_sqm * 10.764 * p.avg_sale_price_sqft ELSE 0 END) / 10000000 AS potential_value_cr
            FROM proposals p
            LEFT JOIN stage1_results s1 ON s1.proposal_id = p.id
            LEFT JOIN stage2_scores  s2 ON s2.proposal_id = p.id
            $where
        ";
        return $db->query($sql)->fetch_assoc() ?: [];
    }

    private static function buildFilterSql(array $filters): string {
        $whereArr = [];
        if (!empty($filters['from_date'])) {
            $from = getDB()->real_escape_string($filters['from_date']);
            $whereArr[] = "p.date_month >= '$from'";
        }
        if (!empty($filters['to_date'])) {
            $to = getDB()->real_escape_string($filters['to_date']);
            $whereArr[] = "p.date_month <= '$to'";
        }
        return $whereArr ? ' WHERE ' . implode(' AND ', $whereArr) : '';
    }

    /** Top proposals by score for dashboard widget */
    public static function getTopScored(int $limit = 5): array {
        return getDB()->query("
            SELECT p.*,
                   s2.total_score, s2.probability_pct, s2.category,
                   s2.score_profit_margin, s2.score_location, s2.score_risk,
                   s2.score_cost_efficiency, s2.score_sale_velocity,
                   s1.is_eligible, s1.eval_fsi_used, s1.eval_profit_margin_pct
            FROM proposals p
            INNER JOIN stage2_scores s2 ON s2.proposal_id = p.id
            LEFT JOIN stage1_results s1 ON s1.proposal_id = p.id
            ORDER BY s2.total_score DESC
            LIMIT $limit
        ")->fetch_all(MYSQLI_ASSOC);
    }

    /** Score distribution for histogram chart */
    public static function getScoreDistribution(array $filters = []): array {
        $where = self::buildFilterSql($filters);
        return getDB()->query("
            SELECT FLOOR(total_score / 10) * 10 AS score_bucket, COUNT(*) AS cnt
            FROM stage2_scores s2
            JOIN proposals p ON p.id = s2.proposal_id
            $where
            GROUP BY score_bucket
            ORDER BY score_bucket ASC
        ")->fetch_all(MYSQLI_ASSOC);
    }

    /** Distinct regions */
    public static function getRegions(): array {
        $rows = getDB()->query("SELECT DISTINCT region FROM proposals WHERE region != '' ORDER BY region")
                       ->fetch_all(MYSQLI_ASSOC);
        return array_column($rows, 'region');
    }

    /** Region distribution for bar chart */
    public static function getRegionDistribution(int $limit = 10, array $filters = []): array {
        $where = self::buildFilterSql($filters);
        return getDB()->query("
            SELECT region, COUNT(*) AS cnt
            FROM proposals p
            $where
            GROUP BY region
            ORDER BY cnt DESC
            LIMIT $limit
        ")->fetch_all(MYSQLI_ASSOC);
    }

    /** City (Zone) distribution for bar chart */
    public static function getCityDistribution(int $limit = 10, array $filters = []): array {
        $where = self::buildFilterSql($filters);
        return getDB()->query("
            SELECT city, COUNT(*) AS cnt
            FROM proposals p
            $where
            GROUP BY city
            ORDER BY cnt DESC
            LIMIT $limit
        ")->fetch_all(MYSQLI_ASSOC);
    }

    /** Proposal Type distribution for pie chart */
    public static function getTypeDistribution(int $limit = 8, array $filters = []): array {
        $where = self::buildFilterSql($filters);
        return getDB()->query("
            SELECT proposal_type, COUNT(*) AS cnt
            FROM proposals p
            $where
            GROUP BY proposal_type
            ORDER BY cnt DESC
            LIMIT $limit
        ")->fetch_all(MYSQLI_ASSOC);
    }

    // ─── CRUD ────────────────────────────────────────────────────────────────

    /**
     * Insert used during Excel import — skips duplicates (same name+type+date).
     * Returns the new id on insert, 0 if skipped as a duplicate.
     */
    public static function importInsert(array $d): int {
        $db = getDB();

        // Duplicate check: same proposal_name + proposal_type + date_month
        $stmt = $db->prepare("
            SELECT id FROM proposals
            WHERE proposal_name = ?
              AND COALESCE(proposal_type,'') = COALESCE(?,'')  
              AND COALESCE(date_month,'0000-00-00') = COALESCE(?,'0000-00-00')
            LIMIT 1
        ");
        $name  = $d['proposal_name'];
        $type  = $d['proposal_type'] ?? '';
        $date  = $d['date_month'] ?? '';
        $stmt->bind_param('sss', $name, $type, $date);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            return 0; // duplicate — skip
        }

        return self::insert($d);
    }

    public static function insert(array $d): int {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO proposals
                (proposal_name, proposal_type, region, city, site_address, pincode,
                 reference_owner, consultant_name_number, lat_lng, date_month, data_source,
                 plot_area_sqm, avg_sale_price_sqft, entry_cost_per_sqft,
                 existing_fsi_used, free_sale_area_pct, society_allocation_pct,
                 existing_units, proposed_units, completion_timeline_yrs,
                 location_rating, approval_complexity, market_demand,
                 is_sra, is_outright, is_mhada, is_stressed,
                 status, remarks, cluster)
            VALUES (?,?,?,?,?,?, ?,?,?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?,?,?, ?,?,?)
        ");
        // Types: 10×s, 6×d (plot_area..society_alloc), 2×i (existing/proposed units),
        //        1×d (timeline), 3×i (ratings), 4×i (flags), 3×s (status/remarks/cluster) = 29
        $stmt->bind_param(
            'sssssssssssddddddiidiiiiiiisss',
            $d['proposal_name'], $d['proposal_type'], $d['region'], $d['city'],
            $d['site_address'], $d['pincode'],
            $d['reference_owner'], $d['consultant_name_number'], $d['lat_lng'], $d['date_month'], $d['data_source'],
            $d['plot_area_sqm'], $d['avg_sale_price_sqft'], $d['entry_cost_per_sqft'],
            $d['existing_fsi_used'], $d['free_sale_area_pct'], $d['society_allocation_pct'],
            $d['existing_units'], $d['proposed_units'], $d['completion_timeline_yrs'],
            $d['location_rating'], $d['approval_complexity'], $d['market_demand'],
            $d['is_sra'], $d['is_outright'], $d['is_mhada'], $d['is_stressed'],
            $d['status'], $d['remarks'], $d['cluster']
        );
        $stmt->execute();
        return $db->insert_id;
    }

    public static function update(int $id, array $d): bool {
        $db   = getDB();
        $stmt = $db->prepare("
            UPDATE proposals SET
                proposal_name = ?, proposal_type = ?, region = ?, city = ?,
                site_address = ?, pincode = ?, reference_owner = ?, consultant_name_number = ?, lat_lng = ?,
                date_month = ?, plot_area_sqm = ?, avg_sale_price_sqft = ?,
                entry_cost_per_sqft = ?, existing_fsi_used = ?,
                free_sale_area_pct = ?, society_allocation_pct = ?,
                existing_units = ?, proposed_units = ?, completion_timeline_yrs = ?,
                location_rating = ?, approval_complexity = ?, market_demand = ?,
                is_sra = ?, is_outright = ?, is_mhada = ?, is_stressed = ?,
                status = ?, remarks = ?, cluster = ?
            WHERE id = ?
        ");
        // 9s(name..date) + 6d(areas/costs) + 2i(units) + 1d(timeline)
        // + 7i(3 ratings + 4 flags) + 3s(status/remarks/cluster) + 1i(id) = 29
        $stmt->bind_param(
            'ssssssssssddddddiiidiiiiiisssi',
            $d['proposal_name'], $d['proposal_type'], $d['region'], $d['city'],
            $d['site_address'], $d['pincode'], $d['reference_owner'], $d['consultant_name_number'], $d['lat_lng'],
            $d['date_month'], $d['plot_area_sqm'], $d['avg_sale_price_sqft'],
            $d['entry_cost_per_sqft'], $d['existing_fsi_used'],
            $d['free_sale_area_pct'], $d['society_allocation_pct'],
            $d['existing_units'], $d['proposed_units'], $d['completion_timeline_yrs'],
            $d['location_rating'], $d['approval_complexity'], $d['market_demand'],
            $d['is_sra'], $d['is_outright'], $d['is_mhada'], $d['is_stressed'],
            $d['status'], $d['remarks'], $d['cluster'],
            $id
        );
        return $stmt->execute();
    }

    public static function delete(int $id): bool {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Excel import ─────────────────────────────────────────────────────────

    /**
     * Maps a raw Excel row (headers as keys) to the proposals table columns.
     * Handles column naming differences between the two Excel files.
     */
    public static function mapExcelRow(array $row, string $source): array {
        $g = fn(array $keys) => self::firstVal($row, $keys);

        return [
            'proposal_name'          => $g(['Proposal name', 'proposal name']),
            'proposal_type'          => $g(['Proposal type']),
            'region'                 => $g(['Region']),
            'city'                   => $g(['City']),
            'site_address'           => $g(['Site Address']),
            'pincode'                => $g(['Pincode']),
            'reference_owner'        => $g(['Reference/Owner']),
            'consultant_name_number' => $g(['Consultant name & Number', 'Consultant name', 'Consultant number']),
            'lat_lng'                => $g(['Latitude/Longitutde', 'Latitude/Longitude']),
            'date_month'             => safeDate($g(['Date/Month'])),
            'data_source'            => $source,
            'plot_area_sqm'          => safeFloat($g(['Plot Area (sq.m.)'])),
            'avg_sale_price_sqft'    => safeFloat($g(['Avg Sale Price(per sq.ft)', 'Avg Sale Price (per sq.ft)'])),
            'entry_cost_per_sqft'    => safeFloat($g(['Entry Cost (per sq.ft)', 'Entry Cost'])),
            'existing_fsi_used'      => safeFloat($g(['Existing FSI Used', 'FSI Used'])),
            'free_sale_area_pct'     => safeFloat($g(['Free Sale Area %', 'Free Sale Area'])),
            'society_allocation_pct' => safeFloat($g(['Society Allocation %', 'Society Allocation'])),
            'existing_units'         => safeInt($g(['Existing Units', 'Existing Flats'])),
            'proposed_units'         => safeInt($g(['Proposed Units', 'Proposed Flats'])),
            'completion_timeline_yrs'=> self::clampTimeline(safeFloat($g(['Timeline', 'Completion Timeline (yrs)']))),
            'location_rating'        => safeInt($g(['Location Rating'])),
            'approval_complexity'    => safeInt($g(['Approval Complexity'])),
            'market_demand'          => safeInt($g(['Market Demand'])),
            'is_sra'                 => self::boolVal($g(['Sra', 'SRA'])),
            'is_outright'            => self::boolVal($g(['Outright'])),
            'is_mhada'               => self::boolVal($g(['MHADA', 'Mhada'])),
            'is_stressed'            => self::boolVal($g(['Stressed'])),
            'status'                 => $g(['Status']) ?: 'Ready for Review',
            'remarks'                => $g(['Remarks']),
            'cluster'                => $g(['Cluster']),
        ];
    }

    private static function clampTimeline(?float $val): ?float {
        if ($val === null) return null;
        // DECIMAL(4,1) = max 999.9 on live DB (column widening migration may not have run yet).
        // Clamping to 999.9 prevents "Out of range value" DB errors on import.
        return max(0.0, min(999.9, $val));
    }

    private static function firstVal(array $row, array $keys): string {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') return (string)$row[$k];
        }
        return '';
    }

    private static function boolVal(string $val): int {
        $v = strtolower(trim($val));
        return in_array($v, ['1', 'yes', 'true', 'y', 'x', '✓'], true) ? 1 : 0;
    }
}
