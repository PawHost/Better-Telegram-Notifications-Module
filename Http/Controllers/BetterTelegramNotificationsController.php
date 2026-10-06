<?php

namespace Modules\BetterTelegramNotifications\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BetterTelegramNotifications\Services\TelegramNotifier as Notifier;

class BetterTelegramNotificationsController extends Controller
{
    /**
     * Add a recipient, or update it if the same user/group + chat ID already exists
     * (same behaviour as the WHMCS module).
     */
    public function saveRecipient(Request $request)
    {
        $type = $request->input('type') == Notifier::TYPE_GROUP ? Notifier::TYPE_GROUP : Notifier::TYPE_USER;
        $chat_id = trim((string) $request->input('chat_id'));
        $user_id = (int) $request->input('user_id');
        $name = trim((string) $request->input('name'));
        $triggers = array_values(array_intersect((array) $request->input('triggers', []), array_keys(Notifier::triggers())));
        $mailboxes = array_values(array_map('intval', (array) $request->input('mailboxes', [])));

        $error = null;
        if (!preg_match('/^(-?\d+|@[A-Za-z0-9_]{5,})$/', $chat_id)) {
            $error = __('Please enter a valid Telegram chat ID.');
        } elseif ($type == Notifier::TYPE_USER && !\App\User::find($user_id)) {
            $error = __('Please select a FreeScout user.');
        } elseif ($type == Notifier::TYPE_GROUP && $name === '') {
            $error = __('Please enter a name for the Telegram group.');
        } elseif (!$triggers) {
            $error = __('Please select at least one trigger.');
        }
        if ($error) {
            return $this->back()->with('flash_error_floating', $error)->withInput();
        }

        $recipient = [
            'type'      => $type,
            'user_id'   => $type == Notifier::TYPE_USER ? $user_id : 0,
            'name'      => $type == Notifier::TYPE_GROUP ? $name : '',
            'chat_id'   => $chat_id,
            'triggers'  => $triggers,
            'mailboxes' => $mailboxes,
            'skip_own'  => $type == Notifier::TYPE_USER && $request->input('skip_own'),
        ];

        $recipients = Notifier::getRecipients();
        $updated = false;
        foreach ($recipients as $i => $existing) {
            if ($existing['type'] == $type && $existing['chat_id'] === $chat_id
                && ($type == Notifier::TYPE_GROUP || $existing['user_id'] == $user_id)
            ) {
                $recipients[$i] = array_merge($existing, $recipient);
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $recipient['id'] = bin2hex(random_bytes(8));
            $recipients[] = $recipient;
        }
        Notifier::saveRecipients($recipients);

        return $this->back()->with('flash_success_floating', $updated ? __('Assignment updated.') : __('Assignment saved.'));
    }

    public function deleteRecipient(Request $request)
    {
        $id = (string) $request->input('id');
        $recipients = array_filter(Notifier::getRecipients(), function ($recipient) use ($id) {
            return $recipient['id'] !== $id;
        });
        Notifier::saveRecipients($recipients);

        return $this->back()->with('flash_success_floating', __('Assignment removed.'));
    }

    /**
     * Send a test message synchronously so the admin sees the API error right away.
     */
    public function test(Request $request)
    {
        $recipient = Notifier::findRecipient((string) $request->input('id'));
        if (!$recipient) {
            return $this->back()->with('flash_error_floating', __('Assignment not found.'));
        }

        $text = '<b>'.Notifier::escape(__('Better Telegram Notifications')).'</b>'."\n\n"
            .Notifier::escape(__('✅ Connection to Telegram successful.'));

        $error = Notifier::send($recipient['chat_id'], $text);
        if ($error) {
            return $this->back()->with('flash_error_floating', __('Telegram API error').': '.$error);
        }

        return $this->back()->with('flash_success_floating', __('Test message sent to :name.', ['name' => Notifier::recipientName($recipient)]));
    }

    /**
     * Import bot token and mailbox -> chat mapping from the original
     * FreeScout "Telegram Notifications" module (alias "telegram").
     */
    public function import()
    {
        $result = Notifier::importFromOriginalModule();
        if ($result['error']) {
            return $this->back()->with('flash_error_floating', $result['error']);
        }

        return $this->back()->with('flash_success_floating', __('Imported from the Telegram module: :token, :count assignment(s).', [
            'token' => $result['token'] ? __('bot token') : __('no bot token'),
            'count' => $result['count'],
        ]));
    }

    protected function back()
    {
        return redirect()->route('settings', ['section' => 'bettertelegram']);
    }
}
