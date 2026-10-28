<?php
/**
 * DocumentStorage — safe file upload and storage for project documents.
 *
 * Files are stored under uploads/documents/{proposal_id}/{doc_id}_{safe_name}.
 * The uploads directory carries an .htaccess deny — access is only via DocumentsController::download().
 */

class DocumentStorage {

    private const UPLOAD_ROOT = __DIR__ . '/../uploads/documents';

    /** Mime whitelist. Text-based PDF supported in MVP; others accepted but flagged for future OCR/DOC handling. */
    private const ALLOWED_MIME = [
        'application/pdf'                                                              => 'pdf',
        'application/msword'                                                           => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'      => 'docx',
        'image/jpeg'                                                                   => 'jpg',
        'image/png'                                                                    => 'png',
        'image/tiff'                                                                   => 'tif',
    ];

    private const MAX_BYTES = 40 * 1024 * 1024; // 40 MB per file

    /**
     * Validate + store one uploaded file. Returns ['ok' => bool, 'stored_path' => ..., 'mime' => ..., 'error' => ...].
     */
    public static function store(int $proposalId, array $fileArr, int $docId): array {
        if (!is_array($fileArr) || !isset($fileArr['tmp_name']) || !is_uploaded_file($fileArr['tmp_name'])) {
            return ['ok' => false, 'error' => 'No valid upload received.'];
        }
        if ($fileArr['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File exceeds ' . round(self::MAX_BYTES / (1024 * 1024)) . ' MB limit.'];
        }

        $mime = self::detectMime($fileArr['tmp_name'], $fileArr['name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported file type: ' . $mime];
        }

        $dir = self::UPLOAD_ROOT . '/' . $proposalId;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['ok' => false, 'error' => 'Could not create storage directory.'];
            }
        }

        $safeName = self::sanitizeFilename($fileArr['name']);
        $finalName = $docId . '_' . $safeName;
        $absPath = $dir . '/' . $finalName;

        if (!move_uploaded_file($fileArr['tmp_name'], $absPath)) {
            return ['ok' => false, 'error' => 'move_uploaded_file failed.'];
        }

        return [
            'ok'          => true,
            'stored_path' => $proposalId . '/' . $finalName,
            'abs_path'    => $absPath,
            'mime'        => $mime,
            'size'        => $fileArr['size'],
        ];
    }

    public static function absolutePath(string $storedPath): string {
        return self::UPLOAD_ROOT . '/' . $storedPath;
    }

    private static function sanitizeFilename(string $name): string {
        $name = pathinfo($name, PATHINFO_BASENAME);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        $name = trim($name, '._-');
        if ($name === '') $name = 'upload';
        // Cap at 96 chars including extension
        if (strlen($name) > 96) $name = substr($name, -96);
        return $name;
    }

    private static function detectMime(string $tmpPath, string $originalName): string {
        // Prefer finfo when available
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $f ? finfo_file($f, $tmpPath) : '';
            if ($f) finfo_close($f);
            if ($mime) return $mime;
        }
        // Fall back to extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $map = ['pdf' => 'application/pdf', 'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'tif' => 'image/tiff', 'tiff' => 'image/tiff'];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
