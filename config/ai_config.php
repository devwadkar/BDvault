<?php
/**
 * AI Configuration — reads credentials from environment variables.
 *
 * NEVER hard-code keys in this file.
 *
 * Set these in production via your host's env mechanism (see .env.example):
 *   GEMINI_API_KEY   — Google Gemini (required for AI assistants)
 *   OPENAI_API_KEY   — optional, only if the OpenAI code path is used
 *
 * Get a Gemini key: https://aistudio.google.com/apikey
 */

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'gemini-2.5-flash');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
