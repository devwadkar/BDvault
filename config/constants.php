<?php
// Application-wide constants
define('APP_NAME', 'BDvault – Proposal Management System');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('MAX_UPLOAD_MB', 10);

// Proposal types
define('PROPOSAL_TYPES', [
    'Society Redevelopment',
    'DM Model',
    'Outright Purchase',
    'MHADA',
    'SRA',
    'Joint Venture',
    'Own land',
    'Hospitals/Healthcare',
    'Land Parcel',
    'Service Provider Model',
    'Other',
]);

// Stage 2 category labels and colours (Bootstrap colours)
define('CATEGORY_HIGH',     'High Potential');
define('CATEGORY_MODERATE', 'Moderate');
define('CATEGORY_LOW',      'Low');

define('CATEGORY_COLOURS', [
    CATEGORY_HIGH     => 'success',
    CATEGORY_MODERATE => 'warning',
    CATEGORY_LOW      => 'danger',
]);

// Score-to-probability logistic params
// P = 100 / (1 + exp(-k * (score - midpoint)))
define('LOGISTIC_K',        0.10);   // steepness
define('LOGISTIC_MIDPOINT', 50.0);   // score at which P = 50%

// Density metric label shown in UI
define('DENSITY_LABEL', 'Proposed/Existing Unit Ratio');
