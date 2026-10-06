# Better Telegram Notifications for FreeScout

![Telegram Logo](https://telegram.org/img/t_logo.png)

Get a Telegram message whenever a FreeScout ticket is created or updated. Built in the style of our [WHMCS Telegram Notification](https://github.com/PawHost/WHMCS_Telegram_Notification) module. Notifications can go to individual FreeScout users or to Telegram group chats, and each one can be filtered by trigger and by mailbox.

## 📦 Features

- 🔔 Telegram messages for new tickets, replies, notes, assignments and status changes
- 👥 Send to **FreeScout users** (personal chat ID) and/or **Telegram groups**
- 🧠 Pick the **triggers** each recipient gets
- 📬 Optional **mailbox filter** per recipient
- 🔐 Users only get tickets from mailboxes they can access in FreeScout
- 🙈 Optional *"Don't notify about my own actions"* per user
- ✅ **Test** button for each recipient that shows the Telegram API error right away
- ⚡ Messages are sent by FreeScout's queue worker, so the UI never waits on Telegram
- 🌍 English & German

---

## 💬 Message format

```
#1234 - Server not reachable

A new reply has been posted by a customer.

Open
```

The first line is bold and **Open** links to the ticket. Only the **ticket number, subject and link** are sent. No customer names, email addresses or message bodies.

---

## 🔔 Triggers

| Trigger | Fires when | Message |
|---------|------------|---------|
| New ticket | A customer opens a ticket, or a staff member creates one | *A new support ticket has been opened.* / *A new ticket has been created by a staff member.* |
| Customer reply | A customer replies | *A new reply has been posted by a customer.* |
| Staff reply | A staff member replies | *A new reply has been posted by a staff member.* |
| Note added | A staff member adds a note | *A new note has been added by a staff member.* |
| Assignee changed | A ticket is assigned or unassigned | *The ticket has been assigned to {name}.* |
| Ticket closed | Status is changed to *Closed* | *A support ticket has been closed.* |
| Other status change | Status is changed to Active, Pending or Spam | *The ticket status has been changed to {status}.* |

---

## 🚀 Installation

1. **Upload the module.** Put the repository contents in `Modules/BetterTelegramNotifications/` of your FreeScout installation:
   ```bash
   cd /path/to/freescout/Modules
   git clone https://github.com/PawHost/Better-Telegram-Notifications-Module.git BetterTelegramNotifications
   ```
   The folder **must** be named `BetterTelegramNotifications`.

2. **Activate it** under *Manage → Modules → Better Telegram Notifications → Activate*.

3. **Enter the bot token** under *Manage → Settings → Telegram*.

4. **Add recipients** on the same page:
   - Choose **FreeScout user** or **Telegram group**
   - Enter the **chat ID**
   - Pick the **triggers** and, if you want, the **mailboxes**
   - Click **Test** to check that it works

   Coming from the original FreeScout **Telegram Notifications** module? Click **Import from Telegram module**. It copies the bot token (if none is set yet), the selected events and the mailbox → chat mapping, one group assignment per chat. Then deactivate the old module so messages aren't sent twice.

> FreeScout's cron (`php artisan schedule:run`) must be running, because messages go out through the queue. Any FreeScout install that sends email already has it.

---

## 🤖 How to get a Telegram bot token

1. Open a chat with [@BotFather](https://t.me/BotFather)
2. Send `/newbot` and follow the instructions
3. Copy the **bot token**

## 🆔 How to find a chat ID

- **User:** send `/start` to your bot first (bots cannot start a chat). Then get your ID from e.g. [@userinfobot](https://t.me/userinfobot).
- **Group:** add the bot to the group. Then forward a group message to [@userinfobot](https://t.me/userinfobot), or call `https://api.telegram.org/bot<TOKEN>/getUpdates`. Group IDs are negative, e.g. `-1001234567890`.

---

## 🛠 Notes

- Saving a recipient with the **same user/group and chat ID** updates the existing entry, the same way the WHMCS module does. **Edit** loads an entry into the form.
- If several recipients share a chat ID, that chat still gets each message only once.
- The bot token is stored **encrypted** in the database.
- Send errors (e.g. `chat not found`) are written to the FreeScout app log: *Manage → Logs → App Logs*.
- All settings are kept as FreeScout options (`bettertelegram.*`). No database migrations are needed.

---

## ❓ FAQ

### Can I send messages to group chats?
Yes. Add the bot to the group and use the group's chat ID (e.g. `-1001234567890`).

### Is it GDPR/DSGVO compliant?
No customer data is sent, only the ticket number, subject and a link to your FreeScout. Keep in mind that the **subject** is written by the customer.

### The test works, but ticket notifications don't arrive.
Check that FreeScout's cron and queue are running (*Manage → System → Cron Commands / Jobs*).

---

## 📄 License

GPL-3.0 © PawHost.de

## 🙌 Contributions

Issues and PRs are welcome.
