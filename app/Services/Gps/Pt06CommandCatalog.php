<?php

namespace App\Services\Gps;

use Illuminate\Validation\ValidationException;

class Pt06CommandCatalog
{
    public function entries(): array
    {
        return collect(config('pt06_commands'))->map(fn ($entry, $key) => [
            'key' => $key, 'name' => $entry['name'], 'query' => ! empty($entry['query']) ? $key.'#' : null,
            'action' => $entry['action'] ?? null, 'example' => $entry['example'] ?? null,
            'help' => $entry['help'] ?? 'Alarm method where applicable: 0 GPRS; 1 SMS+GPRS; 2 SMS+call+GPRS; 3 call+GPRS. Firmware support varies.',
        ])->values()->all();
    }

    public function validate(string $command): array
    {
        $command = trim($command);
        if (! preg_match('/^[A-Za-z_]+(?:,[A-Za-z0-9.+_:\/-]*)*#$/D', $command) || strlen($command) > 180) {
            throw ValidationException::withMessages(['command' => 'Use one ASCII command ending in # (maximum 180 bytes).']);
        }
        $parts = explode(',', substr($command, 0, -1));
        $key = strtoupper(array_shift($parts));
        $command = $key.($parts ? ','.implode(',', $parts) : '').'#';
        $entry = config('pt06_commands.'.$key);
        if ($entry) {
            if ((! $parts && ! empty($entry['query'])) || $this->matches($parts, $entry['read_forms'] ?? [])) {
                return ['command' => $command, 'mutating' => false];
            }
            if (($entry['action'] ?? null) === $command || $this->matches($parts, $entry['forms'] ?? [])) {
                return ['command' => $command, 'mutating' => true];
            }
        }
        throw ValidationException::withMessages(['command' => 'Unsupported command or invalid parameters. Use the documented format shown in the catalog.']);
    }

    private function matches(array $values, array $forms): bool
    {
        foreach ($forms as $form) {
            if (count($form) !== count($values)) {
                continue;
            }
            $valid = true;
            foreach ($form as $index => $rule) {
                $bits = explode(':', $rule);
                $value = $values[$index];
                $valid = $valid && match ($bits[0]) {
                    'one' => in_array($value, array_slice($bits, 1), true),
                    'int' => ctype_digit($value) && (int) $value >= (int) $bits[1] && (int) $value <= (int) $bits[2],
                    'phone' => (bool) preg_match('/^\+?[0-9]{1,20}$/D', $value),
                    'optional_phone' => $value === '' || (bool) preg_match('/^\+?[0-9]{1,20}$/D', $value),
                    'ip' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                    'host' => filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false && strlen($value) <= 100,
                    'apn' => (bool) preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $value),
                    default => false,
                };
            }
            if ($valid) {
                return true;
            }
        }

        return false;
    }
}
