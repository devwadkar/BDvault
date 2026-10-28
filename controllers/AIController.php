<?php

require_once __DIR__ . '/../lib/AIClient.php';

class AIController {

    /**
     * API Endpoint: api_ai_extract
     * Receives raw text via POST, sends it to Gemini to extract structured JSON data.
     */
    public static function extractData(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
            return;
        }

        // Get JSON payload
        $inputRaw = file_get_contents('php://input');
        file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Incoming Request: " . $inputRaw . "\n", FILE_APPEND);
        
        $input = json_decode($inputRaw, true);
        $rawText = $input['text'] ?? '';

        if (empty(trim($rawText))) {
            file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Error: No text provided.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'No text provided.']);
            return;
        }

        // Build the prompt requesting strict JSON extraction
        $prompt = "You are a Real Estate Business Development Assistant. Your task is to extract data from the following raw text/email and map it to a strictly formatted JSON object.
        
        The JSON object MUST contain the following keys. If a value is not found in the text, use null or an empty string. 
        For numeric fields, extract ONLY the number (no commas or currency symbols).
        
        Expected Keys:
        - proposal_name (string, e.g. 'Sunshine Society Redevelopment')
        - proposal_type (string, must be one of: 'Society Redevelopment', 'DM Model', 'Outright Purchase', 'Joint Venture')
        - region (string, e.g. 'Andheri East', 'Bandra West')
        - city (string)
        - site_address (string)
        - pincode (string)
        - reference_owner (string, the name of the owner or society chairman)
        - consultant_name_number (string, the name and contact of the broker/consultant)
        - plot_area_sqm (number, convert sq.ft to sq.m if necessary. 1 sqm = 10.764 sqft)
        - avg_sale_price_sqft (number, expected selling rate per sqft)
        - entry_cost_per_sqft (number)
        - existing_fsi_used (number, e.g., 0.8)
        - free_sale_area_pct (number, percentage 0-100)
        - society_allocation_pct (number, percentage 0-100)
        - existing_units (number)
        - proposed_units (number)
        - completion_timeline_yrs (number, duration in years)
        - remarks (string, any other important context or notes)
        
        Raw Text to Extract From:
        \"\"\"
        {$rawText}
        \"\"\"
        ";

        // Call Gemini in JSON mode
        $jsonResult = AIClient::callGemini($prompt, true);

        if ($jsonResult === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to connect to AI Service. Please check API Key configuration.']);
            return;
        }

        // Validate the response is actually JSON
        $decoded = json_decode($jsonResult, true);
        if ($decoded === null) {
            echo json_encode(['success' => false, 'error' => 'AI returned invalid JSON format.']);
            return;
        }

        $responsePayload = json_encode([
            'success' => true,
            'data' => $decoded
        ]);
        
        file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Success Response: " . $responsePayload . "\n", FILE_APPEND);
        echo $responsePayload;
    }

    /**
     * API Endpoint: api_ai_chat
     * Natural Language to SQL and back to Text.
     */
    public static function chatQuery(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';

        if (empty(trim($question))) {
            echo json_encode(['success' => false, 'error' => 'No question provided.']);
            return;
        }

        // 1. Text to SQL
        $schema = "
        Table: proposals (p)
        Columns:
          - id (INT, PRIMARY KEY)
          - proposal_name (VARCHAR)
          - proposal_type (VARCHAR, e.g. 'Society Redevelopment', 'DM Model', 'Outright Purchase', 'Joint Venture')
          - region (VARCHAR, e.g. 'Bandra West', 'Andheri East', 'Chembur')
          - city (VARCHAR, e.g. 'Mumbai', 'Thane')
          - site_address (TEXT)
          - pincode (VARCHAR)
          - plot_area_sqm (DECIMAL, technical plot size in sq. meters)
          - avg_sale_price_sqft (DECIMAL, carpet sale rate in ₹/sqft)
          - entry_cost_per_sqft (DECIMAL)
          - existing_fsi_used (DECIMAL)
          - free_sale_area_pct (DECIMAL)
          - society_allocation_pct (DECIMAL)
          - existing_units (INT)
          - proposed_units (INT)
          - completion_timeline_yrs (DECIMAL)
          - location_rating (TINYINT, subjective location rating 1-10)
          - approval_complexity (TINYINT, 1-10)
          - market_demand (TINYINT, 1-10)
          - is_sra (TINYINT/Boolean, 1 if SRA, else 0)
          - is_outright (TINYINT/Boolean, 1 if Outright Purchase, else 0)
          - is_mhada (TINYINT/Boolean, 1 if MHADA project, else 0)
          - is_stressed (TINYINT/Boolean, 1 if Stressed/NPAs, else 0)
          - status (VARCHAR, e.g. 'Screened', 'Scored', 'In Review')
          - remarks (TEXT)
          
        Table: stage1_results (s1)
        Columns:
          - proposal_id (INT, FOREIGN KEY)
          - is_eligible (TINYINT/Boolean, 1 if passed all technical filters / is eligible, 0 if failed/rejected)
          - rejection_reasons (TEXT, JSON list of reasons why it failed)
          
        Table: stage2_scores (s2)
        Columns:
          - proposal_id (INT, FOREIGN KEY)
          - total_score (DECIMAL, weighted score 0-100)
          - category (VARCHAR, scoring level: 'High Potential', 'Moderate', 'Low')
          - probability_pct (DECIMAL, conversion probability 0-100)
          
        Relationships:
          - s1.proposal_id = p.id
          - s2.proposal_id = p.id
        ";

        $prompt1 = "You are a strictly read-only SQL generator for a MySQL database. Given this schema:
        {$schema}
        
        Write a valid MySQL SELECT query to answer this user question: \"{$question}\"
        
        RULES:
        1. Only return the raw SQL query. No explanation, no markdown formatting (like ```sql).
        2. ALWAYS use a SELECT statement. Never INSERT, UPDATE, DELETE, or DROP.
        3. If joining, use the aliases (p, s1, s2) and relationships defined above.
        4. Limit the results to a maximum of 20 rows using LIMIT 20 to prevent massive queries.
        5. For boolean fields (is_sra, is_mhada, is_outright, is_stressed, is_eligible), always query using '= 1' or '= 0'.";

        $sql = AIClient::callGemini($prompt1, false);
        
        if (!$sql || stripos(trim($sql), 'SELECT') !== 0) {
            echo json_encode(['success' => false, 'error' => 'AI could not understand the question or generated an invalid query.']);
            return;
        }

        // Clean up markdown just in case
        $sql = preg_replace('/^```sql\s*/i', '', trim($sql));
        $sql = preg_replace('/^```\s*/i', '', $sql);
        $sql = preg_replace('/\s*```$/i', '', $sql);
        $sql = trim($sql);

        file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Chat Question: " . $question . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Generated SQL: " . $sql . "\n", FILE_APPEND);

        // Safety check to prevent destructive queries
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|GRANT|REVOKE)\b/i', $sql)) {
            file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] Blocked Unsafe SQL\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'Unsafe query blocked for security reasons.']);
            return;
        }

        // Execute query
        require_once __DIR__ . '/../config/database.php';
        $db = getDB();
        
        $result = $db->query($sql);
        if (!$result) {
            file_put_contents(__DIR__ . '/../ai_debug.log', "[" . date('Y-m-d H:i:s') . "] DB Error: " . $db->error . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'Database error executing query: ' . $db->error, 'sql' => $sql]);
            return;
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);

        // 2. Data to Text
        $prompt2 = "You are a helpful Real Estate Business Development Assistant. 
        The user asked: \"{$question}\"
        
        Here is the raw JSON data returned from the database:
        " . json_encode($data) . "
        
        Provide a concise, conversational answer to the user's question based strictly on this data. Do not mention that you used SQL or a database. If the data array is empty, tell the user no matching records were found.";

        $finalAnswer = AIClient::callGemini($prompt2, false);

        if (!$finalAnswer) {
            // Fallback: Format the data nicely in a clean HTML table
            if (empty($data)) {
                $finalAnswer = "No matching records were found in the database.";
            } else {
                // If it's a single count/value, format as a simple statement
                if (count($data) === 1 && count($data[0]) === 1) {
                    $val = reset($data[0]);
                    $key = key($data[0]);
                    $friendlyKey = str_replace('_', ' ', ucwords($key));
                    $finalAnswer = "<div class='alert alert-info py-2 px-3 mb-0'><strong>{$friendlyKey}:</strong> " . htmlspecialchars($val ?? '0') . "</div>";
                } else {
                    $finalAnswer = "<p class='mb-2 text-muted'>I successfully queried the database, but formatted the result as a table due to AI API rate limits:</p>";
                    $finalAnswer .= "<div class='table-responsive'><table class='table table-sm table-bordered table-striped mt-1 mb-0'>";
                    $finalAnswer .= "<thead class='table-light'><tr>";
                    foreach (array_keys($data[0]) as $key) {
                        $finalAnswer .= "<th>" . htmlspecialchars(str_replace('_', ' ', ucwords($key))) . "</th>";
                    }
                    $finalAnswer .= "</tr></thead><tbody>";
                    foreach ($data as $row) {
                        $finalAnswer .= "<tr>";
                        foreach ($row as $val) {
                            $finalAnswer .= "<td>" . htmlspecialchars($val ?? 'N/A') . "</td>";
                        }
                        $finalAnswer .= "</tr>";
                    }
                    $finalAnswer .= "</tbody></table></div>";
                }
            }
        }

        echo json_encode([
            'success' => true,
            'answer' => $finalAnswer,
            'sql' => $sql
        ]);
    }

    public static function autoEstimateStage1(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid method.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $locStr = [];
        if (!empty($input['proposal_name'])) $locStr[] = "Project Name: " . $input['proposal_name'];
        if (!empty($input['proposal_type'])) $locStr[] = "Type: " . $input['proposal_type'];
        if (!empty($input['region'])) $locStr[] = "Region: " . $input['region'];
        if (!empty($input['city'])) $locStr[] = "City: " . $input['city'];
        if (!empty($input['site_address'])) $locStr[] = "Address: " . $input['site_address'];
        if (!empty($input['pincode'])) $locStr[] = "Pincode: " . $input['pincode'];

        if (empty($locStr)) {
            echo json_encode(['success' => false, 'error' => 'Insufficient location data.']);
            return;
        }

        $details = implode("\n", $locStr);

        $prompt = "You are an expert Indian Real Estate Market Analyst. Based on the following property/project details, estimate realistic values for these Stage 1 feasibility parameters.

Property Details:
{$details}

Parameters to estimate:
1. avg_sale_price_sqft: The average carpet area sale price in ₹ per sqft for this micro-market/location. Use your knowledge of Indian real estate prices.
2. entry_cost_per_sqft: A realistic acquisition/entry cost per sqft in ₹ for a developer entering this micro-market. Typically 30-50% of sale rate.
3. existing_fsi_used: The typical existing FSI used for old buildings/societies in this area. Usually between 0.5 and 1.5.
4. free_sale_area_pct: The typical percentage of total built-up area available as free sale area after society allocation. Usually 55-75%.
5. society_allocation_pct: The typical percentage allocated to existing society members in redevelopment. Usually 30-50%.
6. completion_timeline_yrs: Typical project completion timeline in years for this type of project and location. Usually 3-6 years.

Return a STRICT JSON object with only these numeric fields (no text, no explanation):
{
    \"avg_sale_price_sqft\": 45000,
    \"entry_cost_per_sqft\": 18000,
    \"existing_fsi_used\": 0.8,
    \"free_sale_area_pct\": 65,
    \"society_allocation_pct\": 35,
    \"completion_timeline_yrs\": 4
}";

        $jsonResponse = AIClient::callGemini($prompt, true);

        if (!$jsonResponse) {
            echo json_encode(['success' => false, 'error' => 'Failed to connect to AI API.']);
            return;
        }

        $data = json_decode($jsonResponse, true);

        if (!isset($data['avg_sale_price_sqft'])) {
            echo json_encode(['success' => false, 'error' => 'AI returned malformed data.']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $data]);
    }


    public static function autoScoreStage2(): void {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid method.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $locStr = [];
        if (!empty($input['region'])) $locStr[] = "Region: " . $input['region'];
        if (!empty($input['city'])) $locStr[] = "City: " . $input['city'];
        if (!empty($input['site_address'])) $locStr[] = "Address: " . $input['site_address'];
        if (!empty($input['plot_area_sqm'])) $locStr[] = "Plot Area: " . $input['plot_area_sqm'] . " sqm";
        if (!empty($input['avg_sale_price_sqft'])) $locStr[] = "Sale Rate: ₹" . $input['avg_sale_price_sqft'] . "/sqft";

        if (empty($locStr)) {
            echo json_encode(['success' => false, 'error' => 'Insufficient location data provided.']);
            return;
        }

        $details = implode("\n", $locStr);

        $prompt = "You are an expert Real Estate Assessor. Based on the following property details, provide a score from 1 to 10 (integers only) for three specific parameters.

Property Details:
{$details}

Parameters to Score (1 to 10 scale):
1. location_rating: 1 = poor location, 10 = premium micro-market.
2. approval_complexity: 1 = simple/ready approvals, 10 = extremely complex (e.g. coastal zones, highly restricted areas).
3. market_demand: 1 = low demand, 10 = very high absorption / fast sales velocity.

Return a STRICT JSON object exactly in this format:
{
    \"location_rating\": 7,
    \"approval_complexity\": 4,
    \"market_demand\": 8
}";

        $jsonResponse = AIClient::callGemini($prompt, true);

        if (!$jsonResponse) {
            echo json_encode(['success' => false, 'error' => 'Failed to connect to AI API.']);
            return;
        }

        $data = json_decode($jsonResponse, true);

        if (!isset($data['location_rating']) || !isset($data['approval_complexity']) || !isset($data['market_demand'])) {
            echo json_encode(['success' => false, 'error' => 'AI returned malformed data.']);
            return;
        }

        echo json_encode(['success' => true, 'data' => $data]);
    }

    public static function underwriteChat(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
            return;
        }

        // Enforce daily limit: 5 chatbot questions
        if (!self::checkAndLogUsage('chat', 5)) {
            echo json_encode(['success' => false, 'error' => 'You have reached your daily limit of 5 chatbot questions. Please try again tomorrow.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $proposalId = (int)($input['proposal_id'] ?? 0);
        $question = trim($input['question'] ?? '');

        if (!$proposalId || empty($question)) {
            echo json_encode(['success' => false, 'error' => 'Proposal ID and question are required.']);
            return;
        }

        // Fetch proposal details
        require_once __DIR__ . '/../models/Proposal.php';
        $proposal = Proposal::getById($proposalId);
        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Proposal not found.']);
            return;
        }

        // Fetch market analysis scores if available
        $marketAnalysis = Proposal::getMarketAnalysis($proposalId);

        // Build proposal statistics context
        $pContext = "PROPOSAL FINANCIAL & PHYSICAL METRICS:\n";
        $pContext .= "- Project Name: " . ($proposal['proposal_name'] ?? 'N/A') . "\n";
        $pContext .= "- Type: " . ($proposal['proposal_type'] ?? 'N/A') . "\n";
        $pContext .= "- Location: " . ($proposal['region'] ?? 'N/A') . ", " . ($proposal['city'] ?? 'N/A') . "\n";
        $pContext .= "- Plot Area: " . ($proposal['plot_area_sqm'] ? $proposal['plot_area_sqm'] . " sqm" : 'N/A') . "\n";
        $pContext .= "- Avg Sale Rate: " . ($proposal['avg_sale_price_sqft'] ? "₹" . $proposal['avg_sale_price_sqft'] . "/sqft" : 'N/A') . "\n";
        $pContext .= "- Entry Cost: " . ($proposal['entry_cost_per_sqft'] ? "₹" . $proposal['entry_cost_per_sqft'] . "/sqft" : 'N/A') . "\n";
        $pContext .= "- Existing FSI Used: " . ($proposal['existing_fsi_used'] ?? 'N/A') . "\n";
        $pContext .= "- Free Sale Area %: " . ($proposal['free_sale_area_pct'] ? $proposal['free_sale_area_pct'] . "%" : 'N/A') . "\n";
        $pContext .= "- Society Allocation %: " . ($proposal['society_allocation_pct'] ? $proposal['society_allocation_pct'] . "%" : 'N/A') . "\n";
        $pContext .= "- Timeline: " . ($proposal['completion_timeline_yrs'] ? $proposal['completion_timeline_yrs'] . " years" : 'N/A') . "\n";
        $pContext .= "- Existing/Proposed Units: " . ($proposal['existing_units'] ?? 'N/A') . " / " . ($proposal['proposed_units'] ?? 'N/A') . "\n";
        $pContext .= "- Location Rating: " . ($proposal['location_rating'] ?? 'N/A') . "/10, Approval Complexity: " . ($proposal['approval_complexity'] ?? 'N/A') . "/10, Market Demand: " . ($proposal['market_demand'] ?? 'N/A') . "/10\n";
        $pContext .= "- Remarks: " . ($proposal['remarks'] ?? 'None') . "\n\n";

        if ($marketAnalysis) {
            $pContext .= "MARKET ANALYSIS RATINGS:\n";
            $pContext .= "- Connectivity: " . $marketAnalysis['connectivity_score'] . "/20\n";
            $pContext .= "- Infrastructure: " . $marketAnalysis['infrastructure_score'] . "/15\n";
            $pContext .= "- Demand: " . $marketAnalysis['demand_score'] . "/20\n";
            $pContext .= "- Income Levels: " . $marketAnalysis['income_score'] . "/10\n";
            $pContext .= "- Future Developments: " . $marketAnalysis['future_dev_score'] . "/15\n";
            $pContext .= "- Competition: " . $marketAnalysis['competition_score'] . "/10\n";
            $pContext .= "- Regulatory Ease: " . $marketAnalysis['regulatory_score'] . "/10\n";
            $pContext .= "- Overall Score: " . $marketAnalysis['total_score'] . "/100\n\n";
        }

        // Format conversation history
        $historyStr = "";
        $history = $input['history'] ?? [];
        if (!empty($history)) {
            $historyStr = "CONVERSATION HISTORY:\n";
            foreach ($history as $msg) {
                $role = ($msg['role'] === 'user') ? 'User' : 'Underwriter';
                $historyStr .= "- {$role}: " . $msg['text'] . "\n";
            }
            $historyStr .= "\n";
        }

        $systemPrompt = "You are a Senior Real Estate Investment and Redevelopment Underwriter specializing in the Mumbai/Maharashtra micro-markets.
Your goal is to evaluate the feasibility, risks, strengths, and negotiation strategies for the proposal details provided.

Here is the data for the proposal you are underwriting:
\"\"\"
{$pContext}
\"\"\"

{$historyStr}Based on the above context, answer the user's question. Be brief, highly strategic, realistic about local dynamics (MCGM regulations, FSI premiums, society member consent, local ward liaison issues, cost escalations), and clear. Do not repeat standard definitions. Use bullet points for structural answers.

User's Question: \"{$question}\"";

        $response = AIClient::callGemini($systemPrompt, false);

        if (!$response) {
            echo json_encode(['success' => false, 'error' => 'Underwriter failed to respond. please try again.']);
            return;
        }

        // Replace any rupee symbols with HTML entity for safety
        $response = str_replace('₹', '&#8377;', $response);

        echo json_encode([
            'success' => true,
            'answer' => $response
        ]);
    }

    public static function checkAndLogUsage(string $actionType, int $limit): bool {
        $userId = (int)TokenAuth::userId();
        if (!$userId) {
            return false; // must be logged in
        }

        $db = getDB();

        // Self-healing database check: if table ai_usage_logs doesn't exist, create it automatically.
        $tableCheck = $db->query("SHOW TABLES LIKE 'ai_usage_logs'");
        if ($tableCheck && $tableCheck->num_rows === 0) {
            $db->query("CREATE TABLE IF NOT EXISTS ai_usage_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action_type VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        }

        // Count today's logs for this user & action
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM ai_usage_logs WHERE user_id = ? AND action_type = ? AND DATE(created_at) = CURRENT_DATE()");
        if (!$stmt) {
            return true; // if database check fails, don't block the user (graceful fallback)
        }

        $stmt->bind_param('is', $userId, $actionType);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $count = (int)($res['count'] ?? 0);

        if ($count >= $limit) {
            return false;
        }

        // Log the usage
        $logStmt = $db->prepare("INSERT INTO ai_usage_logs (user_id, action_type) VALUES (?, ?)");
        if ($logStmt) {
            $logStmt->bind_param('is', $userId, $actionType);
            $logStmt->execute();
        }

        return true;
    }
}
