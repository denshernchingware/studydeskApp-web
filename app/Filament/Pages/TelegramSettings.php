<?php

namespace App\Filament\Pages;

use App\Actions\Telegram\CreateTelegramLink;
use App\Models\TelegramAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use RuntimeException;

class TelegramSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Telegram';

    protected static ?string $title = 'Telegram settings';

    protected string $view = 'filament.pages.telegram-settings';

    public ?string $linkUrl = null;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user() instanceof User, 403);
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createLink')
                ->label('Generate Telegram link')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => $this->account() === null)
                ->disabled(fn (): bool => blank(config('services.telegram.bot_token'))
                    || blank(config('services.telegram.bot_username'))
                    || blank(config('services.telegram.webhook_secret')))
                ->action(fn (CreateTelegramLink $createTelegramLink) => $this->createLink($createTelegramLink)),
            Action::make('disconnect')
                ->label('Disconnect Telegram')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('The bot will stop processing messages and sending notifications to this chat.')
                ->visible(fn (): bool => $this->account() !== null)
                ->action(fn () => $this->disconnect()),
            Action::make('toggleReminders')
                ->label(fn (): string => $this->account()?->reminders_enabled
                    ? 'Pause subject reminders'
                    : 'Enable subject reminders')
                ->color('gray')
                ->visible(fn (): bool => $this->account() !== null)
                ->action(fn () => $this->toggleReminders()),
        ];
    }

    #[Computed]
    public function account(): ?TelegramAccount
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->telegramAccount()->first() : null;
    }

    public function createLink(CreateTelegramLink $createTelegramLink): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $this->linkUrl = $createTelegramLink->create($user);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Telegram link generated')
            ->body('This one-time link expires in 10 minutes.')
            ->success()
            ->send();
    }

    public function disconnect(): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $user->telegramAccount()->delete();
        $this->linkUrl = null;
        unset($this->account);

        Notification::make()
            ->title('Telegram disconnected')
            ->success()
            ->send();
    }

    public function toggleReminders(): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $account = $user->telegramAccount()->first();

        if (! $account instanceof TelegramAccount) {
            abort(404);
        }

        $account->update(['reminders_enabled' => ! $account->reminders_enabled]);
        unset($this->account);

        Notification::make()
            ->title($account->reminders_enabled ? 'Subject reminders enabled' : 'Subject reminders paused')
            ->success()
            ->send();
    }
}
