<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:reset-admin {--email= : Email of the administrator} {--password= : New password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset administrator password for development or recovery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        if ($password === '' && $this->input->isInteractive() && ! app()->runningUnitTests() && function_exists('posix_isatty') && @posix_isatty(STDIN)) {
            $password = (string) $this->secret('Enter new administrator password:');
        }

        if ($password === '') {
            $this->error('Password cannot be empty. Please specify --password.');

            return Command::FAILURE;
        }

        if ($email !== '') {
            $user = User::where('email', $email)->first();
        } else {
            $user = User::where('role', 'super admin')->first();
        }

        if (! $user) {
            $targetDesc = $email !== '' ? "with email [{$email}]" : 'with super admin role';
            $this->error("No user found {$targetDesc}.");

            return Command::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Successfully reset password for user [{$user->email}].");

        return Command::SUCCESS;
    }
}
