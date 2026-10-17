<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * First start of a real installation (the "AGAPAY for Windows" installer runs it): creates the tables, loads the
 * reference data and the Administrator's account. It refuses to run again once anyone has an account.
 */
class InstallCommand extends Command
{
    protected $signature = 'agapay:install
        {--last-name= : The Administrator\'s last name}
        {--first-name= : The Administrator\'s first name}
        {--middle-initial= : The Administrator\'s middle initial (optional)}
        {--suffix= : Jr., Sr., II, III, IV or V (optional)}
        {--username= : The Administrator\'s username}
        {--password= : The Administrator\'s password (at least 8 characters)}
        {--from-file= : Read the account from a JSON file (the Windows installer uses this), then delete the file}
        {--status : Only say whether AGAPAY is already set up}';

    protected $description = 'Set up a clean AGAPAY: tables, reference data and the Administrator account (no sample records)';

    private const FIELDS = ['last_name', 'first_name', 'middle_initial', 'suffix', 'username', 'password'];

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

        $account = $this->option('from-file') ? $this->readAccountFile($this->option('from-file')) : $this->askForAccount();
        if ($account === null) {
            return self::FAILURE;
        }

        $validator = Validator::make($account, [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'regex:/^[A-Za-z]\.?$/'],
            'suffix' => ['nullable', Rule::in(User::SUFFIXES)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'password' => ['required', 'string', Password::min(8)],
        ], [
            'username.regex' => 'Use letters, numbers, dots, dashes or underscores only.',
            'middle_initial.regex' => 'Type one letter only for the middle initial, e.g. M.',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);

        $admin = new User([...$validator->validated(),
            'role_id' => Role::where('slug', Role::ADMIN)->value('id'), 'status' => User::STATUS_ACTIVE]);
        $admin->must_change_password = false;   // chosen by the Administrator just now
        $admin->save();

        $this->info("AGAPAY is ready. Sign in as {$admin->username}.");

        return self::SUCCESS;
    }

    /** Options first; whatever is missing is asked (the optional parts only when nothing was given as options). */
    private function askForAccount(): ?array
    {
        $asking = $this->option('last-name') === null;
        $account = [
            'last_name' => $this->option('last-name') ?? $this->ask('Last name'),
            'first_name' => $this->option('first-name') ?? $this->ask('First name'),
            'middle_initial' => $this->option('middle-initial') ?? ($asking ? $this->ask('Middle initial (leave blank if none)') : null),
            'suffix' => $this->option('suffix') ?? ($asking ? $this->ask('Suffix such as Jr. (leave blank if none)') : null),
            'username' => $this->option('username') ?? $this->ask('Username'),
            'password' => $this->option('password'),
        ];
        if ($account['password'] === null) {
            $account['password'] = $this->secret('Password (at least 8 characters)');
            if ($this->secret('Type the password again') !== $account['password']) {
                $this->error('The two passwords do not match.');

                return null;
            }
        }

        return array_map(fn ($value) => $value === '' ? null : $value, $account);
    }

    /** The file holds the password, so it is deleted as soon as it has been read, even when it is invalid. */
    private function readAccountFile(string $path): ?array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if (is_file($path)) {
            unlink($path);
        }
        $data = $json === false ? null : json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $json), true);
        if (! is_array($data)) {
            $this->error('The account file could not be read.');

            return null;
        }

        return collect(self::FIELDS)
            ->mapWithKeys(fn ($key) => [$key => isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null])
            ->all();
    }
}
