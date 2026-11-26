<?php

namespace App\Jobs;

use App\Actions\Telegram\BuildStudyAdvisorContext;
use App\Ai\Agents\StudyAdvisor;
use App\Enums\TelegramDeliveryStatus;
use App\Models\Semester;
use App\Models\TelegramAccount;
use App\Models\TelegramDelivery;
use App\Models\TelegramUpdate;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Enums\Lab;
use Throwable;

class ProcessTelegramAiMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $telegramUpdateId) {}

    public function uniqueId(): string
    {
        return (string) $this->telegramUpdateId;
    }

    public function handle(BuildStudyAdvisorContext $buildStudyAdvisorContext): void
    {
        $update = TelegramUpdate::query()->find($this->telegramUpdateId);

        if (! $update instanceof TelegramUpdate
            || $update->update_type !== 'message'
            || blank($update->message_text)
            || mb_strlen($update->message_text) > 2000
            || blank($update->chat_id)
            || blank($update->telegram_user_id)) {
            return;
        }

        $account = TelegramAccount::query()
            ->with('user')
            ->where('telegram_user_id', $update->telegram_user_id)
            ->where('chat_id', $update->chat_id)
            ->whereNull('disabled_at')
            ->first();
        $user = $account?->user;

        if (! $account instanceof TelegramAccount || ! $user instanceof User) {
            return;
        }

        $dedupeKey = 'ai-reply:'.$update->update_id;
        $existingDelivery = TelegramDelivery::query()->where('dedupe_key', $dedupeKey)->first();

        if ($existingDelivery instanceof TelegramDelivery) {
            $this->dispatchPendingReply($existingDelivery, $account, $user);

            return;
        }

        $rateKey = 'telegram-ai:'.$user->getKey();

        if (! RateLimiter::attempt($rateKey, 10, fn (): bool => true, 60)) {
            if (RateLimiter::attempt('telegram-ai-notice:'.$user->getKey(), 1, fn (): bool => true, 60)) {
                $this->storeReply($update, $account, $user, null, 'Please slow down. StudyDesk accepts up to 10 study-advisor questions per minute.');
            }

            return;
        }

        $semester = is_numeric($user->active_semester_id)
            ? $user->semesters()->whereKey((int) $user->active_semester_id)->first()
            : null;

        if (! $semester instanceof Semester) {
            $this->storeReply($update, $account, $user, null, 'Please choose an active semester in StudyDesk before asking about your study progress.');

            return;
        }

        if (blank(config('ai.providers.groq.key'))) {
            $this->storeReply($update, $account, $user, $semester, 'The study advisor is not configured yet. Set GROQ_API_KEY and try again.');

            return;
        }

        $conversationId = $this->ownedConversationId($account, $user, $semester);
        $agent = new StudyAdvisor($user, $semester, $buildStudyAdvisorContext->build($user, $semester));

        try {
            $response = $agent->continueOrStart($conversationId, as: $user)->prompt(
                (string) $update->message_text,
                provider: Lab::Groq,
                model: (string) config('ai.providers.groq.models.text.default'),
                timeout: 30,
            );
            $reply = filled($response->text)
                ? Str::limit(trim($response->text), 3800, '...')
                : 'I could not form a useful answer from the available study data. Please try a more specific question.';
            $newConversationId = $response->conversationId ?? $agent->currentConversation();

            if (is_string($newConversationId)) {
                $account->forceFill([
                    'ai_conversation_id' => $newConversationId,
                    'ai_conversation_semester_id' => $semester->getKey(),
                ])->save();
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram study-advisor request failed.', [
                'update_id' => $update->update_id,
                'user_id' => $user->getKey(),
                'exception_type' => $exception::class,
            ]);
            $reply = 'The study advisor is temporarily unavailable. Please try again shortly.';
        }

        $this->storeReply($update, $account, $user, $semester, $reply);
    }

    private function ownedConversationId(TelegramAccount $account, User $user, Semester $semester): ?string
    {
        $conversationId = $account->ai_conversation_id;

        if (! is_string($conversationId) || $conversationId === ''
            || (int) $account->ai_conversation_semester_id !== (int) $semester->getKey()) {
            return null;
        }

        $store = app(ConversationStore::class);

        return $store instanceof VerifiesConversationOwnership
            && $store->conversationBelongsTo($conversationId, $user->getMorphClass(), $user->getKey())
            ? $conversationId
            : null;
    }

    private function storeReply(
        TelegramUpdate $update,
        TelegramAccount $account,
        User $user,
        ?Semester $semester,
        string $reply,
    ): void {
        $delivery = TelegramDelivery::query()->firstOrCreate(
            ['dedupe_key' => 'ai-reply:'.$update->update_id],
            [
                'user_id' => $user->getKey(),
                'telegram_account_id' => $account->getKey(),
                'semester_id' => $semester?->getKey(),
                'notification_type' => 'ai_response',
                'message_text' => Str::limit(trim($reply), 3800, '...'),
                'status' => TelegramDeliveryStatus::Pending,
                'attempts' => 0,
            ],
        );

        $this->dispatchPendingReply($delivery, $account, $user);
    }

    private function dispatchPendingReply(TelegramDelivery $delivery, TelegramAccount $account, User $user): void
    {
        if ((int) $delivery->user_id !== (int) $user->getKey()
            || (int) $delivery->telegram_account_id !== (int) $account->getKey()
            || $delivery->notification_type !== 'ai_response'
            || $delivery->status !== TelegramDeliveryStatus::Pending) {
            return;
        }

        SendTelegramAiReply::dispatch((int) $delivery->getKey());
    }
}
