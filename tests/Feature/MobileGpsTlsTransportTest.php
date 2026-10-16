<?php

namespace Tests\Feature;

use App\Services\Gps\HexPacketCodec;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MobileGpsTlsTransportTest extends TestCase
{
    public function test_fragmented_tls_frame_is_decrypted_and_returns_safe_validation_fields(): void
    {
        if (! extension_loaded('openssl') || ! function_exists('proc_open')) {
            $this->markTestSkipped('OpenSSL and child processes are required.');
        }
        $directory = sys_get_temp_dir().'/tg1-tls-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $process = null;
        $socket = null;
        try {
            file_put_contents($directory.'/openssl.cnf', "[req]\nprompt=no\ndistinguished_name=dn\nx509_extensions=ext\n[dn]\nCN=127.0.0.1\n[ext]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\nsubjectAltName=IP:127.0.0.1\n");
            $options = ['config' => $directory.'/openssl.cnf', 'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
            $key = openssl_pkey_new($options);
            $this->assertNotFalse($key);
            $request = openssl_csr_new(['commonName' => '127.0.0.1'], $key, $options);
            $this->assertNotFalse($request);
            $certificate = openssl_csr_sign($request, null, $key, 1, $options);
            $this->assertNotFalse($certificate);
            openssl_pkey_export_to_file($key, $directory.'/key.pem', null, $options);
            openssl_x509_export_to_file($certificate, $directory.'/cert.pem');

            $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            $this->assertNotFalse($reservation);
            $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
            fclose($reservation);
            $process = new Process([PHP_BINARY, 'artisan', 'gps:listen', '--host=127.0.0.1',
                '--port='.$port, '--cert='.$directory.'/cert.pem', '--key='.$directory.'/key.pem', '--once'],
                base_path(), ['CACHE_STORE' => 'array', 'APP_ENV' => 'testing']);
            $process->start();
            $deadline = microtime(true) + 10;
            while (! str_contains($process->getOutput(), 'Mobile GPS listener:')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Listener failed: '.$process->getErrorOutput().$process->getOutput());
                }
                usleep(20000);
            }
            $context = stream_context_create(['ssl' => ['verify_peer' => true,
                'verify_peer_name' => true, 'peer_name' => '127.0.0.1',
                'cafile' => $directory.'/cert.pem']]);
            $disconnected = stream_socket_client('tls://127.0.0.1:'.$port, $errno, $error, 5,
                STREAM_CLIENT_CONNECT, $context);
            $this->assertNotFalse($disconnected);
            fclose($disconnected);
            $socket = stream_socket_client('tls://127.0.0.1:'.$port, $errno, $error, 5,
                STREAM_CLIENT_CONNECT, $context);
            $this->assertNotFalse($socket);
            stream_set_timeout($socket, 5);
            $codec = new HexPacketCodec;
            $frame = $codec->encode(1, ['device_key' => 'never-log-this-secret']);
            foreach (str_split($frame, 17) as $fragment) {
                $offset = 0;
                while ($offset < strlen($fragment)) {
                    $written = fwrite($socket, substr($fragment, $offset));
                    $this->assertGreaterThan(0, $written);
                    $offset += $written;
                }
            }
            $response = fgets($socket, HexPacketCodec::MAX_HEX_LENGTH + 3);
            $this->assertNotFalse($response);
            $reply = $codec->decode($response);
            $this->assertSame(0x81, $reply['code']);
            $this->assertSame('invalid_fields', $reply['payload']['reason']);
            $this->assertContains('user_id', $reply['payload']['field_errors']);
            $this->assertContains('imei', $reply['payload']['field_errors']);
            $this->assertStringNotContainsString('never-log-this-secret', $response);
            $this->assertSame(0, $process->wait());
            $this->assertStringContainsString('TLS_ESTABLISHED', $process->getOutput());
            $this->assertStringNotContainsString('invalid_hex', $process->getOutput());
            $this->assertStringNotContainsString('never-log-this-secret', $process->getOutput());
        } finally {
            if (is_resource($socket)) fclose($socket);
            if ($process?->isRunning()) $process->stop();
            foreach (['openssl.cnf', 'key.pem', 'cert.pem'] as $file) {
                if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
            }
            rmdir($directory);
        }
    }
}
