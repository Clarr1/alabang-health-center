<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceRun;
use Database\Seeders\SurveillanceDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SurveillanceDemo extends Command
{
    protected $signature = 'surveillance:demo {--activate : Temporarily switch the local application to the separate fictional database} {--restore : Restore the database configuration saved before demo activation}';

    protected $description = 'Create an isolated fictional surveillance database with real calculated example outcomes';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The fictional demo is available only in local or testing environments.');

            return self::FAILURE;
        }
        $manifestPath = storage_path('app/private/surveillance-demo.json');
        try {
            if ($this->option('restore')) {
                return $this->restore($manifestPath);
            }
            if (File::exists($manifestPath)) {
                $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
                if ($manifest['active'] ?? false) {
                    $this->error('A fictional demo is already active. Use surveillance:demo --restore before creating another.');

                    return self::FAILURE;
                }
            }
            $sourceConnection = DB::getDefaultConnection();
            $source = config('database.connections.'.$sourceConnection);
            if (($source['driver'] ?? '') !== 'mysql' || ! in_array($source['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new RuntimeException('Demo creation requires the local MySQL connection. The existing database will not be seeded.');
            }
            $database = 'ahc_surveillance_demo_'.now('Asia/Manila')->format('Ymd_His').'_'.bin2hex(random_bytes(3));
            $server = array_replace($source, ['database' => null, 'url' => null]);
            config(['database.connections.surveillance_demo_server' => $server]);
            DB::connection('surveillance_demo_server')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            config(['database.connections.surveillance_demo' => array_replace($server, ['database' => $database]), 'cache.default' => 'array']);
            try {
                DB::setDefaultConnection('surveillance_demo');
                if (Artisan::call('migrate', ['--database' => 'surveillance_demo', '--no-interaction' => true]) !== 0) {
                    throw new RuntimeException('Demo migrations failed: '.Artisan::output());
                }
                app(SurveillanceDemoSeeder::class)->run();
                $run = SurveillanceRun::where('status', 'Completed')->latest('window_end')->with('results')->firstOrFail();
                $manifest = [
                    'database' => $database, 'original_database' => $source['database'], 'active' => false,
                    'created_at' => now()->toIso8601String(), 'fictional_only' => true,
                    'patients' => PatientInformation::count(), 'consultations' => MedicalRecord::count(), 'alerts' => SurveillanceAlert::count(),
                    'outcomes' => $run->results->map->only(['syndrome_name', 'observed', 'expected', 'outcome'])->all(),
                    'email' => 'admin@surveillance-demo.test', 'password' => SurveillanceDemoSeeder::PASSWORD,
                ];
                File::ensureDirectoryExists(dirname($manifestPath));
                File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } finally {
                DB::setDefaultConnection($sourceConnection);
                DB::purge('surveillance_demo');
                DB::purge('surveillance_demo_server');
            }
            if ($this->option('activate')) {
                $this->activate($manifestPath, $manifest);
            }
            $this->info('Fictional demonstration created: '.$database);
            $this->table(['Scenario', 'Observed', 'Expected', 'Outcome'], array_map(fn (array $result): array => [
                $result['syndrome_name'], $result['observed'], $result['expected'] === null ? 'Not estimable' : round($result['expected'], 2), $result['outcome'],
            ], $manifest['outcomes']));
            $this->info('Login account type: Admin | Email: '.$manifest['email'].' | Password: '.$manifest['password']);
            $this->info('Your original database was not modified. Restore it with: php artisan surveillance:demo --restore');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $manifest */
    private function activate(string $path, array $manifest): void
    {
        $environmentPath = app()->environmentFilePath();
        $contents = File::get($environmentPath);
        $updates = [
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $manifest['database'], 'DB_URL' => '',
            'SURVEILLANCE_DEMO' => 'true', 'TEXTBEE_ENABLED' => 'false', 'MAIL_MAILER' => 'array',
            'SESSION_COOKIE' => 'ahc_surveillance_demo_session', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database',
        ];
        $manifest['previous_environment'] = [];
        foreach ($updates as $key => $value) {
            $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*\r?$/m';
            $manifest['previous_environment'][$key] = preg_match($pattern, $contents, $matches) ? rtrim($matches[0], "\r") : null;
            $contents = preg_match($pattern, $contents) ? preg_replace_callback($pattern, fn (): string => $key.'='.$value, $contents) : rtrim($contents)."\n".$key.'='.$value."\n";
        }
        $manifest['active'] = true;
        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::replace($environmentPath, $contents);
        Artisan::call('config:clear');
        $this->info('The local app now uses the isolated fictional demo. Sign in with the demo account.');
    }

    private function restore(string $path): int
    {
        if (! File::exists($path)) {
            throw new RuntimeException('No saved fictional demo configuration exists.');
        }
        $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! ($manifest['active'] ?? false)) {
            $this->info('The fictional demo is not active.');

            return self::SUCCESS;
        }
        $environmentPath = app()->environmentFilePath();
        $contents = File::get($environmentPath);
        $expected = '/^DB_DATABASE='.preg_quote($manifest['database'], '/').'\r?$/m';
        if (! preg_match($expected, $contents)) {
            throw new RuntimeException('The database configuration changed after demo activation. Restore was stopped to preserve your changes.');
        }
        foreach ($manifest['previous_environment'] as $key => $originalLine) {
            $pattern = '/^'.preg_quote($key, '/').'=[^\r\n]*\r?\n?/m';
            $contents = preg_replace_callback($pattern, fn (): string => $originalLine === null ? '' : $originalLine."\n", $contents);
        }
        File::replace($environmentPath, $contents);
        Artisan::call('config:clear');
        $manifest['active'] = false;
        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info('Original database configuration restored. Fictional data remains in its separate database.');

        return self::SUCCESS;
    }
}
