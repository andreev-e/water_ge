<?php

namespace App\Services\Translation;

use App\Traits\ChecksConfig;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class LingvaNexTranslationService implements TranslationInterface
{
    use ChecksConfig;

    private Client $client;
    private string $token;

    public function __construct(array $config)
    {
        $this->checkConfig(['token', 'uri'], $config);

        $this->client = new Client([
            'base_uri' => $config['uri'],
        ]);

        $this->token = $config['token'];
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function translate(string $string, string $from, string $to): string
    {
        $result = $this->client->post('translate', [
            RequestOptions::JSON => [
                'platform' => 'api',
                'from' => $from,
                'to' => $to,
                'data' => $string,
            ],
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'authorization' => $this->token,
            ],
        ])->getBody()->getContents();
        $result = json_decode($result);

        return $result->result;
    }
}
