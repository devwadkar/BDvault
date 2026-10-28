<?php
/**
 * AI Configuration — reads credentials from environment variables.
 *
 * NEVER hard-code keys in this file.
 *
 * Set these in production via your host's env mechanism (see .env.example),
 * or drop a project-root `.env` file for local testing — the env_loader
 * included below picks it up without affecting real OS env vars.
 *
 *   GEMINI_API_KEY   — Google Gemini (required for AI assistants)
 *
 * Get a Gemini key: https://aistudio.google.com/apikey
 */

require_once __DIR__ . '/../includes/env_loader.php';

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'gemini-2.5-flash');
