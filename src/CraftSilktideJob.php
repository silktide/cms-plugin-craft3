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
use craft\helpers\FileHelper;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CraftSilktideJob
 *
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
              'urls' => implode(', ', $this->urls),
            ]
          ),
          __METHOD__
        );
        $requestBody = http_build_query([
          'apiKey' => $this->apiKey,
          'urls' => $this->urls,
        ]);
        $options = [RequestOptions::BODY => $requestBody];
        $client = Craft::createGuzzleClient(
          [
            'headers' => [
              'User-Agent' => $this->userAgent,
              'Content-Type' => 'application/x-www-form-urlencoded',
            ],
          ]
        );
        try {
            $response = $client->request('post', self::SILKTIDE_API_URL,
              $options);
            $this->handleValidResponse($response, $requestBody);
        } catch (RequestException $e) {
            $message = Craft::t(
              'craft-silktide',
              'Failed to notify Silktide - exception type {exception} message: {message} with request body {request}',
              [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'request' => $requestBody,
              ]
            );
            Craft::warning($message, __METHOD__);
            $file = Craft::getAlias('@storage/logs/silktide.log');
            $log = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
            FileHelper::writeToFile($file, $log, ['append' => true]);
        }
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $requestBody
     *
     * @throws \yii\base\ErrorException
     */
    protected function handleValidResponse(
      ResponseInterface $response,
      string $requestBody
    ) {
        $responseCode = (int)$response->getStatusCode();
        $body = $response->getBody()->getContents();
        $failReason = 'Unknown';
        if ($responseCode < 200 && $responseCode > 299) {
            $failReason = 'Status code';
        } else {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                $failReason = 'Unable to decode';
            } elseif (!isset($decoded['status'])) {
                $failReason = 'No status field';
            } elseif ($decoded['status'] !== 'ok') {
                $failReason = 'Status not ok';
            } else {
                $message = Craft::t(
                  'craft-silktide',
                  'Notified Silktide and received a {status} code back with {body} from {request}',
                  [
                    'status' => $responseCode,
                    'body' => $body,
                    'request' => $requestBody,
                  ]
                );
                Craft::info($message, __METHOD__);
                $file = Craft::getAlias('@storage/logs/silktide.log');
                $log = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
                FileHelper::writeToFile($file, $log, ['append' => true]);
                return;
            }
        }
        $message = Craft::t(
          'craft-silktide',
          'Failed to notify Silktide - HTTP request failed: ({reason}) with status {status} and body "{body}" from {request}',
          [
            'reason' => $failReason,
            'status' => $responseCode,
            'body' => $body,
            'request' => $requestBody,
          ]
        );
        Craft::warning($message, __METHOD__);
        $file = Craft::getAlias('@storage/logs/silktide.log');
        $log = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
        FileHelper::writeToFile($file, $log, ['append' => true]);
    }

}
