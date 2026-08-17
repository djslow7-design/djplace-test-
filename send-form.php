<?php
// send-form.php
// Принимает заявку с формы, шлёт в Telegram, при сбое дублирует на почту.
// Токен бота и адрес почты хранятся только здесь, наружу не отдаются.

header('Content-Type: application/json; charset=utf-8');

// ==== настройки ====
$botToken   = '8750226765:AAEcVWgi8MM_Mk-t5JpCSQoO-cahhwoVLaI';
$chatId     = '321163553';
$mailTo     = 'djslow7@gmail.com';
$mailFrom   = 'noreply@dj-place.ru'; // лучше свой почтовый ящик на этом же домене

// ==== принимаем данные ====
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

$name   = isset($data['name'])   ? trim(strip_tags($data['name']))   : '';
$phone  = isset($data['phone'])  ? trim(strip_tags($data['phone']))  : '';
$course = isset($data['course']) ? trim(strip_tags($data['course'])) : 'Пробное';

if ($phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'phone_required']);
    exit;
}

$time = date('d.m.Y H:i');
$text = "🎧 Новая заявка, DJ Place\n\n"
      . "Имя: " . ($name !== '' ? $name : 'не указано') . "\n"
      . "Контакт: " . $phone . "\n"
      . "Курс: " . $course . "\n"
      . "Время: " . $time;

// ==== пробуем Telegram ====
$telegramOk = false;
$tgUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
$ch = curl_init($tgUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'chat_id' => $chatId,
        'text'    => $text,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $httpCode === 200) {
    $telegramOk = true;
}

// ==== если телеграм не сработал, дублируем на почту ====
$mailOk = false;
if (!$telegramOk) {
    $subject = 'DJ Place: новая заявка (резерв, Telegram недоступен)';
    $body = "Имя: " . ($name !== '' ? $name : 'не указано') . "\n"
          . "Контакт: " . $phone . "\n"
          . "Курс: " . $course . "\n"
          . "Время: " . $time;
    $headers = "From: {$mailFrom}\r\nContent-Type: text/plain; charset=utf-8";
    $mailOk = @mail($mailTo, $subject, $body, $headers);
}

echo json_encode([
    'ok'          => $telegramOk || $mailOk,
    'via'         => $telegramOk ? 'telegram' : ($mailOk ? 'mail' : 'none'),
]);
