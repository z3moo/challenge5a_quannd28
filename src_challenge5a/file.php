<?php
require_once 'includes/config.php';
requireLogin();

$path = $_GET['path'] ?? '';

$path = str_replace(["\0", '\\'], ['', '/'], $path);
$path = preg_replace('/\.\.+/', '', $path);
$path = ltrim($path, '/');

$realUpload = realpath(UPLOAD_DIR);
$absPath    = UPLOAD_DIR . $path;
$realFile   = realpath($absPath);

if (!$realFile || !$realUpload || !is_file($realFile)) {
    http_response_code(404);
    exit('File not found.');
}
if (strncmp($realFile, $realUpload, strlen($realUpload)) !== 0) {
    http_response_code(403);
    exit('Forbidden.');
}

if (preg_match('/\.(php\d?|phtml|phar|shtml|cgi|pl|py|sh|exe|bat|cmd)$/i', $realFile)) {
    http_response_code(403);
    exit('Forbidden.');
}

if (strncmp($path, 'challenges/', 11) === 0) {
    http_response_code(403);
    exit('Forbidden.');
}

$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mime     = finfo_file($finfo, $realFile) ?: 'application/octet-stream';
finfo_close($finfo);

$filename    = basename($realFile);
$disposition = (strncmp($mime, 'image/', 6) === 0) ? 'inline' : 'attachment';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($realFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($realFile);
exit;
