<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * First start of a real installation (the portable "AGAPAY for Windows" runs it): creates the tables, loads the
 * reference data and the Administrator's account. It refuses to run again once anyone has an account.
 */
class InstallCommand extends Command
{
    protected $signature = 'agapay:install
        {--name= : The Administrator\'s full name}
        {--username= : The Administrator\'s username}
        {--password= : The Administrator\'s password (at least 8 characters)}
        {--status : Only say whether AGAPAY is already set up}';

    protected $description = 'Set up a clean AGAPAY: tables, reference data and the Administrator account (no sample records)';

    public function handle(): int
    {
        $installed = Schema::hasTable('users') && User::query()->exists();

        if ($this->option('status')) {
            $this->line($installed ? 'installed' : 'not installed');

            return $installed ? self::SUCCESS : self::FAILURE;
        }
        if ($installed) {
            $this->error('AGAPAY is already set up; nothing was changed.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Administrator\'s full name');
        $username = $this->option('username') ?? $this->ask('Username');
        $password = $this->option('password');
        if ($password === null) {
            $password = $this->secret('Password (at least 8 characters)');
            if ($this->secret('Type the password again') !== $password) {
                $this->error('The two passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(compact('name', 'username', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'password' => ['required', 'string', Password::min(8)],
        ], ['username.regex' => 'Use letters, numbers, dots, dashes or underscores only.']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);

        $admin = new User(['name' => $name, 'username' => $username, 'password' => $password,
            'role_id' => Role::where('slug', Role::ADMIN)->value('id'), 'status' => User::STATUS_ACTIVE]);
        $admin->must_change_password = false;   // chosen by the Administrator just now
        $admin->save();

        $this->info("AGAPAY is ready. Sign in as {$username}.");

        return self::SUCCESS;
    }
}
