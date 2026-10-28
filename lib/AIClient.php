<?php

require_once __DIR__ . '/../config/ai_config.php';

class AIClient {
    
    /**
     * Sends a prompt to the Google Gemini API and returns the generated text.
     * 
     * @param string $prompt The instruction or question for the AI.
     * @param bool $jsonMode If true, requests the AI to return a strict JSON object.
     * @return string|false The AI's text response, or false on failure.
     */
    public static function callGemini(string $prompt, bool $jsonMode = false) {
        $apiKey = GEMINI_API_KEY;
        $model = GEMINI_MODEL;

        if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
            error_log("AIClient Error: Gemini API key is not configured.");
            return false;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // If JSON mode is requested, explicitly instruct the model to return JSON
        if ($jsonMode) {
            $prompt .= "\n\nIMPORTANT: Return ONLY a valid JSON object. Do not include markdown formatting like ```json or any conversational text.";
        }

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $jsonMode ? 0.1 : 0.7, // Lower temperature for JSON for more deterministic output
            ]
        ];

        if ($jsonMode) {
            // Some newer Gemini models support strict JSON formatting via configuration
            $data['generationConfig']['responseMimeType'] = 'application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP environments
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Allow up to 2 minutes for long AI tasks
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("AIClient cURL Error: " . $error);
            return false;
        }

        $responseData = json_decode($response, true);

        // Check for API errors
        if (isset($responseData['error'])) {
            error_log("AIClient API Error: " . print_r($responseData['error'], true));
            return false;
        }

        // Extract text from Gemini response structure
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
            
            // Clean up markdown code blocks if the AI still included them despite instructions
            if ($jsonMode) {
                $text = preg_replace('/^```json\s*/i', '', $text);
                $text = preg_replace('/^```\s*/i', '', $text);
                $text = preg_replace('/\s*```$/i', '', $text);
            }
            
            return trim($text);
        }

        return false;
    }

    /**
     * Sends a prompt + file (PDF or image) to Gemini for vision/OCR-style processing.
     * Gemini accepts PDF / JPEG / PNG / WEBP inline; each file must be Base64-encoded.
     *
     * @param string $prompt      Instruction for the model
     * @param string $filePath    Absolute path to the file
     * @param string $mimeType    e.g. application/pdf | image/png | image/jpeg
     * @param bool   $jsonMode
     * @return string|false Text response, or false on failure
     */
    public static function callGeminiWithFile(string $prompt, string $filePath, string $mimeType, bool $jsonMode = false) {
        $apiKey = GEMINI_API_KEY;
        $model  = GEMINI_MODEL;

        if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
            error_log("AIClient Error: Gemini API key is not configured.");
            return false;
        }
        if (!file_exists($filePath)) {
            error_log("AIClient Vision: file not found: $filePath");
            return false;
        }

        $bytes = @file_get_contents($filePath);
        if ($bytes === false) return false;
        // Hard cap 20MB inline to keep request bodies sane. Larger files should be split.
        if (strlen($bytes) > 20 * 1024 * 1024) {
            error_log("AIClient Vision: file too large for inline call: " . strlen($bytes));
            return false;
        }

        if ($jsonMode) {
            $prompt .= "\n\nIMPORTANT: Return ONLY a valid JSON object. No markdown, no ```json fences.";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $data = [
            'contents' => [[
                'parts' => [
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($bytes)]],
                    ['text' => $prompt],
                ]
            ]],
            'generationConfig' => [
                'temperature' => $jsonMode ? 0.1 : 0.3, // OCR wants low temp for fidelity
            ]
        ];
        if ($jsonMode) $data['generationConfig']['responseMimeType'] = 'application/json';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Vision on multi-page PDF can take a few minutes

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) { error_log("AIClient Vision cURL Error: " . $error); return false; }
        $resp = json_decode($response, true);
        if (isset($resp['error'])) { error_log("AIClient Vision API Error: " . print_r($resp['error'], true)); return false; }
        if (isset($resp['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $resp['candidates'][0]['content']['parts'][0]['text'];
            if ($jsonMode) {
                $text = preg_replace('/^```json\s*/i', '', $text);
                $text = preg_replace('/^```\s*/i', '', $text);
                $text = preg_replace('/\s*```$/i', '', $text);
            }
            return trim($text);
        }
        return false;
    }

    /**
     * Sends a prompt to the OpenAI ChatGPT API and returns the generated text.
     * 
     * @param string $prompt The instruction or question for the AI.
     * @param bool $jsonMode If true, requests the AI to return a strict JSON object.
     * @return string|false The AI's text response, or false on failure.
     */
    public static function callChatGPT(string $prompt, bool $jsonMode = false) {
        $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

        if (empty($apiKey)) {
            error_log("AIClient Error: OpenAI API key is not configured.");
            return false;
        }

        $url = "https://api.openai.com/v1/chat/completions";

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $jsonMode ? 0.1 : 0.7,
        ];

        if ($jsonMode) {
            $data['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("AIClient ChatGPT cURL Error: " . $error);
            return false;
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            error_log("AIClient ChatGPT API Error: " . print_r($responseData['error'], true));
            return false;
        }

        if (isset($responseData['choices'][0]['message']['content'])) {
            $text = $responseData['choices'][0]['message']['content'];
            return trim($text);
        }

        return false;
    }
}
