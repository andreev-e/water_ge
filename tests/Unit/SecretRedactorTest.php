<?php

namespace Tests\Unit;

use App\Support\SecretRedactor;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class SecretRedactorTest extends TestCase
{
    private const TOKEN = '6113375921:AAEtJ_s4P_SN09CGG8DbM6tc2oPj9bFiBOg';

    public function test_telegram_token_in_url_is_masked(): void
    {
        $text = 'Client error: `POST https://api.telegram.org/bot' . self::TOKEN . '/sendMessage` resulted in a `403 Forbidden`';

        $redacted = SecretRedactor::redact($text);

        $this->assertStringNotContainsString(self::TOKEN, $redacted);
        $this->assertStringContainsString('https://api.telegram.org/bot[redacted]/sendMessage', $redacted);
    }

    public function test_configured_token_is_masked_even_outside_telegram_format(): void
    {
        config(['services.telegram-bot-api.token' => 'custom-secret-value']);

        $this->assertSame('token=[redacted]', SecretRedactor::redact('token=custom-secret-value'));
    }

    public function test_text_without_secrets_is_unchanged(): void
    {
        $text = 'Scheduled command failed with exit code [1]';

        $this->assertSame($text, SecretRedactor::redact($text));
    }

    public function test_exception_in_context_is_masked(): void
    {
        $url = 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage';
        $exception = new ClientException('Client error: `POST ' . $url . '` resulted in a `403 Forbidden` response', new Request('POST', $url), new Response(403));

        $redacted = SecretRedactor::redactValue(['exception' => $exception]);

        $this->assertIsString($redacted['exception']);
        $this->assertStringNotContainsString(self::TOKEN, $redacted['exception']);
    }
}
