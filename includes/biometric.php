<?php
// Saves a base64 data-URL image (from webcam capture) to assets/uploads/biometrics/.
// Returns the relative path to store in the DB, or null on failure.
function saveBiometricPhoto(string $dataUrl, int $userId, string $date): ?string
{
    if (!preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/', $dataUrl, $matches)) {
        return null;
    }

    $ext = $matches[1] === 'png' ? 'png' : 'jpg';
    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        return null;
    }

    $filename = $userId . '_' . $date . '_' . time() . '.' . $ext;
    $relativePath = 'assets/uploads/biometrics/' . $filename;
    $fullPath = __DIR__ . '/../' . $relativePath;

    if (file_put_contents($fullPath, $binary) === false) {
        return null;
    }

    return $relativePath;
}
