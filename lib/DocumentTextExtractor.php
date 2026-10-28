<?php
/**
 * DocumentTextExtractor — extract per-page text from a document.
 *
 * Uses pdftotext (bundled with Git for Windows / poppler-utils) for text-based PDFs.
 * OCR (scanned PDF) and DOC/DOCX paths are stubbed with clear error messages for Phase 3c.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AIClient.php';

class DocumentTextExtractor {

    /** If pdftotext produces fewer than this many words, treat the PDF as scanned and route through Gemini vision. */
    private const MIN_WORDS_FOR_TEXT_PDF = 30;

    /**
     * Runs pdftotext for a PDF, splits by page, upserts document_pages rows.
     * Returns ['ok', 'page_count', 'notes'].
     */
    public static function extract(int $documentId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM project_documents WHERE id = ?");
        $stmt->bind_param('i', $documentId);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        if (!$doc) return ['ok' => false, 'notes' => 'Document not found.'];

        $abs = __DIR__ . '/../uploads/documents/' . $doc['stored_path'];
        if (!file_exists($abs)) return ['ok' => false, 'notes' => 'File missing on disk.'];

        $mime = $doc['mime_type'];

        // Image → Gemini vision OCR
        if (strpos($mime, 'image/') === 0) {
            return self::extractViaGeminiVision($db, $documentId, $abs, $mime, 'image');
        }

        // DOC / DOCX → try pandoc
        if ($mime === 'application/msword'
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return self::extractDocx($db, $documentId, $abs);
        }

        if ($mime !== 'application/pdf') {
            $note = 'Unsupported mime: ' . $mime;
            $upd = $db->prepare("UPDATE project_documents SET text_extracted = 0, extraction_note = ? WHERE id = ?");
            $upd->bind_param('si', $note, $documentId);
            $upd->execute();
            return ['ok' => false, 'notes' => $note];
        }

        // Locate pdftotext
        $pdftotext = self::pdftotextPath();
        if (!$pdftotext) return ['ok' => false, 'notes' => 'pdftotext binary not available on server.'];

        // Extract per-page text using -f/-l page bounds
        // First get total page count via pdfinfo if available, else probe.
        $pageCount = self::probePageCount($abs) ?: self::probePageCountFallback($pdftotext, $abs);

        if ($pageCount <= 0) return ['ok' => false, 'notes' => 'Could not determine page count.'];

        // Wipe prior pages for this document (idempotent re-extract)
        $del = $db->prepare("DELETE FROM document_pages WHERE document_id = ?");
        $del->bind_param('i', $documentId);
        $del->execute();

        $ins = $db->prepare("INSERT INTO document_pages (document_id, page_no, text, ocr_used, word_count) VALUES (?, ?, ?, 0, ?)");

        $totalWords = 0;
        for ($p = 1; $p <= $pageCount; $p++) {
            $tmpOut = tempnam(sys_get_temp_dir(), 'pdftxt');
            $cmd = escapeshellcmd($pdftotext) . ' -layout -f ' . $p . ' -l ' . $p . ' ' . escapeshellarg($abs) . ' ' . escapeshellarg($tmpOut);
            @exec($cmd, $out, $rc);
            $pageText = @file_get_contents($tmpOut) ?: '';
            @unlink($tmpOut);

            $words = str_word_count($pageText);
            $totalWords += $words;

            $ins->bind_param('iisi', $documentId, $p, $pageText, $words);
            $ins->execute();
        }

        // Scanned-PDF detection: if the whole doc has < N words per-page average, route through Gemini vision.
        $avgWordsPerPage = $pageCount > 0 ? $totalWords / $pageCount : 0;
        if ($avgWordsPerPage < self::MIN_WORDS_FOR_TEXT_PDF) {
            $result = self::extractViaGeminiVision($db, $documentId, $abs, 'application/pdf', 'scanned-pdf');
            if ($result['ok']) return $result;
            // Vision failed — keep whatever pdftotext managed, mark low-content
            $note = "Only $totalWords words extracted (avg " . number_format($avgWordsPerPage, 1) . "/page). Vision fallback also failed: " . $result['notes'];
            $upd = $db->prepare("UPDATE project_documents SET text_extracted = 1, page_count = ?, extraction_note = ? WHERE id = ?");
            $upd->bind_param('isi', $pageCount, $note, $documentId);
            $upd->execute();
            return ['ok' => true, 'page_count' => $pageCount, 'notes' => $note];
        }

        $note = "Extracted $pageCount pages · $totalWords words total (pdftotext)";
        $upd = $db->prepare("UPDATE project_documents SET text_extracted = 1, page_count = ?, extraction_note = ? WHERE id = ?");
        $upd->bind_param('isi', $pageCount, $note, $documentId);
        $upd->execute();

        return ['ok' => true, 'page_count' => $pageCount, 'notes' => $note];
    }

    /**
     * Route a file through Gemini vision to OCR it into per-page text.
     * Works for both scanned PDFs and single-page images.
     */
    private static function extractViaGeminiVision(mysqli $db, int $documentId, string $abs, string $mime, string $label): array {
        $prompt = <<<PROMPT
You are an OCR engine. Extract ALL text from this document exactly as it appears.

FORMAT RULES:
1. Prefix each page with exactly this marker on its own line: "===== PAGE N ====="  where N is the page number (start at 1).
2. Preserve the reading order.
3. Preserve tables as pipe-delimited rows where possible.
4. Do NOT summarize, translate, or infer. Copy verbatim including punctuation and numbers.
5. If a page is blank, still emit its marker followed by "(blank page)".
6. For a single-page image, use "===== PAGE 1 =====".

Return the raw extracted text only — no commentary, no JSON.
PROMPT;

        $ocrText = AIClient::callGeminiWithFile($prompt, $abs, $mime, false);
        if (!$ocrText) {
            $note = "Gemini vision returned no text (label=$label). Check API key + model + file.";
            $upd = $db->prepare("UPDATE project_documents SET text_extracted = 0, extraction_note = ? WHERE id = ?");
            $upd->bind_param('si', $note, $documentId);
            $upd->execute();
            return ['ok' => false, 'notes' => $note];
        }

        // Split the response on the PAGE markers
        $pages = self::splitByPageMarkers($ocrText);
        if (empty($pages)) {
            // Single monolithic block — treat as page 1
            $pages = [1 => $ocrText];
        }

        // Wipe prior pages
        $del = $db->prepare("DELETE FROM document_pages WHERE document_id = ?");
        $del->bind_param('i', $documentId);
        $del->execute();

        $ins = $db->prepare("INSERT INTO document_pages (document_id, page_no, text, ocr_used, word_count) VALUES (?, ?, ?, 1, ?)");
        $pageCount = 0; $totalWords = 0;
        foreach ($pages as $pageNo => $text) {
            $pageNo = (int)$pageNo;
            $words = str_word_count($text);
            $totalWords += $words;
            $ins->bind_param('iisi', $documentId, $pageNo, $text, $words);
            $ins->execute();
            $pageCount++;
        }

        $note = "OCR via Gemini vision (label=$label): $pageCount pages · $totalWords words total";
        $upd = $db->prepare("UPDATE project_documents SET text_extracted = 1, page_count = ?, extraction_note = ? WHERE id = ?");
        $upd->bind_param('isi', $pageCount, $note, $documentId);
        $upd->execute();
        return ['ok' => true, 'page_count' => $pageCount, 'notes' => $note];
    }

    private static function splitByPageMarkers(string $text): array {
        // Match "===== PAGE N =====" with flexible whitespace
        if (!preg_match_all('/=====\s*PAGE\s+(\d+)\s*=====/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $pages = [];
        $count = count($m[0]);
        for ($i = 0; $i < $count; $i++) {
            $pageNo = (int)$m[1][$i][0];
            $start  = $m[0][$i][1] + strlen($m[0][$i][0]);
            $end    = ($i + 1 < $count) ? $m[0][$i + 1][1] : strlen($text);
            $pages[$pageNo] = trim(substr($text, $start, $end - $start));
        }
        return $pages;
    }

    /**
     * Extract DOC/DOCX via pandoc if available; else emit a clear "convert to PDF" message.
     */
    private static function extractDocx(mysqli $db, int $documentId, string $abs): array {
        $pandoc = self::pandocPath();
        if (!$pandoc) {
            $note = 'DOC/DOCX support requires pandoc (not installed on server). Please save the file as PDF and re-upload.';
            $upd = $db->prepare("UPDATE project_documents SET text_extracted = 0, extraction_note = ? WHERE id = ?");
            $upd->bind_param('si', $note, $documentId);
            $upd->execute();
            return ['ok' => false, 'notes' => $note];
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'docx');
        $cmd = escapeshellcmd($pandoc) . ' ' . escapeshellarg($abs) . ' -t plain -o ' . escapeshellarg($tmpOut);
        @exec($cmd, $out, $rc);
        $text = @file_get_contents($tmpOut) ?: '';
        @unlink($tmpOut);

        if ($rc !== 0 || $text === '') {
            $note = "pandoc failed to convert (rc=$rc).";
            $upd = $db->prepare("UPDATE project_documents SET text_extracted = 0, extraction_note = ? WHERE id = ?");
            $upd->bind_param('si', $note, $documentId);
            $upd->execute();
            return ['ok' => false, 'notes' => $note];
        }

        // Treat as single "page 1" since DOC/DOCX has no fixed pagination in plain-text form
        $del = $db->prepare("DELETE FROM document_pages WHERE document_id = ?");
        $del->bind_param('i', $documentId);
        $del->execute();

        $ins = $db->prepare("INSERT INTO document_pages (document_id, page_no, text, ocr_used, word_count) VALUES (?, 1, ?, 0, ?)");
        $words = str_word_count($text);
        $ins->bind_param('isi', $documentId, $text, $words);
        $ins->execute();

        $note = "Extracted DOCX via pandoc: 1 logical page · $words words";
        $upd = $db->prepare("UPDATE project_documents SET text_extracted = 1, page_count = 1, extraction_note = ? WHERE id = ?");
        $upd->bind_param('si', $note, $documentId);
        $upd->execute();
        return ['ok' => true, 'page_count' => 1, 'notes' => $note];
    }

    private static function pandocPath(): ?string {
        $out = [];
        @exec('where pandoc 2>NUL', $out);
        if (!empty($out) && file_exists(trim($out[0]))) return trim($out[0]);
        foreach (['C:/Program Files/Pandoc/pandoc.exe', 'C:/Program Files (x86)/Pandoc/pandoc.exe'] as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    private static function unsupportedNote(string $mime): string {
        if (strpos($mime, 'image/') === 0) {
            return 'OCR for images is not enabled in this phase (Phase 3c will route images through Gemini vision).';
        }
        if (strpos($mime, 'wordprocessingml') !== false || $mime === 'application/msword') {
            return 'DOC/DOCX support is not enabled in this phase. Please save the document as PDF and re-upload.';
        }
        return 'Extraction for mime type ' . $mime . ' not supported in this phase.';
    }

    private static function pdftotextPath(): ?string {
        // Try PATH first
        $out = [];
        @exec('where pdftotext 2>NUL', $out);
        if (!empty($out) && file_exists(trim($out[0]))) return trim($out[0]);
        // Known Windows locations
        $candidates = [
            'C:/Program Files/Git/mingw64/bin/pdftotext.exe',
            'C:/Program Files (x86)/Git/mingw64/bin/pdftotext.exe',
        ];
        foreach ($candidates as $c) if (file_exists($c)) return $c;
        return null;
    }

    private static function probePageCount(string $pdfAbs): int {
        // Attempt pdfinfo (part of poppler-utils, often bundled next to pdftotext)
        $pdfinfoCandidates = [
            'C:/Program Files/Git/mingw64/bin/pdfinfo.exe',
            'C:/Program Files (x86)/Git/mingw64/bin/pdfinfo.exe',
        ];
        foreach ($pdfinfoCandidates as $p) {
            if (file_exists($p)) {
                $out = [];
                @exec(escapeshellcmd($p) . ' ' . escapeshellarg($pdfAbs), $out);
                foreach ($out as $line) {
                    if (preg_match('/^Pages:\s+(\d+)/i', $line, $m)) return (int)$m[1];
                }
            }
        }
        return 0;
    }

    private static function probePageCountFallback(string $pdftotext, string $pdfAbs): int {
        // Extract everything to one file, count form-feed pages
        $tmp = tempnam(sys_get_temp_dir(), 'pdfall');
        @exec(escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($pdfAbs) . ' ' . escapeshellarg($tmp), $out, $rc);
        $txt = @file_get_contents($tmp) ?: '';
        @unlink($tmp);
        if ($txt === '') return 0;
        return substr_count($txt, "\f") + 1;
    }
}
