<?php
declare(strict_types=1);
/**
 * Silktide Craft plugin for Craft CMS 3.x
 *
 * Integrate Silktide with Craft
 *
 * @link      https://www.silktide.com
 * @copyright Copyright (c) 2019 Silktide Ltd
 */

namespace silktide\craftsilktide;

use Craft;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\TemplateEvent;
use craft\web\View;
use silktide\craftsilktide\models\Settings;
use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves.
 * We’ve made it as simple as we can, but the training wheels are off. A little
 * prior knowledge is going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP
 * and SQL, as well as some semi-advanced concepts like object-oriented
 * programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    silktide
 * @package   craftsilktide
 * @since     1.0.0
 *
 */
class CraftSilktide extends Plugin
{

    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can
     * be accessed via CraftSilktide::$plugin
     *
     * @var craftSilktide
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema
     * version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.2';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed
     * via craftsilktide::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time
     * initialization here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you
     * automatically; you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (!$this->isInstalled) {
            return;
        }

        if (!$this->isInstalled) {
            return;
        }
        // check if we've been configured, and if we haven't then return.
        $apiKey = $this->getSettings()->apiKey;
        if ($apiKey === '') {
            Craft::info(
              Craft::t(
                'craft-silktide',
                '{name} plugin loaded, but not configured',
                ['name' => $this->name]
              ),
              __METHOD__
            );
            return;
        }

        $this->initSaveEntry($apiKey);

        $this->initMetaTag($apiKey);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Setup our save event.
     *
     * @param string $apiKey
     */
    protected function initSaveEntry(string $apiKey)
    {
        Event::on(Entry::class, Entry::EVENT_AFTER_SAVE,
          function (Event $e) use ($apiKey) {
              /* @var Entry $sender */
              $sender = $e->sender;
              if ($sender->status === Entry::STATUS_LIVE) {
                  /**
                   * If we have an entry which has been saved and is 'live', send notification to silktide.
                   */
                  Craft::$app->getQueue()->push(new CraftSilktideJob([
                    'apiKey' => $apiKey,
                    'urls' => [$sender->url],
                    'userAgent' => 'SilktideCraft/' .
                      $this->getVersion() .
                      ' (compatible; CraftCMS ' .
                      Craft::$app->getVersion() .
                      ')',
                  ]));
              }
          });
    }

    /**
     * Setup the meta tag event.
     *
     * @param string $apiKey
     */
    protected function initMetaTag(string $apiKey)
    {
        Event::on(View::class, View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
          function (TemplateEvent $e) use ($apiKey) {
              // make sure we are public view only
              if (Craft::$app->request->getIsSiteRequest() &&
                !Craft::$app->request->getIsCpRequest() &&
                !Craft::$app->request->getIsConsoleRequest() &&
                !(Craft::$app->request->hasMethod('getIsAjax') && Craft::$app->request->getIsAjax()) &&
                !(Craft::$app->request->hasMethod('getIsLivePreview') && Craft::$app->request->getIsLivePreview())
              ) {
                  // get current page element
                  $element = Craft::$app->urlManager->getMatchedElement();
                  if (!empty($element)) {
                      $editLink = $element->getCpEditUrl();
                      if ($editLink !== null) {
                          $this->createMetaTag($apiKey, $editLink);
                      }
                  }
              }

          });
    }

    /**
     * Created the encrypted meta tag.
     *
     * @param string $apiKey
     * @param string $editLink
     */
    protected function createMetaTag(string $apiKey, string $editLink)
    {
        $ivlen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivlen);
        if ($iv === false) {
            return;
        }
        $ciphertext_raw = openssl_encrypt(
          json_encode(
            [
              'editorUrl' => $editLink,
            ]
          ),
          'AES-256-CBC',
          $apiKey,
          OPENSSL_RAW_DATA,
          $iv
        );

        $hmac = hash_hmac('sha256', $ciphertext_raw, $apiKey, true);
        $ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);
        Craft::$app->getView()->registerMetaTag(
          [
            'name' => 'silktide-cms',
            'content' => htmlspecialchars($ciphertext, ENT_QUOTES),
          ]
        );
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the
     * content block on the settings page.
     *
     * @return string The rendered settings HTML
     *
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
          'craft-silktide/_settings',
          [
            'settings' => $this->getSettings(),
          ]
        );
    }


}
