<?php

namespace Modules\BetterTelegramNotifications\Services;

use App\User;

class TelegramNotifier
{
    const OPTION_BOT_TOKEN = 'bettertelegram.bot_token';
    const OPTION_RECIPIENTS = 'bettertelegram.recipients';

    const TYPE_USER = 'user';
    const TYPE_GROUP = 'group';

    const TRIGGER_NEW_TICKET = 'new_ticket';
    const TRIGGER_CUSTOMER_REPLY = 'customer_reply';
    const TRIGGER_STAFF_REPLY = 'staff_reply';
    const TRIGGER_NOTE = 'note';
    const TRIGGER_ASSIGNED = 'assigned';
    const TRIGGER_CLOSED = 'closed';
    const TRIGGER_STATUS = 'status';

    /**
     * Background action that performs the actual API call (runs in the queue worker).
     */
    const SEND_ACTION = 'bettertelegram.send';

    /**
     * Available triggers (the FreeScout counterpart of the WHMCS "trigger groups").
     */
    public static function triggers()
    {
        return [
            self::TRIGGER_NEW_TICKET     => __('New ticket'),
            self::TRIGGER_CUSTOMER_REPLY => __('Customer reply'),
            self::TRIGGER_STAFF_REPLY    => __('Staff reply'),
            self::TRIGGER_NOTE           => __('Note added'),
            self::TRIGGER_ASSIGNED       => __('Assignee changed'),
            self::TRIGGER_CLOSED         => __('Ticket closed'),
            self::TRIGGER_STATUS         => __('Other status change'),
        ];
    }

    public static function getBotToken()
    {
        $token = (string) \Option::get(self::OPTION_BOT_TOKEN, '');
        if ($token === '') {
            return '';
        }

        try {
            return (string) decrypt($token);
        } catch (\Exception $e) {
            // Stored unencrypted (e.g. set manually).
            return $token;
        }
    }

    /**
     * Event codes of the original Telegram module -> our triggers.
     */
    const ORIGINAL_EVENTS = [
        'conversation.created'          => [self::TRIGGER_NEW_TICKET],
        'conversation.assigned'         => [self::TRIGGER_ASSIGNED],
        'conversation.note_added'       => [self::TRIGGER_NOTE],
        'conversation.customer_replied' => [self::TRIGGER_CUSTOMER_REPLY],
        'conversation.user_replied'     => [self::TRIGGER_STAFF_REPLY],
        'conversation.status_changed'   => [self::TRIGGER_CLOSED, self::TRIGGER_STATUS],
    ];

    /**
     * Bot token of the original Telegram module. It keeps the token (encrypted)
     * in .env as TELEGRAM_BOT_TOKEN, exposed as config telegram.bots.main.token
     * only while that module is active.
     */
    public static function getOriginalBotToken()
    {
        $token = config('telegram.bots.main.token') ?: env('TELEGRAM_BOT_TOKEN');

        if (!$token) {
            $env_file = base_path('.env');
            if (is_readable($env_file)
                && preg_match('/^\s*TELEGRAM_BOT_TOKEN\s*=\s*(.*?)\s*$/m', file_get_contents($env_file), $m)
            ) {
                $token = trim($m[1], '"\'');
            }
        }

        return $token ? (string) \Helper::decryptSoft($token) : '';
    }

