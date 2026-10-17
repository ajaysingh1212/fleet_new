<?php

namespace App\Services\Gps\Gateway;

/** Metadata from an already length/checksum-validated diagnostic frame. */
final class Gt06DiagnosticDetails
{
    public function packet(string $frame): array
    {
        $extended = substr($frame, 0, 2) === "\x79\x79";
        $offset = $extended ? 4 : 3;
        $type = sprintf('%02X', ord($frame[$offset]));

        return ['frame' => $extended ? '7979 (extended)' : '7878 (short)', 'type' => '0x'.$type,
            'packet_name' => match ($type) {
                '01' => 'Login', '12' => 'Legacy GT06 GPS/LBS location', '22' => 'PT06 GPS/LBS location', '13' => 'Heartbeat/status',
                '26' => 'PT06 alarm', '24' => 'LBS measurements', '21' => 'Command response', '2A' => 'Address request',
                '27' => 'Timezone (layout not supplied)', '2C' => 'Wi-Fi (layout not supplied)', '94' => 'General information', default => 'Unknown / unsupported variant'
            },
            'bytes' => strlen($frame), 'declared_length' => $extended ? unpack('n', substr($frame, 2, 2))[1] : ord($frame[2]),
            'serial' => unpack('n', substr($frame, -6, 2))[1], 'stop_bytes' => '0D0A',
            'crc' => strtoupper(bin2hex(substr($frame, -4, 2))), 'checksum' => 'valid',
            'payload_hex' => strtoupper(bin2hex(substr($frame, $offset + 1, -6))),
            'raw_hex' => strtoupper(bin2hex(substr($frame, 0, 256))),
            'raw_truncated' => strlen($frame) > 256];
    }

    public function simInformation(string $frame): ?array
    {
        if (substr($frame, 0, 2) !== "\x79\x79" || ord($frame[4]) !== 0x94) {
            return null;
        }
        $payload = substr($frame, 5, -6);
        // PT06 V02 / S20 V2.1 section 5.6: subtype 0A + IMEI(8),
        // IMSI(8), ICCID(10), optionally door/air-con(1). Never infer padding.
        if (! in_array(strlen($payload), [27, 28], true) || ord($payload[0]) !== 0x0A) {
            return null;
        }
        $imei = strtoupper(bin2hex(substr($payload, 1, 8)));
        if (! preg_match('/^0[0-9]{15}$/D', $imei)) {
            return null;
        }
        $data = ['subtype' => '0x0A (device/SIM identifiers)', 'reported_imei' => substr($imei, 1),
            'imsi_encoded' => strtoupper(bin2hex(substr($payload, 9, 8))),
            'iccid_encoded' => strtoupper(bin2hex(substr($payload, 17, 10))),
            'coordinates' => null, 'note' => 'Non-location information. Encoded identifiers preserved; no padding or coordinate values guessed.'];
        if (strlen($payload) === 28) {
            $status = ord($payload[27]);
            $data += ['door_open' => (bool) ($status & 2), 'air_conditioning_on' => (bool) ($status & 1), 'status_byte_hex' => sprintf('%02X', $status)];
        }

        return $data;
    }
}
