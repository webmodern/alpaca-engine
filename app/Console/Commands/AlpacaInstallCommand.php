<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Env;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'alpaca:install',
    description: 'Install Starter Kit settings for Alpaca Engine',
    aliases: ['alpaca:setup', 'alpaca:starter']
)]
class AlpacaInstallCommand extends Command
{
    public function handle()
    {
        $this->line('Alpaca Starter Kit Installer for Laravel 12');
        $this->newLine();

        $envPath = base_path('.env');

        // 0. .env Switch (если нужно)
        if (confirm('Do you want to update the .env file?', default: false)) {
            $envFiles = collect(glob(base_path('.env.*')))
                ->map(fn($file) => basename($file))
                ->filter(fn($file) => !in_array($file, ['.env.plesk']))
                ->values();

            $options = $envFiles
                ->map(fn($file) => str_replace('.env.', '', $file))
                ->all();

            $options[] = 'restore';

            $envToUpdate = select(
                'Select which .env.* file to update:',
                options: array_combine($options, $options),
                default: $options[0]
            );

            if ($envToUpdate !== 'restore') {
                $this->info("Switching to .env.$envToUpdate...");
                $exitCode = null;
                passthru('./deploy/env_switch.sh ' . escapeshellarg($envToUpdate), $exitCode);

                if ($exitCode !== 0) {
                    $this->error('env_switch.sh returned a non-zero exit code!');
                }
            }
        }

        // 1. Stages selection
        $steps = multiselect(
            label: 'Which steps do you want to perform?',
            options: [
                'env'          => 'Update APP settings',
                'db'           => 'Update DB settings and check connection',
                'migrate'      => 'Run migrations',
                'user'         => 'Create admin user',
                'mail'         => 'Configure mail and send test email',
                'storage_link' => 'Re-create storage symlink',
            ],
            default: []
        );

        if (empty($steps)) {
            $this->info('No further steps selected. Installation finished!');
            return;
        }

        // 2. APP settings (env)
        if (in_array('env', $steps)) {
            $appName   = text('APP_NAME', default: Env::get('APP_NAME') ?? 'Laravel');
            $appUrl    = text('APP_URL', default: Env::get('APP_URL') ?? 'http://localhost');
            $appLocale = text('APP_LOCALE', default: Env::get('APP_LOCALE') ?? 'en');
            $appDebug  = select('APP_DEBUG', ['true', 'false'], default: Env::get('APP_DEBUG') ?? 'false');
            $appKeyRegen = confirm('Regenerate APP_KEY?', default: false);

            Env::writeVariables([
                'APP_NAME'     => $appName,
                'APP_URL'      => $appUrl,
                'APP_LOCALE'   => $appLocale,
                'APP_DEBUG'    => $appDebug,
            ], $envPath, true);

            if ($appKeyRegen) {
                $this->callSilent('key:generate');
                $this->info('Application key regenerated!');
            }

            $this->line('Done with APP settings setup.');
        }

        // 3. DB settings (все параметры)
        if (in_array('db', $steps)) {
            $dbConnection = text('DB_CONNECTION', default: Env::get('DB_CONNECTION') ?? 'mysql');
            $dbHost = text('DB_HOST', default: Env::get('DB_HOST') ?? 'localhost');
            $dbPort = text('DB_PORT', default: Env::get('DB_PORT') ?? '3306');
            $dbName = text('DB_DATABASE', default: Env::get('DB_DATABASE') ?? '');
            $dbUser = text('DB_USERNAME', default: Env::get('DB_USERNAME') ?? '');
            $dbPass = text('DB_PASSWORD', default: Env::get('DB_PASSWORD') ?? '');

            Env::writeVariables([
                'DB_CONNECTION' => $dbConnection,
                'DB_HOST'       => $dbHost,
                'DB_PORT'       => $dbPort,
                'DB_DATABASE'   => $dbName,
                'DB_USERNAME'   => $dbUser,
                'DB_PASSWORD'   => $dbPass,
            ], $envPath, true);

            // Test DB connection (с учётом всех параметров)
            $connected = $this->testDbConnection($dbName, $dbUser, $dbPass, $dbConnection, $dbHost, $dbPort);
            while (!$connected) {
                $this->error('Database connection failed. Please enter the credentials again.');
                $dbConnection = text('DB_CONNECTION', default: $dbConnection);
                $dbHost = text('DB_HOST', default: $dbHost);
                $dbPort = text('DB_PORT', default: $dbPort);
                $dbName = text('DB_DATABASE', default: $dbName);
                $dbUser = text('DB_USERNAME', default: $dbUser);
                $dbPass = text('DB_PASSWORD', default: $dbPass);
                Env::writeVariables([
                    'DB_CONNECTION' => $dbConnection,
                    'DB_HOST'       => $dbHost,
                    'DB_PORT'       => $dbPort,
                    'DB_DATABASE'   => $dbName,
                    'DB_USERNAME'   => $dbUser,
                    'DB_PASSWORD'   => $dbPass,
                ], $envPath, true);
                $connected = $this->testDbConnection($dbName, $dbUser, $dbPass, $dbConnection, $dbHost, $dbPort);
            }
            $this->info('Database connection successful.');
            $this->line('Done with DB settings setup.');
        }

        // 4. Migrate
        if (in_array('migrate', $steps)) {
            $this->info('Starting STEP: migrate');
            if (confirm('Do you want to run migrations?', default: false)) {
                $result = $this->callSilent('migrate', ['--force' => true]);
                if ($result === 0) {
                    $this->info('Migrations completed.');
                } else {
                    $this->error('Migration failed. Please check your migrations manually.');
                }
            }
            $this->line('Done with migration step.');
        }

        // 5. Create user (и super admin внутри этого этапа)
        $createdUserId = null;
        $createdUserEmail = null;
        if (in_array('user', $steps)) {
            $this->info('Starting STEP: user creation');
            $name = text('User Name', default: 'Admin');
            $email = text('User Email', default: 'admin@example.com');
            $password = password('User Password');

            $userModel = config('auth.providers.users.model', \App\Models\User::class);

            $user = $userModel::where('email', $email)->first();
            if (!$user) {
                $user = $userModel::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
                $this->info('User created.');

                if (confirm('Mark this user as email confirmed?', default: true)) {
                    $user->email_verified_at = now();
                    $user->save();
                    $this->info('User email marked as confirmed.');
                }

                if (confirm('Grant this user Super Admin role?', default: true)) {
                    $this->callSilent('shield:super-admin', ['--user' => $user->id, '--panel' => 'admin']);
                    $this->info('Super Admin role assigned.');
                }
            } else {
                $this->info('User already exists. Skipping creation.');
            }

            $this->line('Done with user creation step.');
        }

        // 6. Mail (обновленная логика)
        if (in_array('mail', $steps)) {
            $this->info('Starting STEP: mail configuration');
            $mailMailer = select('MAIL_MAILER', ['smtp', 'mailgun', 'sendmail', 'ses', 'log'], default: Env::get('MAIL_MAILER', ) ?? 'smtp' );
            $mailScheme = text('MAIL_SCHEME', default: Env::get('MAIL_SCHEME', ) ?? 'smtps');
            $mailHost   = text('MAIL_HOST', default: Env::get('MAIL_HOST', ) ?? 'localhost');
            $mailPort   = text('MAIL_PORT', default: Env::get('MAIL_PORT', ) ?? 465);
            $mailUser   = text('MAIL_USERNAME', default: Env::get('MAIL_USERNAME', ) ?? '');
            $mailPass   = text('MAIL_PASSWORD', default: Env::get('MAIL_PASSWORD', ) ?? '');
            $mailFrom   = text('MAIL_FROM_ADDRESS', default: Env::get('MAIL_FROM_ADDRESS', ) ?? 'admin@example.com');

            Env::writeVariables([
                'MAIL_MAILER'       => $mailMailer,
                'MAIL_SCHEME'       => $mailScheme,
                'MAIL_HOST'         => $mailHost,
                'MAIL_PORT'         => $mailPort,
                'MAIL_USERNAME'     => $mailUser,
                'MAIL_PASSWORD'     => $mailPass,
                'MAIL_FROM_ADDRESS' => $mailFrom,
            ], $envPath, true);

            // Новый шаг: запрос email для тестового письма
            $testEmail = text('Enter email for test mail (leave empty to skip):', default: '');

            if (!empty($testEmail)) {
                $ok = false;
                $tries = 0;
                do {
                    try {
                        $this->callSilent('cache:clear');
                        $this->callSilent('config:clear');
                        $this->callSilent('config:cache');
                        config([
                            'mail.default' => $mailMailer,
                            "mail.mailers.$mailMailer.host" => $mailHost,
                            "mail.mailers.$mailMailer.port" => $mailPort,
                            "mail.mailers.$mailMailer.username" => $mailUser,
                            "mail.mailers.$mailMailer.password" => $mailPass,
                            "mail.mailers.$mailMailer.scheme" => $mailScheme,
                            'mail.from.address' => $mailFrom,
                        ]);

                        Mail::to($testEmail)->send(new \App\Mail\GenericNotification());
                        $this->info("Test email sent successfully to {$testEmail}!");
                        $ok = true;
                    } catch (\Throwable $e) {
                        $this->error('Failed to send test email: ' . $e->getMessage());
                        $ok = false;
                    }
                    if (!$ok && ++$tries < 3) {
                        if (!confirm('Do you want to reconfigure mail settings?', default: false)) {
                            break;
                        }
                        $mailMailer = select('MAIL_MAILER', ['smtp', 'mailgun', 'sendmail', 'ses', 'log'], default: $mailMailer);
                        $mailScheme = text('MAIL_SCHEME', default: $mailScheme);
                        $mailHost   = text('MAIL_HOST', default: $mailHost);
                        $mailPort   = text('MAIL_PORT', default: $mailPort);
                        $mailUser   = text('MAIL_USERNAME', default: $mailUser);
                        $mailPass   = text('MAIL_PASSWORD', default: $mailPass);
                        $mailFrom   = text('MAIL_FROM_ADDRESS', default: $mailFrom);

                        Env::writeVariables([
                            'MAIL_MAILER'       => $mailMailer,
                            'MAIL_SCHEME'       => $mailScheme,
                            'MAIL_HOST'         => $mailHost,
                            'MAIL_PORT'         => $mailPort,
                            'MAIL_USERNAME'     => $mailUser,
                            'MAIL_PASSWORD'     => $mailPass,
                            'MAIL_FROM_ADDRESS' => $mailFrom,
                        ], $envPath, true);

                        $testEmail = text('Enter email for test mail (leave empty to skip):', default: $testEmail);
                    }
                } while (!$ok && $tries < 5);
            } else {
                $this->info('Test mail skipped, parameters updated.');
            }

            $this->line('Done with mail configuration step.');
        }

        // 7. Storage symlink
        if (in_array('storage_link', $steps)) {
            $this->info('Starting STEP: (re)create storage symlink');
            if (confirm('Do you want to (re)create the storage symlink (public/storage)?', default: true)) {
                $result = $this->callSilent('storage:link');
                if ($result === 0) {
                    $this->info('Symlink created successfully: public/storage → storage/app/public');
                } else {
                    $this->error('Failed to create the symlink. You may need to run manually: php artisan storage:link');
                }
            } else {
                $this->info('Symlink step skipped.');
            }
            $this->line('Done with storage symlink step.');
        }

        // 8. Сброс кэшей Laravel
        $this->info('Clearing Laravel cache, config, route, view, event...');
        $this->clearCaches();

        // 9. Финальное сообщение
        $url = (Env::get('APP_URL') ?? 'http://localhost') . '/admin';
        $this->info("🎉 All done! Happy coding. Visit your admin panel: $url");

        $this->line('');
        $this->line('With love from <fg=red;options=bold>Web Modern</> (https://web-modern.com)');
        $this->line('');
    }

    // --- Helpers ---

    protected function testDbConnection(
        $db,
        $user,
        $pass,
        $driver = 'mysql',
        $host = 'localhost',
        $port = '3306'
    ): bool {
        config([
            "database.connections.$driver.database" => $db,
            "database.connections.$driver.username" => $user,
            "database.connections.$driver.password" => $pass,
            "database.connections.$driver.host"     => $host,
            "database.connections.$driver.port"     => $port,
            'database.default'                      => $driver,
        ]);

        try {
            DB::purge('mysql');
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function assignSuperAdminRole($user)
    {
        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            $superAdminRole = \BezhanSalleh\FilamentShield\FilamentShield::createRole();
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->assignRole($superAdminRole);
        }
    }

    protected function clearCaches()
    {
        try {
            $this->callSilent('cache:clear');
            $this->callSilent('config:clear');
            $this->callSilent('route:clear');
            $this->callSilent('view:clear');
            $this->callSilent('event:clear');
        } catch (\Throwable $e) {
            $this->error('Cache clear failed: ' . $e->getMessage());
        }
    }
}
