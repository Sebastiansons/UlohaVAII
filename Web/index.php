<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['message' => 'Neplatný JSON formát.']);
        http_response_code(400);
        exit;
    }

    $errors = [];
    if (empty($data['name']) || strlen($data['name']) > 60) {
        $errors[] = 'Meno je povinné a musí ma maximálne 60 znakov.';
    }
    if (empty($data['email']) || strlen($data['email']) > 100 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email je povinný, musí by platný a ma maximálne 100 znakov.';
    }
    if (empty($data['orderNumber']) || !preg_match('/^\d{1,10}$/', $data['orderNumber'])) {
        $errors[] = 'Èíslo objednávky je povinné a musí obsahova iba èíslice a maximálne 10 èíslic.';
    }
    if (empty($data['complaint']) || strlen($data['complaint']) > 255) {
        $errors[] = 'Popis reklamácie je povinný a musí ma maximálne 255 znakov.';
    }
    if (empty($data['images']) || !is_array($data['images'])) {
        $errors[] = 'Obrázky sú povinné.';
    } else {
        foreach ($data['images'] as $image) {
            if (!preg_match('/^data:image\/(jpeg|png);base64,/', $image)) {
                $errors[] = 'Obrázky musia by vo formáte JPG alebo PNG.';
                break;
            }
        }
    }

    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        http_response_code(400);
        exit;
    }

    $to = 'sepkosidor@gmail.com';
    $subject = 'Nová reklamácia';
    $boundary = md5(uniqid(time()));

    $headers = 'From: ' . htmlspecialchars($data['email']) . "\r\n" .
               'Reply-To: ' . htmlspecialchars($data['email']) . "\r\n" .
               'X-Mailer: PHP/' . phpversion() . "\r\n" .
               'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= "Meno: " . htmlspecialchars($data['name']) . "\n";
    $message .= "Email: " . htmlspecialchars($data['email']) . "\n";
    $message .= "Èíslo objednávky: " . htmlspecialchars($data['orderNumber']) . "\n";
    $message .= "Popis reklamácie: " . htmlspecialchars($data['complaint']) . "\n\n";
    $message .= "Obrázky sú priložené k tejto správe.\n\n";

    foreach ($data['images'] as $index => $image) {
        $imageData = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,'], '', $image);
        $imageData = base64_decode($imageData);
        $imageType = strpos($image, 'image/png') !== false ? 'png' : 'jpeg';
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: image/{$imageType}; name=\"image{$index}.{$imageType}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"image{$index}.{$imageType}\"\r\n\r\n";
        $message .= chunk_split(base64_encode($imageData)) . "\r\n";
    }

    $message .= "--{$boundary}--";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['message' => 'Formulár bol úspešne odoslaný!']);
    } else {
        echo json_encode(['message' => 'Pri odosielaní formulára došlo k chybe.']);
        http_response_code(500);
    }
} else {
    echo json_encode(['message' => 'Neplatná požiadavka.']);
    http_response_code(405);
}
?>
