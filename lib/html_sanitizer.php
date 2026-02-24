<?php
// ec/lib/html_sanitizer.php
// Helper wrapper to sanitize HTML for saving clinical notes.
// Uses HTMLPurifier if installed (recommended). Falls back to a safe whitelist sanitizer if not.
//
// Usage:
//   require_once __DIR__ . '/lib/html_sanitizer.php';
//   $clean = sanitize_html($dirty_html);

function sanitize_html($html) {
    $html = (string)$html;

    // Quick empty check
    if (trim($html) === '') return '';

    // Try HTMLPurifier if available
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            if (class_exists('HTMLPurifier')) {
                // Basic recommended config: allow a conservative set of tags and attributes
                $config = HTMLPurifier_Config::createDefault();
                // Allow some formatting tags commonly used in notes
                $config->set('HTML.AllowedElements', [
                    'a','b','strong','i','em','u','p','br','ul','ol','li','h3','h4','pre','code','span','div'
                ]);
                // Allow basic attributes for anchors and spans
                $config->set('HTML.AllowedAttributes', [
                    'a.href', 'a.title', 'a.target', 'span.class', 'div.class'
                ]);
                // Allow safe URI schemes (http, https, mailto)
                $config->set('URI.AllowedSchemes', [
                    'http' => true, 'https' => true, 'mailto' => true
                ]);
                // Remove empty elements and comments
                $config->set('Core.RemoveEmpty', true);
                $purifier = new HTMLPurifier($config);
                return $purifier->purify($html);
            }
        } catch (Exception $e) {
            // Fall through to fallback sanitizer
            error_log('HTMLPurifier initialization error: ' . $e->getMessage());
        }
    }

    // Fallback sanitizer (whitelist + attribute strip)
    return sanitize_html_fallback($html);
}

/**
 * Fallback HTML sanitizer when HTMLPurifier isn't installed.
 * - Allows a small set of tags
 * - Removes all attributes except href on <a>
 * - Removes script/style tags and their contents
 */
function sanitize_html_fallback($html) {
    // Remove script/style tags and contents
    $html = preg_replace('#<script.*?>.*?</script>#is', '', $html);
    $html = preg_replace('#<style.*?>.*?</style>#is', '', $html);

    // Allowed tags (same as above)
    $allowed_tags = ['a','b','strong','i','em','u','p','br','ul','ol','li','h3','h4','pre','code','span','div'];
    // Build regex to remove not allowed tags but keep inner text
    // First, strip disallowed tags but keep content
    $html = strip_tags($html, '<' . implode('><', $allowed_tags) . '>');

    // Remove attributes except href on anchors
    // Use DOMDocument to normalize attributes safely (suppresses warnings on malformed HTML)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    // Force proper encoding handling
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        // As a final fallback, strip tags only
        return strip_tags($html, '<' . implode('><', $allowed_tags) . '>');
    }

    $xpath = new DOMXPath($dom);
    // Remove all attributes except href on <a>
    foreach ($xpath->query('//*') as $node) {
        if ($node->hasAttributes()) {
            $attrs = [];
            foreach ($node->attributes as $attr) $attrs[] = $attr->name;
            foreach ($attrs as $attrName) {
                if ($node->nodeName === 'a' && $attrName === 'href') {
                    // validate href scheme
                    $href = $node->getAttribute('href');
                    if (!preg_match('#^(https?:|mailto:)#i', $href)) {
                        $node->removeAttribute('href');
                    }
                    continue;
                }
                // remove all other attributes
                $node->removeAttribute($attrName);
            }
        }
    }

    // Return innerHTML of body
    $body = $dom->getElementsByTagName('body')->item(0);
    $result = '';
    if ($body) {
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
    } else {
        $result = $dom->saveHTML();
    }

    libxml_clear_errors();
    return $result;
}

/**
 * Simple text sanitizer (for plain-text fields).
 * - Trims and encodes special HTML chars.
 */
function sanitize_text($text) {
    return trim(htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}