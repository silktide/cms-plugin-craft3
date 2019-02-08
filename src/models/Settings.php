<?php
/**
 * Silktide Craft plugin for Craft CMS 3.x
 *
 * Integrate Silktide with Craft
 *
 * @link      https://www.silktide.com
 * @copyright Copyright (c) 2019 Silktide Ltd.
 */

namespace silktide\craftsilktide\models;

use craft\base\Model;

/**
 * Silktide Settings Model
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Silktide Ltd
 * @package   craftsilktide
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * API Key
     *
     * @var string
     */
    public $apiKey = '';

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            ['apiKey', 'required', 'message' =>
                \Craft::t(
                    'craft-silktide',
                    'Please provide the API key as presented in Silktide for your site.'
                )
            ],
            ['apiKey', 'trim'],
            ['apiKey', 'string', 'max' => 32],
            ['apiKey', 'validateApiKey']
        ];
    }


    /**
     * Validate the API key.
     *
     * @param string $attribute
     * @param $params
     * @param $validator
     * @return void
     */
    public function validateApiKey(string $attribute, $params, $validator)
    {
        $input = trim($this->$attribute);
        if (preg_match('/^[a-z0-9]{32}$/i', $input)) {
            return;
        }
        $this->addError(
            $attribute,
            \Craft::t(
                'craft-silktide',
                'Sorry, that API key was invalid. It must be a 32 character long code from Silktide.'
            )
        );
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => \Craft::t('craft-silktide', 'The API key provided by Silktide.')
        ];
    }
}
