<?php
declare(strict_types=1);

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '9257776748:AAF02sz0VA-PeZEsuMY0I9kcwuVCRJmRXls');
define('OWNER_ID', (int) (getenv('OWNER_ID') ?: '5013016506'));
define('API', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

function tg(string $method, array $params = []): array
{
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    }
    $ch = curl_init(API . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $res, true) ?: [];
}

function handleMessage(array $m): void
{
    $chatId  = $m['chat']['id'];
    $from    = $m['from'];
    $text    = $m['text'] ?? $m['caption'] ?? '(پیام غیرمتنی)';
    $isStart = str_starts_with($text, '/start');

    // ---- پیام از طرف صاحب ربات: جواب دادن به کاربر ----
    if ($chatId === OWNER_ID) {
        $ref = $m['reply_to_message']['text']
            ?? $m['reply_to_message']['caption']
            ?? '';
        if (!$isStart && preg_match('/\[ID:(\d+)\]/', $ref, $mm)) {
            tg('sendMessage', [
                'chat_id' => $mm[1],
                'text'    => "☎ پاسخ از Alir3za:\n\n$text",
            ]);
            tg('sendMessage', [
                'chat_id' => OWNER_ID,
                'text'    => "✔ پاسخ ارسال شد.",
            ]);
        }
        return;
    }

    // ---- کاربر عادی ----
    if ($isStart) {
        tg('sendMessage', [
            'chat_id' => $chatId,
            'text'    => "☺ سلام! تو در حال ارسال پیام ناشناس هستی به Alir3za.\nپیامت رو بنویس و بفرست.",
        ]);
        return;
    }

    $uid      = $from['id'];
    $name     = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: '-';
    $username = isset($from['username']) ? '@' . $from['username'] : '-';

    // بیو کاربر (ربات باید قبلاً توسط کاربر استارت شده باشد)
    $bio = tg('getChat', ['chat_id' => $uid])['result']['bio'] ?? '-';

    // عکس پروفایل
    $photoId = null;
    $photos  = tg('getUserProfilePhotos', ['user_id' => $uid, 'limit' => 1])['result'] ?? [];
    if (!empty($photos['photos'][0])) {
        $sizes   = $photos['photos'][0];
        $photoId = end($sizes)['file_id']; // بزرگ‌ترین سایز
    }

    // [ID:...] در ابتدای متن می‌ماند تا وقتی reply می‌زنی، ID پیدا شود
    $body = "[ID:$uid]\n"
        . "✉ پیام ناشناس جدید\n\n"
        . "⚲ ID: $uid\n"
        . "☻ نام: $name\n"
        . "@ یوزرنیم: $username\n"
        . "✎ بیو: $bio\n\n"
        . "☰ متن:\n$text";

    $markup = [
        'inline_keyboard' => [[
            ['text' => '✔ جواب دادن', 'callback_data' => "reply:$uid", 'style' => 'success'],
            ['text' => '☹ رد کردن',   'callback_data' => "reject:$uid", 'style' => 'danger'],
        ]],
    ];

    if ($photoId) {
        tg('sendPhoto', [
            'chat_id'      => OWNER_ID,
            'photo'        => $photoId,
            'caption'      => mb_substr($body, 0, 1024),
            'reply_markup' => $markup,
        ]);
    } else {
        tg('sendMessage', [
            'chat_id'      => OWNER_ID,
            'text'         => mb_substr($body, 0, 4096),
            'reply_markup' => $markup,
        ]);
    }

    tg('sendMessage', [
        'chat_id' => $chatId,
        'text'    => "✔ پیامت ارسال شد.",
    ]);
}

function handleCallback(array $cb): void
{
    if ($cb['from']['id'] !== OWNER_ID) {
        tg('answerCallbackQuery', [
            'callback_query_id' => $cb['id'],
            'text'              => 'دسترسی ندارید',
        ]);
        return;
    }

    [$action, $uid] = explode(':', $cb['data'], 2) + [null, null];
    $ownerChat = $cb['message']['chat']['id'];
    $msgId     = $cb['message']['message_id'];

    if ($action === 'reply') {
        tg('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
        tg('sendMessage', [
            'chat_id'      => $ownerChat,
            'text'         => "✎ جوابت رو روی همین پیام Reply کن\n[ID:$uid]",
            'reply_markup' => [
                'force_reply'             => true,
                'input_field_placeholder' => 'جواب...',
            ],
        ]);
    } elseif ($action === 'reject') {
        tg('sendMessage', [
            'chat_id' => $uid,
            'text'    => "☹ پیامت توسط Alir3za رد شد.",
        ]);
        tg('editMessageReplyMarkup', [
            'chat_id'      => $ownerChat,
            'message_id'   => $msgId,
            'reply_markup' => ['inline_keyboard' => []],
        ]);
        tg('answerCallbackQuery', [
            'callback_query_id' => $cb['id'],
            'text'              => 'رد شد',
        ]);
    }
}

// ---- ورودی وبهوک ----
$update = json_decode(file_get_contents('php://input') ?: '', true);

if ($update) {
    if (isset($update['callback_query'])) {
        handleCallback($update['callback_query']);
    } elseif (isset($update['message'])) {
        handleMessage($update['message']);
    }
}

echo 'ok';
