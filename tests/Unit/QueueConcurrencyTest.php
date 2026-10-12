<?php

use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('handles simultaneous manual MySQL assignments with a clear conflict', function (string $conflict) {
    $schema = 'ahc_queue_test_'.bin2hex(random_bytes(6));
    $defaultConnection = DB::getDefaultConnection();
    $mysql = array_replace(config('database.connections.mysql'), ['database' => null, 'url' => null]);
    config(['database.connections.queue_test_server' => $mysql]);
    $server = DB::connection('queue_test_server');
    $server->statement("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $processes = [];

    try {
        config(['database.connections.queue_test' => array_replace($mysql, ['database' => $schema])]);
        DB::setDefaultConnection('queue_test');
        Artisan::call('migrate', ['--database' => 'queue_test', '--no-interaction' => true]);
        $staffIds = User::factory()->count(2)->create()->pluck('user_id')->all();
        $patientIds = PatientInformation::factory()->count(8)->create()->pluck('patient_id')->all();
        $assignedNumbers = [40, 12, 95, 6, 1001, 2, 880, 1045];

        if ($conflict === 'number') {
            $assignedNumbers[7] = $assignedNumbers[0];
        } else {
            $patientIds[7] = $patientIds[6];
        }

        $date = now('Asia/Manila')->toDateString();
        DB::table('queue_days')->insert(['queue_date' => $date, 'last_number' => 9000]);
        DB::beginTransaction();
        DB::table('queue_days')->where('queue_date', $date)->lockForUpdate()->first();

        foreach ($patientIds as $index => $patientId) {
            $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::onceUsingId((int) getenv('QUEUE_TEST_USER'));
echo "ready\n";
flush();
try {
    $queue = app(App\Services\QueueWorkflow::class)->addPatient((int) getenv('QUEUE_TEST_PATIENT'), getenv('QUEUE_TEST_NUMBER'));
    echo $queue->queue_number;
} catch (Illuminate\Validation\ValidationException $exception) {
    if (isset($exception->errors()['queueNumber'])) {
        echo 'number-conflict:'.$exception->errors()['queueNumber'][0];
    } else {
        echo 'patient-conflict:'.$exception->errors()['queue'][0];
    }
}
PHP;
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $schema, 'DB_URL' => '',
                'DB_HOST' => (string) $mysql['host'], 'DB_PORT' => (string) $mysql['port'],
                'DB_USERNAME' => (string) $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'],
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'PAO_DISABLE' => '1',
                'QUEUE_TEST_USER' => (string) $staffIds[$index % 2], 'QUEUE_TEST_PATIENT' => (string) $patientId,
                'QUEUE_TEST_NUMBER' => (string) $assignedNumbers[$index],
            ]);
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            $deadline = microtime(true) + 15;
            while (! str_contains($process->getOutput(), 'ready') && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            expect(str_contains($process->getOutput(), 'ready'))->toBeTrue();
        }

        DB::commit();
        $results = [];

        foreach ($processes as $process) {
            $exitCode = $process->wait();
            $this->assertSame(0, $exitCode, $process->getErrorOutput().$process->getOutput());
            $results[] = trim(preg_replace('/^ready\\r?\\n/', '', $process->getOutput()));
        }

        $conflicts = array_values(array_filter($results, fn (string $result): bool => str_contains($result, '-conflict:')));
        $numbers = array_map('intval', array_filter($results, fn (string $result): bool => ctype_digit($result)));
        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0])->toContain($conflict === 'number' ? 'number-conflict:This queue number has already been assigned today' : 'patient-conflict:This patient already has an active queue entry today')
            ->and($numbers)->toHaveCount(7)
            ->and(array_diff($numbers, $assignedNumbers))->toBe([])
            ->and(array_unique($numbers))->toHaveCount(7)
            ->and(Queue::count())->toBe(7)
            ->and(Queue::whereNotIn('assigned_by', $staffIds)->count())->toBe(0)
            ->and(Queue::whereNull('assigned_by')->count())->toBe(0)
            ->and(DB::table('queue_days')->value('last_number'))->toBe(9000);

        if ($conflict === 'number') {
            $expectedNumbers = array_values(array_unique($assignedNumbers));
            sort($expectedNumbers);
            sort($numbers);
            expect($numbers)->toBe($expectedNumbers);
        }
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }

        while (DB::connection('queue_test')->transactionLevel() > 0) {
            DB::connection('queue_test')->rollBack();
        }

        DB::purge('queue_test');
        DB::setDefaultConnection($defaultConnection);
        $server->statement("DROP DATABASE `{$schema}`");
        DB::purge('queue_test_server');
    }
})->with(['number', 'patient'])->group('mysql')->skip(fn (): bool => ! filter_var(getenv('QUEUE_MYSQL_TESTS'), FILTER_VALIDATE_BOOLEAN), 'Set QUEUE_MYSQL_TESTS=1 to run against a disposable MySQL schema.');
