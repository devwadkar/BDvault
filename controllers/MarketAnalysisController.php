<?php
require_once __DIR__ . '/../models/Proposal.php';
require_once __DIR__ . '/../lib/AIClient.php';

class MarketAnalysisController {

    public static function generateAnalysis(): void {
        // Temporary debugging to catch live server 500 errors
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $proposalId = (int)($input['proposal_id'] ?? 0);

        if (!$proposalId) {
            echo json_encode(['success' => false, 'error' => 'Proposal ID is required.']);
            return;
        }

        // Fetch proposal details
        $proposal = Proposal::getById($proposalId);
        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Proposal not found.']);
            return;
        }

        // Check if analysis already exists
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM market_analysis WHERE proposal_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database Error: market_analysis table not found. Please run the database migration.']);
            return;
        }
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            echo json_encode(['success' => true, 'data' => $existing, 'message' => 'Loaded from cache.']);
            return;
        }

        // Construct the prompt
        $locationDetails = "Region: " . ($proposal['region'] ?? 'Unknown') . "\n";
        $locationDetails .= "City: " . ($proposal['city'] ?? 'Unknown') . "\n";
        $locationDetails .= "Address: " . ($proposal['site_address'] ?? 'Unknown') . "\n";
        
        if (!empty($proposal['plot_area_sqm'])) {
            $locationDetails .= "Plot Area: " . $proposal['plot_area_sqm'] . " sqm\n";
        }
        if (!empty($proposal['avg_sale_price_sqft'])) {
            $locationDetails .= "Expected Sale Rate: ₹" . $proposal['avg_sale_price_sqft'] . " / sqft\n";
        }

        $prompt = "You are an expert Real Estate Market Analyst. Please analyze the following property location and provide a market desirability score out of 100 based on specific parameters.

Property Details:
{$locationDetails}

Parameters and Weights:
1. Connectivity (20%) - Proximity to highways, transit, airports.
2. Infrastructure (15%) - Schools, hospitals, utilities.
3. Demand (20%) - Will people buy/rent here? 
4. Income Levels (10%) - Purchasing power in the area.
5. Future Developments (15%) - Upcoming mega-projects or infrastructure.
6. Competition (10%) - Existing supply vs demand.
7. Regulatory Ease (10%) - Ease of approvals and construction feasibility.

