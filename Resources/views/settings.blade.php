@php
    use Modules\BetterTelegramNotifications\Services\TelegramNotifier as Notifier;
    $mailbox_names = $mailboxes->pluck('name', 'id');
@endphp

{{-- Bot --}}
<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <div class="form-group">
        <label for="btn_bot_token" class="col-sm-2 control-label">{{ __('Bot Token') }}</label>

        <div class="col-sm-6">
            <input id="btn_bot_token" type="password" class="form-control input-sized-lg" name="settings[{{ Notifier::OPTION_BOT_TOKEN }}]" value="{{ \Helper::safePassword($settings[Notifier::OPTION_BOT_TOKEN]) }}" maxlength="255" autocomplete="new-password">
            <p class="block-help">
                {!! __('Create a bot with :botfather (/newbot) and paste the token here.', ['botfather' => '<a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a>']) !!}
                {{ __('Only the ticket number, subject and a link are sent — no customer data.') }}
            </p>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>

<h3 class="subheader">{{ __('User & Group Assignments') }}</h3>

<form class="form-horizontal margin-bottom" method="POST" action="{{ route('bettertelegram.recipient.save') }}" id="btn-assign-form">
    {{ csrf_field() }}

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Recipient') }}</label>
        <div class="col-sm-6">
            <label class="radio-inline"><input type="radio" name="type" value="{{ Notifier::TYPE_USER }}" @if (old('type', Notifier::TYPE_USER) == Notifier::TYPE_USER) checked @endif> {{ __('FreeScout user') }}</label>
            <label class="radio-inline"><input type="radio" name="type" value="{{ Notifier::TYPE_GROUP }}" @if (old('type') == Notifier::TYPE_GROUP) checked @endif> {{ __('Telegram group') }}</label>
        </div>
    </div>

    <div class="form-group btn-type-user">
        <label for="btn_user_id" class="col-sm-2 control-label">{{ __('User') }}</label>
        <div class="col-sm-6">
            <select id="btn_user_id" name="user_id" class="form-control input-sized-lg">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @if (old('user_id') == $user->id) selected @endif>{{ $user->getFullName() }} ({{ $user->email }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group btn-type-group">
        <label for="btn_name" class="col-sm-2 control-label">{{ __('Group name') }}</label>
        <div class="col-sm-6">
            <input id="btn_name" type="text" name="name" class="form-control input-sized-lg" value="{{ old('name') }}" maxlength="100" placeholder="{{ __('e.g. Support Team') }}">
        </div>
    </div>

    <div class="form-group">
        <label for="btn_chat_id" class="col-sm-2 control-label">{{ __('Chat ID') }}</label>
        <div class="col-sm-6">
            <input id="btn_chat_id" type="text" name="chat_id" class="form-control input-sized-lg" value="{{ old('chat_id') }}" maxlength="64" required>
            <p class="block-help">{{ __('Users: personal chat ID (send /start to the bot first, e.g. get the ID via @userinfobot). Groups: group chat ID, e.g. -1001234567890 (the bot must be a member).') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Triggers') }}</label>
        <div class="col-sm-6">
            @foreach ($triggers as $key => $label)
                <div class="checkbox"><label><input type="checkbox" name="triggers[]" value="{{ $key }}" @if (in_array($key, old('triggers', []))) checked @endif> {{ $label }}</label></div>
            @endforeach
        </div>
    </div>

    <div class="form-group">
        <label for="btn_mailboxes" class="col-sm-2 control-label">{{ __('Mailboxes') }}</label>
        <div class="col-sm-6">
            <select id="btn_mailboxes" name="mailboxes[]" class="form-control input-sized-lg" multiple size="{{ min(max($mailboxes->count(), 2), 8) }}">
                @foreach ($mailboxes as $mailbox)
                    <option value="{{ $mailbox->id }}" @if (in_array($mailbox->id, old('mailboxes', []))) selected @endif>{{ $mailbox->name }}</option>
                @endforeach
            </select>
            <p class="block-help">{{ __('None selected = all mailboxes. Users only receive tickets of mailboxes they have access to. Ctrl / Cmd + click for multiple selection.') }}</p>
        </div>
    </div>

    <div class="form-group btn-type-user">
        <div class="col-sm-6 col-sm-offset-2">
            <div class="checkbox"><label><input type="checkbox" name="skip_own" value="1" @if (old('skip_own')) checked @endif> {{ __("Don't notify about my own actions") }}</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>

<h3 class="subheader">{{ __('Current Assignments') }}</h3>

@if (count($recipients))
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('User / Group') }}</th>
                    <th>{{ __('Chat ID') }}</th>
                    <th>{{ __('Triggers') }}</th>
                    <th>{{ __('Mailboxes') }}</th>
                    <th>{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recipients as $recipient)
                    <tr>
                        <td>
                            <i class="glyphicon glyphicon-{{ $recipient['type'] == Notifier::TYPE_GROUP ? 'comment' : 'user' }} text-help"></i>
                            {{ Notifier::recipientName($recipient) }}
                            @if (!empty($recipient['skip_own']))<br><small class="text-help">{{ __('without own actions') }}</small>@endif
                        </td>
                        <td>{{ $recipient['chat_id'] }}</td>
                        <td>{{ implode(', ', array_map(function ($t) use ($triggers) { return $triggers[$t] ?? $t; }, $recipient['triggers'] ?? [])) }}</td>
                        <td>
                            @if (empty($recipient['mailboxes']))
                                {{ __('All') }}
                            @else
                                {{ implode(', ', array_map(function ($id) use ($mailbox_names) { return $mailbox_names[$id] ?? '#'.$id; }, $recipient['mailboxes'])) }}
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="#" class="btn-edit" data-recipient="{{ json_encode($recipient) }}">{{ __('Edit') }}</a>
                            &nbsp;·&nbsp;
                            <form method="POST" action="{{ route('bettertelegram.test') }}" style="display:inline">
                                {{ csrf_field() }}
                                <input type="hidden" name="id" value="{{ $recipient['id'] }}">
                                <button type="submit" class="btn btn-link" style="padding:0;vertical-align:baseline">{{ __('Test') }}</button>
                            </form>
                            &nbsp;·&nbsp;
                            <form method="POST" action="{{ route('bettertelegram.recipient.delete') }}" style="display:inline" onsubmit="return confirm('{{ __('Really delete?') }}');">
                                {{ csrf_field() }}
                                <input type="hidden" name="id" value="{{ $recipient['id'] }}">
                                <button type="submit" class="btn btn-link text-danger" style="padding:0;vertical-align:baseline">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-help">{{ __('No assignments yet.') }}</p>
@endif

{{-- Plain JS: scripts in the page body run before jQuery is loaded. --}}
<script {!! \Helper::cspNonceAttr() !!}>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('btn-assign-form');

    function toggleType() {
        var type = form.querySelector('input[name="type"]:checked').value;
        form.querySelectorAll('.btn-type-user').forEach(function (el) { el.style.display = type === 'user' ? '' : 'none'; });
        form.querySelectorAll('.btn-type-group').forEach(function (el) { el.style.display = type === 'group' ? '' : 'none'; });
    }
    form.querySelectorAll('input[name="type"]').forEach(function (el) { el.addEventListener('change', toggleType); });
    toggleType();

    // "Edit" loads an assignment into the form; saving with the same user/group + chat ID updates it.
    document.querySelectorAll('.btn-edit').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var r = JSON.parse(link.getAttribute('data-recipient'));
            form.querySelector('input[name="type"][value="' + r.type + '"]').checked = true;
            if (r.type === 'user') {
                form.querySelector('[name="user_id"]').value = r.user_id;
            }
            form.querySelector('[name="name"]').value = r.name || '';
            form.querySelector('[name="chat_id"]').value = r.chat_id;
            form.querySelectorAll('input[name="triggers[]"]').forEach(function (el) {
                el.checked = (r.triggers || []).indexOf(el.value) !== -1;
            });
            Array.prototype.forEach.call(form.querySelector('[name="mailboxes[]"]').options, function (opt) {
                opt.selected = (r.mailboxes || []).indexOf(parseInt(opt.value, 10)) !== -1;
            });
            form.querySelector('[name="skip_own"]').checked = !!r.skip_own;
            toggleType();
            form.scrollIntoView({behavior: 'smooth'});
        });
    });
});
</script>