    /**
     * Copy the bot token (unless one is set already) and the mailbox -> chat
     * mapping of the original module. Each chat becomes one group assignment
     * covering all mailboxes mapped to it. Existing assignments with the same
     * chat ID are extended, not replaced. Safe to run more than once.
     *
     * @return array ['error' => string|null, 'token' => bool, 'count' => int]
     */
    public static function importFromOriginalModule()
    {
        $result = ['error' => null, 'token' => false, 'count' => 0];

        $old_token = self::getOriginalBotToken();
        $mapping = \Option::get('telegram.channels_mapping', []);
        $mapping = is_array($mapping) ? array_filter($mapping, function ($chat_id) {
            return trim((string) $chat_id) !== '';
        }) : [];

        if ($old_token === '' && !$mapping) {
            $result['error'] = __('No settings of the Telegram module found.');

            return $result;
        }

        if ($old_token !== '' && self::getBotToken() === '') {
            \Option::set(self::OPTION_BOT_TOKEN, encrypt($old_token));
            $result['token'] = true;
        }

        $triggers = [];
        foreach ((array) \Option::get('telegram.events', []) as $event) {
            $triggers = array_merge($triggers, self::ORIGINAL_EVENTS[$event] ?? []);
        }
        if (!$triggers) {
            $triggers = array_keys(self::triggers());
        }
        $triggers = array_values(array_unique($triggers));

        // chat ID => mailbox IDs
        $chats = [];
        foreach ($mapping as $mailbox_id => $chat_id) {
            $chats[trim((string) $chat_id)][] = (int) $mailbox_id;
        }

        $titles = \Option::get('telegram_channels', []);
        $recipients = self::getRecipients();

        foreach ($chats as $chat_id => $mailboxes) {
            $found = false;
            foreach ($recipients as $i => $existing) {
                if ($existing['type'] == self::TYPE_GROUP && (string) $existing['chat_id'] === (string) $chat_id) {
                    $recipients[$i]['triggers'] = array_values(array_unique(array_merge($existing['triggers'] ?? [], $triggers)));
                    // Empty = all mailboxes already.
                    if (!empty($existing['mailboxes'])) {
                        $recipients[$i]['mailboxes'] = array_values(array_unique(array_merge($existing['mailboxes'], $mailboxes)));
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $recipients[] = [
                    'id'        => bin2hex(random_bytes(8)),
                    'type'      => self::TYPE_GROUP,
                    'user_id'   => 0,
                    'name'      => (string) ($titles[$chat_id] ?? 'Telegram '.$chat_id),
                    'chat_id'   => (string) $chat_id,
                    'triggers'  => $triggers,
                    'mailboxes' => $mailboxes,
                    'skip_own'  => false,
                ];
            }
            $result['count']++;
        }

        self::saveRecipients($recipients);

        return $result;
    }

    public static function getRecipients()
    {
        $recipients = \Option::get(self::OPTION_RECIPIENTS, []);

        return is_array($recipients) ? array_values($recipients) : [];
    }

    public static function saveRecipients(array $recipients)
    {
        \Option::set(self::OPTION_RECIPIENTS, array_values($recipients));
    }

    public static function findRecipient($id)
    {
        foreach (self::getRecipients() as $recipient) {
            if ($recipient['id'] === $id) {
                return $recipient;
            }
        }

        return null;
    }

    /**
     * Display name of a recipient (FreeScout user name or Telegram group name).
     */
    public static function recipientName(array $recipient)
    {
        if ($recipient['type'] == self::TYPE_USER) {
            $user = User::find($recipient['user_id']);

            return $user ? $user->getFullName() : __('Unknown user');
        }

        return $recipient['name'] ?: __('Unknown Telegram group');
    }

    /**
     * Queue a notification for every recipient subscribed to the trigger.
     *
     * @param string       $trigger
     * @param Conversation $conversation
     * @param string       $message       Short, data-free description of what happened.
     * @param int|null     $actor_user_id FreeScout user who caused the event (for "skip own actions").
     */
    public static function notify($trigger, $conversation, $message, $actor_user_id = null)
    {
        try {
            if (!$conversation || self::getBotToken() === '') {
                return;
            }

            $text = self::buildText($conversation, $message);
            $sent = [];

            foreach (self::getRecipients() as $recipient) {
                if (!in_array($trigger, $recipient['triggers'] ?? [])) {
                    continue;
                }
                if (!empty($recipient['mailboxes']) && !in_array($conversation->mailbox_id, $recipient['mailboxes'])) {
                    continue;
                }

                if ($recipient['type'] == self::TYPE_USER) {
                    $user = User::find($recipient['user_id']);
                    // Never leak tickets of mailboxes the user cannot see.
                    if (!$user || $user->status != User::STATUS_ACTIVE || !$user->hasAccessToMailbox($conversation->mailbox_id)) {
                        continue;
                    }
                    if (!empty($recipient['skip_own']) && $actor_user_id && $actor_user_id == $user->id) {
                        continue;
                    }
                }

                $chat_id = (string) $recipient['chat_id'];
                if ($chat_id === '' || isset($sent[$chat_id])) {
                    continue;
                }
                $sent[$chat_id] = true;

                \Helper::backgroundAction(self::SEND_ACTION, [$chat_id, $text]);
            }
        } catch (\Exception $e) {
            // A notification problem must never break ticket processing.
            \Helper::logException($e, '[BetterTelegramNotifications]');
        }
    }

    /**
     * Same layout as the WHMCS module: bold title, message, "Open" link.
     * Only number, subject and link are sent — no customer data.
     */
    public static function buildText($conversation, $message)
    {
        $title = '#'.$conversation->number.' - '.$conversation->getSubject();
        $url = route('conversations.view', ['id' => $conversation->id]);

        return '<b>'.self::escape($title).'</b>'."\n\n"
            .self::escape($message)."\n\n"
            .'<a href="'.self::escape($url).'">'.self::escape(__('Telegram: Open')).'</a>';
    }

    public static function escape($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Send a message via the Bot API.
     *
     * @return string|null Error message, or null on success.
     */
    public static function send($chat_id, $text, $bot_token = null)
    {
        $bot_token = $bot_token ?: self::getBotToken();
        if (!$bot_token) {
            return __('Telegram bot token is not set.');
        }

        $ch = curl_init(config('bettertelegram.api_url').$bot_token.'/sendMessage');
        // Defaults first (proxy etc.), our own timeouts below override them.
        \Helper::setCurlDefaultOptions($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id'                  => $chat_id,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => 'true',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) config('bettertelegram.timeout', 10),
            CURLOPT_CONNECTTIMEOUT => (int) config('bettertelegram.connect_timeout', 5),
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return 'cURL: '.$curl_error;
        }
        if ($http_code != 200) {
            $data = json_decode($response, true);

            return 'HTTP '.$http_code.(!empty($data['description']) ? ': '.$data['description'] : '');
        }

        return null;
    }
}
