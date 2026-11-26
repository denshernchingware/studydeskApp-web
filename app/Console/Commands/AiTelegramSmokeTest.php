<?php

namespace App\Console\Commands;

use App\Actions\Telegram\BuildStudyAdvisorContext;
use App\Ai\Agents\StudyAdvisor;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Enums\Lab;
use Throwable;

#[Signature('ai:telegram-smoke-test {--user=} {--question=}')]
#[Description('Send an explicit prompt to Groq using a linked owner study context; no Telegram message is sent.')]
class AiTelegramSmokeTest extends Command
{
    public function handle(BuildStudyAdvisorContext $buildStudyAdvisorContext): int
    {
        $userOption = $this->option('user');
        $question = $this->option('question');

        try {
            Validator::make(
                ['user' => $userOption, 'question' => $question],
                [
                    'user' => ['required', 'integer', 'min:1', 'exists:users,id'],
                    'question' => ['required', 'string', 'max:1000'],
                ],
            )->validate();
        } catch (ValidationException) {
            $this->error('Provide a valid --user=<id> and an explicit --question="...".');

            return self::FAILURE;
        }

        if (blank(config('ai.providers.groq.key'))) {
            $this->error('Set GROQ_API_KEY before running the AI smoke test.');

            return self::FAILURE;
        }

        $user = User::query()
            ->whereKey((int) $userOption)
            ->whereHas('telegramAccount', fn ($query) => $query->whereNull('disabled_at'))
            ->first();

        if (! $user instanceof User) {
            $this->error('The selected user does not have an active linked Telegram account.');

            return self::FAILURE;
        }

        $semester = is_numeric($user->active_semester_id)
            ? $user->semesters()->whereKey((int) $user->active_semester_id)->first()
            : null;

        if (! $semester instanceof Semester) {
            $this->error('The selected user has no owner-matched active semester.');

            return self::FAILURE;
        }

        $agent = new StudyAdvisor($user, $semester, $buildStudyAdvisorContext->build($user, $semester));

        try {
            $response = $agent->prompt(
                (string) $question,
                provider: Lab::Groq,
                model: (string) config('ai.providers.groq.models.text.default'),
                timeout: 30,
            );
        } catch (Throwable $exception) {
            Log::warning('Telegram AI smoke test failed.', [
                'user_id' => $user->getKey(),
                'exception_type' => $exception::class,
            ]);
            $this->error('The Groq request failed. Check application logs for the safe exception category.');

            return self::FAILURE;
        }

        $this->info('Groq responded. No Telegram message was sent.');
        $this->line($response->text);

        return self::SUCCESS;
    }
}
