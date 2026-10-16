<?php

namespace App\Console\Commands;

use App\Services\Gps\GpsDeviceRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterGpsDevice extends Command
{
    protected $signature = 'gps:device-register {user_id} {license_number} {imei} {--rotate}';

    protected $description = 'Provision a user/license/IMEI-bound GPS device without a user login';

    public function handle(GpsDeviceRegistrationService $registration): int
    {
        if (! ctype_digit((string) $this->argument('user_id')) || (int) $this->argument('user_id') < 1) {
            $this->error('user_id must be a positive database user ID.');
            return self::FAILURE;
        }
        try {
            $result = $registration->register((int) $this->argument('user_id'),
                (string) $this->argument('license_number'), (string) $this->argument('imei'),
                (bool) $this->option('rotate'));
        } catch (InvalidArgumentException | ValidationException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        $this->info('GPS device registered. No user login is required. Save the device_key securely; it is shown only once.');
        $this->output->writeln(json_encode([
            'user_id' => (int) $this->argument('user_id'),
            'license_number' => $this->argument('license_number'),
            'imei' => $this->argument('imei'),
            'device_key' => $result['device_key'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
