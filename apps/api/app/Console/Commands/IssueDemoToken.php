<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueDemoToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:issue-demo-token {email=vipul@example.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Issue a local assessment Sanctum token for a demo user';

    /**
     * Issue a replacement local assessment token for the requested demo user.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No demo user exists with email {$email}.");

            return self::FAILURE;
        }

        $user->tokens()->where('name', 'assessment-mobile')->delete();

        $plainTextToken = $user->createToken('assessment-mobile')->plainTextToken;

        $this->warn('Local assessment use only. This plaintext token is shown once:');
        $this->line($plainTextToken);

        return self::SUCCESS;
    }
}
