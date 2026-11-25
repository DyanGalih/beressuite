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
    protected $signature = 'auth:reset-admin {--email=dyan.galih@gmail.com : Email of the administrator} {--password=beressuite@123 : New password}';

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

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::where('role', 'super admin')->first();
        }

        if (! $user) {
            $this->error("No user found with email [{$email}] or with super admin role.");

            return Command::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Successfully reset password for user [{$user->email}].");

        return Command::SUCCESS;
    }
}