Return a STRICT JSON object with the following structure exactly (all scores out of their respective weights, and a total out of 100):
{
    \"connectivity_score\": 15.5,
    \"infrastructure_score\": 12.0,
    \"demand_score\": 18.0,
    \"income_score\": 8.0,
    \"future_dev_score\": 10.0,
    \"competition_score\": 7.0,
    \"regulatory_score\": 7.0,
    \"total_score\": 77.5,
    \"analysis_details\": \"A brief summary explaining the rationale for the scores based on the location...\",
    \"reasons\": {
        \"connectivity\": \"2-3 lines explaining connectivity score...\",
        \"infrastructure\": \"2-3 lines explaining infrastructure score...\",
        \"demand\": \"2-3 lines explaining demand score...\",
        \"income\": \"2-3 lines explaining income score...\",
        \"future_dev\": \"2-3 lines explaining future dev score...\",
        \"competition\": \"2-3 lines explaining competition score...\",
        \"regulatory\": \"2-3 lines explaining regulatory score...\"
    }
}";

        $jsonResponse = AIClient::callGemini($prompt, true);

        if (!$jsonResponse) {
            echo json_encode(['success' => false, 'error' => 'Failed to connect to AI Service.']);
            return;
        }

        $analysis = json_decode($jsonResponse, true);
        if (!$analysis || !isset($analysis['total_score'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON response from AI.']);
            return;
        }

        // Save to DB
        $stmt = $db->prepare("INSERT INTO market_analysis 
            (proposal_id, connectivity_score, infrastructure_score, demand_score, income_score, future_dev_score, competition_score, regulatory_score, total_score, raw_json_response) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Database Error on INSERT. Table schema might be incorrect: ' . $db->error]);
            return;
        }

        $stmt->bind_param('idddddddds', 
            $proposalId, 
            $analysis['connectivity_score'],
            $analysis['infrastructure_score'],
            $analysis['demand_score'],
            $analysis['income_score'],
            $analysis['future_dev_score'],
            $analysis['competition_score'],
            $analysis['regulatory_score'],
            $analysis['total_score'],
            $jsonResponse
        );

        if ($stmt->execute()) {
            $analysis['id'] = $stmt->insert_id;
            echo json_encode(['success' => true, 'data' => $analysis, 'message' => 'Analysis generated successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save analysis: ' . $stmt->error]);
        }
    }

    public static function generateFullAnalysis(): void {
        // Set higher execution time limit for long AI reports
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $proposalId = (int)($input['proposal_id'] ?? 0);

            if (!$proposalId) {
                echo json_encode(['success' => false, 'error' => 'Proposal ID is required.']);
                return;
            }

            // Fetch proposal details
            $proposal = Proposal::getById($proposalId);
            if (!$proposal) {
                echo json_encode(['success' => false, 'error' => 'Proposal not found.']);
                return;
            }

            $db = getDB();
            
            // Self-healing database check: if column full_analysis_text doesn't exist, create it automatically.
            $colCheck = $db->query("SHOW COLUMNS FROM market_analysis LIKE 'full_analysis_text'");
            if ($colCheck && $colCheck->num_rows === 0) {
                $db->query("ALTER TABLE market_analysis ADD COLUMN full_analysis_text LONGTEXT NULL");
            }
            
            // Convert table and column collation to utf8mb4 to prevent string formatting/encoding errors with Rupee symbols (₹)
            $db->query("ALTER TABLE market_analysis CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db->query("ALTER TABLE market_analysis MODIFY COLUMN full_analysis_text LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");

            $stmt = $db->prepare("SELECT * FROM market_analysis WHERE proposal_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database Error: market_analysis table not found. Please run the database migration.']);
                return;
            }
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $marketAnalysis = $stmt->get_result()->fetch_assoc();

            if ($marketAnalysis && !empty($marketAnalysis['full_analysis_text'])) {
                echo json_encode([
                    'success' => true,
                    'full_analysis_text' => $marketAnalysis['full_analysis_text'],
                    'message' => 'Loaded from cache.'
                ]);
                return;
            }

            $locationDetails = "Project Name: " . ($proposal['proposal_name'] ?? 'Unknown') . "\n";
            $locationDetails .= "Type: " . ($proposal['proposal_type'] ?? 'Unknown') . "\n";
            $locationDetails .= "Region: " . ($proposal['region'] ?? 'Unknown') . "\n";
            $locationDetails .= "City: " . ($proposal['city'] ?? 'Unknown') . "\n";
            $locationDetails .= "Address: " . ($proposal['site_address'] ?? 'Unknown') . "\n";
            $locationDetails .= "Pincode: " . ($proposal['pincode'] ?? '') . "\n";
            if (!empty($proposal['plot_area_sqm'])) {
                $locationDetails .= "Plot Area: " . $proposal['plot_area_sqm'] . " sqm\n";
            }
            if (!empty($proposal['avg_sale_price_sqft'])) {
                $locationDetails .= "Expected Sale Rate: ₹" . $proposal['avg_sale_price_sqft'] . " / sqft\n";
            }

            $existingScoresContext = "";
            if ($marketAnalysis) {
                $existingScoresContext = "We have already graded the location with the following scores (out of 100 max possible score weighted):
- Connectivity: " . $marketAnalysis['connectivity_score'] . "/20
- Infrastructure: " . $marketAnalysis['infrastructure_score'] . "/15
- Demand: " . $marketAnalysis['demand_score'] . "/20
- Income Levels: " . $marketAnalysis['income_score'] . "/10
- Future Developments: " . $marketAnalysis['future_dev_score'] . "/15
- Competition: " . $marketAnalysis['competition_score'] . "/10
- Regulatory Ease: " . $marketAnalysis['regulatory_score'] . "/10
Total Score: " . $marketAnalysis['total_score'] . "/100
Please incorporate and expand on these ratings in your detailed report. Keep scores aligned if mentioned.";
            }

            $prompt = "You are a senior real estate consultant and market analyst. Draft a highly professional, comprehensive, and exhaustive Market Analysis Report and Case Study for the following property proposal.

Property Details:
{$locationDetails}

{$existingScoresContext}

The report MUST include:
1. Executive Summary & Overview of the Micro-Market
2. Detailed Case Study of the 7 Key Desirability Factors (Connectivity, Infrastructure, Demand, Income Levels, Future Developments, Competition, Regulatory Ease) with deep reasoning.
3. Micro-Market Infrastructure & Amenities Inventory:
   - Nearby Highways & Transit Corridors (include specific routes and distances if possible)
   - Schools & Educational Institutions
   - Colleges & Universities
   - Hotels & Hospitality Establishments
   - Restaurants, F&B & Retail Hubs
   - Residential Density & Key Landmarks
   - Resorts & Leisure Spots nearby
4. Real Estate Valuation & Pricing Matrix (provide a clean HTML table inside the HTML with local estimates):
   - Average Sale Price per sqm of Residential space in this locality
   - Average Sale Price per sqm of Commercial space in this locality
   - Average Property Rate of Land in this locality
   - Average Property Rate of Residential projects in this locality
5. Developer Risk & Opportunity SWOT Analysis
6. Strategic Recommendation for Project Launch, Target Audience & Pricing Strategy

FORMAT REQUIREMENT:
Return ONLY clean, well-structured HTML code. Do NOT wrap the code in ```html or any other markdown text. Just output valid HTML blocks (using <div>, <h3>, <h4>, <p>, <ul>, <li>, <table class='table table-bordered table-striped mt-3'>, <strong>, <em>, and standard Bootstrap 5 helper classes for design and spacing like 'mb-4', 'p-3', 'bg-light', etc.). Make it look extremely premium, clean, and professional for presentation to senior executives. Ensure that the HTML is direct and fits perfectly into a clean container.";

            // Enforce daily limit: 2 market analysis reports
            require_once __DIR__ . '/AIController.php';
            if (!AIController::checkAndLogUsage('analysis', 2)) {
                echo json_encode(['success' => false, 'error' => 'You have reached your daily limit of 2 detailed market analysis generations. Please try again tomorrow.']);
                return;
            }

            // Close the database connection before the long-running Gemini API call to prevent MySQL timeouts
            $db->close();

            $htmlResult = AIClient::callGemini($prompt, false);

            if (!$htmlResult) {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to AI API or request timed out. Please try again.']);
                return;
            }

            $htmlResult = preg_replace('/^```html\s*/i', '', $htmlResult);
            $htmlResult = preg_replace('/^```\s*/i', '', $htmlResult);
            $htmlResult = preg_replace('/\s*```$/i', '', $htmlResult);
            $htmlResult = trim($htmlResult);

            // Replace Indian Rupee symbol (₹) with its HTML entity (&#8377;) for database compatibility
            $htmlResult = str_replace('₹', '&#8377;', $htmlResult);

            // Reopen a fresh database connection to save the result
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) {
                echo json_encode(['success' => false, 'error' => 'Database reconnect failed: ' . $db->connect_error]);
                return;
            }
            $db->set_charset(DB_CHARSET);

            if ($marketAnalysis) {
                $updateStmt = $db->prepare("UPDATE market_analysis SET full_analysis_text = ? WHERE proposal_id = ?");
                if (!$updateStmt) {
                    echo json_encode(['success' => false, 'error' => 'Database structure mismatch. Error: ' . $db->error]);
                    return;
                }
                $updateStmt->bind_param('si', $htmlResult, $proposalId);
                $updateStmt->execute();
            } else {
                $insertStmt = $db->prepare("INSERT INTO market_analysis (proposal_id, connectivity_score, infrastructure_score, demand_score, income_score, future_dev_score, competition_score, regulatory_score, total_score, raw_json_response, full_analysis_text) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, '{}', ?)");
                if (!$insertStmt) {
                    echo json_encode(['success' => false, 'error' => 'Database structure mismatch. Error: ' . $db->error]);
                    return;
                }
                $insertStmt->bind_param('is', $proposalId, $htmlResult);
                $insertStmt->execute();
            }

            echo json_encode([
                'success' => true,
                'full_analysis_text' => $htmlResult
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
        }
    }
}
