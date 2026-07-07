<?php

namespace App\Telegram;

use App\Models\TelegramAllowedPhone;
use App\Models\TelegramSubscriber;
use App\Models\TelegramTemplate;

/**
 * Processes an incoming Telegram update: onboarding, phone verification,
 * and simple commands. Authorization is by phone number — a user must
 * share their own contact, and the number must be on the allow-list.
 * All reply texts come from editable templates.
 */
class UpdateHandler
{
    public function __construct(
        private readonly TelegramClient $client,
    ) {}

    public function handle(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! is_array($message) || ! isset($message['chat']['id'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $from = $message['from'] ?? [];

        $subscriber = TelegramSubscriber::firstOrCreate(
            ['chat_id' => $chatId],
            [
                'first_name' => $from['first_name'] ?? null,
                'username' => $from['username'] ?? null,
            ],
        );

        if (isset($message['contact'])) {
            $this->handleContact($subscriber, $message['contact'], $from);

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '/stop') {
            $subscriber->update(['authorized' => false]);
            $this->reply($subscriber->chat_id, 'bot_unsubscribed', $this->client->removeKeyboard());

            return;
        }

        if ($subscriber->authorized) {
            $this->reply($subscriber->chat_id, 'bot_already_subscribed', $this->client->removeKeyboard());

            return;
        }

        // /start or anything else from an unverified user → ask for the phone.
        $this->promptForContact($subscriber->chat_id);
    }

    private function handleContact(TelegramSubscriber $subscriber, array $contact, array $from): void
    {
        // Guard: the shared contact must be the sender's own number.
        if (isset($contact['user_id'], $from['id']) && (string) $contact['user_id'] !== (string) $from['id']) {
            $this->reply($subscriber->chat_id, 'bot_not_own_contact', $this->contactKeyboard());

            return;
        }

        $phone = $contact['phone_number'] ?? null;

        if (! TelegramAllowedPhone::allows($phone)) {
            $subscriber->update(['phone' => $phone, 'authorized' => false, 'authorized_at' => null]);
            $this->reply($subscriber->chat_id, 'bot_not_authorized', $this->client->removeKeyboard());

            return;
        }

        $subscriber->update([
            'phone' => $phone,
            'authorized' => true,
            'authorized_at' => now(),
        ]);

        $this->reply($subscriber->chat_id, 'bot_verified', $this->client->removeKeyboard());
    }

    private function promptForContact(string $chatId): void
    {
        $this->reply($chatId, 'bot_welcome', $this->contactKeyboard());
    }

    private function contactKeyboard(): array
    {
        return $this->client->requestContactKeyboard(TelegramTemplate::render('bot_share_button'));
    }

    private function reply(string $chatId, string $templateKey, ?array $replyMarkup = null): void
    {
        $this->client->sendMessage($chatId, TelegramTemplate::render($templateKey), $replyMarkup);
    }
}
