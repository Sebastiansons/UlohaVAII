<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['message' => 'Neplatn� JSON form�t.']);
        http_response_code(400);
        exit;
    }

    $errors = [];
    if (empty($data['name']) || strlen($data['name']) > 60) {
        $errors[] = 'Meno je povinn� a mus� ma� maxim�lne 60 znakov.';
    }
    if (empty($data['email']) || strlen($data['email']) > 100 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email je povinn�, mus� by� platn� a ma� maxim�lne 100 znakov.';
    }
    if (empty($data['orderNumber']) || !preg_match('/^\d{1,10}$/', $data['orderNumber'])) {
        $errors[] = '��slo objedn�vky je povinn� a mus� obsahova� iba ��slice a maxim�lne 10 ��slic.';
    }
    if (empty($data['complaint']) || strlen($data['complaint']) > 255) {
        $errors[] = 'Popis reklam�cie je povinn� a mus� ma� maxim�lne 255 znakov.';
    }
    if (empty($data['images']) || !is_array($data['images'])) {
        $errors[] = 'Obr�zky s� povinn�.';
    } else {
        foreach ($data['images'] as $image) {
            if (!preg_match('/^data:image\/(jpeg|png);base64,/', $image)) {
                $errors[] = 'Obr�zky musia by� vo form�te JPG alebo PNG.';
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
    $subject = 'Nov� reklam�cia';
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
    $message .= "��slo objedn�vky: " . htmlspecialchars($data['orderNumber']) . "\n";
    $message .= "Popis reklam�cie: " . htmlspecialchars($data['complaint']) . "\n\n";
    $message .= "Obr�zky s� prilo�en� k tejto spr�ve.\n\n";

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
        echo json_encode(['message' => 'Formul�r bol �spe�ne odoslan�!']);
    } else {
        echo json_encode(['message' => 'Pri odosielan� formul�ra do�lo k chybe.']);
        http_response_code(500);
    }
} else {
    echo json_encode(['message' => 'Neplatn� po�iadavka.']);
    http_response_code(405);
}
?>
