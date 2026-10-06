<?php

namespace Modules\BetterTelegramNotifications\Providers;

use App\Conversation;
use Illuminate\Support\ServiceProvider;
use Modules\BetterTelegramNotifications\Services\TelegramNotifier as Notifier;

class BetterTelegramNotificationsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->hooks();
        $this->listeners();
    }

    /**
     * Ticket events -> Telegram.
     */
    public function listeners()
    {
        \Event::listen(\App\Events\CustomerCreatedConversation::class, function ($event) {
            self::guard(function () use ($event) {
                Notifier::notify(Notifier::TRIGGER_NEW_TICKET, $event->conversation, __('A new support ticket has been opened.'));
            });
        });

        \Event::listen(\App\Events\UserCreatedConversation::class, function ($event) {
            self::guard(function () use ($event) {
                Notifier::notify(Notifier::TRIGGER_NEW_TICKET, $event->conversation, __('A new ticket has been created by a staff member.'), self::actorId($event->last_thread));
            });
        });

        \Event::listen(\App\Events\CustomerReplied::class, function ($event) {
            self::guard(function () use ($event) {
                Notifier::notify(Notifier::TRIGGER_CUSTOMER_REPLY, $event->conversation, __('A new reply has been posted by a customer.'));
            });
        });

        \Event::listen(\App\Events\UserReplied::class, function ($event) {
            self::guard(function () use ($event) {
                Notifier::notify(Notifier::TRIGGER_STAFF_REPLY, $event->conversation, __('A new reply has been posted by a staff member.'), self::actorId($event->thread));
            });
        });

        \Event::listen(\App\Events\UserAddedNote::class, function ($event) {
            self::guard(function () use ($event) {
                Notifier::notify(Notifier::TRIGGER_NOTE, $event->conversation, __('A new note has been added by a staff member.'), self::actorId($event->thread));
            });
        });

        // The Eventy hooks carry the acting user and the previous value, the plain events don't.
        \Eventy::addAction('conversation.status_changed', function ($conversation, $user, $changed_on_reply, $prev_status) {
            self::guard(function () use ($conversation, $user, $prev_status) {
                if ($prev_status == $conversation->status) {
                    return;
                }
                if ($conversation->status == Conversation::STATUS_CLOSED) {
                    Notifier::notify(Notifier::TRIGGER_CLOSED, $conversation, __('A support ticket has been closed.'), $user->id ?? null);
                } else {
                    Notifier::notify(Notifier::TRIGGER_STATUS, $conversation, __('The ticket status has been changed to :status.', ['status' => $conversation->getStatusName()]), $user->id ?? null);
                }
            });
        }, 20, 4);

        \Eventy::addAction('conversation.user_changed', function ($conversation, $user, $prev_user_id) {
            self::guard(function () use ($conversation, $user, $prev_user_id) {
                if ($prev_user_id == $conversation->user_id) {
                    return;
                }
                $assignee = $conversation->user_id ? \App\User::find($conversation->user_id) : null;
                if ($assignee) {
                    $message = __('The ticket has been assigned to :name.', ['name' => $assignee->getFullName()]);
                } else {
                    $message = __('The ticket has been unassigned.');
                }
                Notifier::notify(Notifier::TRIGGER_ASSIGNED, $conversation, $message, $user->id ?? null);
            });
        }, 20, 3);

        // Executed by the queue worker (see Notifier::notify()).
        \Eventy::addAction(Notifier::SEND_ACTION, function ($chat_id, $text) {
            $error = Notifier::send($chat_id, $text);
            if ($error) {
                \Log::error('[BetterTelegramNotifications] Could not send message to chat '.$chat_id.': '.$error);
            }
        }, 20, 2);
    }

    /**
     * A notification problem must never break saving a ticket in FreeScout.
     */
    protected static function guard(callable $callback)
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            \Helper::logException($e, '[BetterTelegramNotifications]');
        }
    }

    protected static function actorId($thread)
    {
        return $thread->created_by_user_id ?? null;
    }

    /**
     * Settings page.
     */
    public function hooks()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections['bettertelegram'] = [
                'title' => __('Telegram'),
                'icon'  => 'send',
                'order' => 660,
            ];

            return $sections;
        }, 20, 1);

        // Only the bot token goes through the core settings form. Recipients are
        // saved by our own controller: the core removes every listed option that
        // is missing from the submitted form.
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section != 'bettertelegram') {
                return $settings;
            }

            return [
                Notifier::OPTION_BOT_TOKEN => Notifier::getBotToken(),
            ];
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section != 'bettertelegram') {
                return $params;
            }

            $params['settings'] = [
                Notifier::OPTION_BOT_TOKEN => [
                    'safe_password' => true,
                    'encrypt'       => true,
                ],
            ];

            $params['template_vars'] = [
                'recipients' => Notifier::getRecipients(),
                'triggers'   => Notifier::triggers(),
                'users'      => \App\User::where('status', \App\User::STATUS_ACTIVE)->orderBy('first_name')->get(),
                'mailboxes'  => \App\Mailbox::orderBy('name')->get(),
            ];

            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            if ($section == 'bettertelegram') {
                return 'bettertelegram::settings';
            }

            return $view;
        }, 20, 2);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php',
            'bettertelegram'
        );
    }

    /**
     * Register views.
     */
    public function registerViews()
    {
        $source_path = __DIR__.'/../Resources/views';

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/bettertelegram';
        }, \Config::get('view.paths')), [$source_path]), 'bettertelegram');
    }

    /**
     * Register translations.
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../Resources/lang');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
