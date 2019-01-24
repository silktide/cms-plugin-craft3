<?php
/**
 * Silktide Craft plugin for Craft CMS 3.x
 *
 * Integrate Silktide with Craft
 *
 * @link      https://www.silktide.com
 * @copyright Copyright (c) 2019 silktide
 */

namespace silktide\craftsilktide;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CraftSilktideJob
 * @package silktide\craftsilktide
 */
class CraftSilktideJob extends BaseJob
{
    const SILKTIDE_API_URL = 'https://api.silktide.com/cms/update';

    /**
     * @var string The API key we are using.
     */
    public $apiKey;

    /**
     * @var array List of URLs to notify.
     */
    public $urls;

    /**
     * @var string User agent.
     */
    public $userAgent;

    /**
     * @inheritdoc
     */
    public function defaultDescription(): string
    {
        return Craft::t('craft-silktide', 'Notifying Silktide');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        Craft::info(
            Craft::t(
                'craft-silktide',
                'Notifying Silktide about a change to URLs {urls}',
                [
                    'urls' => implode($this->urls, ', ')
                ]
            ),
            __METHOD__
        );
        $options = [
            RequestOptions::BODY => http_build_query([
                'apiKey' => $this->apiKey,
                'urls' => [
                    $this->urls
                ]
            ])
        ];
        $client = Craft::createGuzzleClient(
            [
                'headers' => [
                    'User-Agent' => $this->userAgent
                ]
            ]
        );

        try {
            $response = $client->request('post', self::SILKTIDE_API_URL, $options);
            $this->handleValidResponse($response);
        } catch (RequestException $e) {
            $message = Craft::t(
                'craft-silktide',
                'Failed to notify Silktide - exception type {exception} message: {message}',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ]
            );
            Craft::warning($message, __METHOD__);
        }
    }

    /**
     * @param ResponseInterface $response
     */
    protected function handleValidResponse(ResponseInterface $response)
    {
        $responseCode = (int)$response->getStatusCode();
        $body = $response->getBody()->getContents();
        if ($responseCode >= 200 && $responseCode <= 299) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === 'ok') {
                Craft::info(
                    Craft::t(
                        'craft-silktide',
                        'Notified Silktide and received a {status} code back with {body}',
                        ['status' => $responseCode,
                            'body' => $body
                        ]
                    ),
                    __METHOD__
                );
            }
        }
        $message = Craft::t(
            'craft-silktide',
            'Failed to notify Silktide - HTTP request failed with status {status} and body {body}',
            [
                'status' => $responseCode,
                'body' => $body
            ]
        );
        Craft::warning($message, __METHOD__);
    }

}
