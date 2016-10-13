<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace MauticPlugin\MauticEmailMarketingBundle\Integration;

/**
 * Class MailchimpIntegration.
 */
class MailchimpIntegration extends EmailAbstractIntegration
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Mailchimp';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'MailChimp';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationType()
    {
        return (empty($this->keys['client_id'])) ? 'basic' : 'oauth2';
    }

    /**
     * Get the URL required to obtain an oauth2 access token.
     *
     * @return string
     */
    public function getAccessTokenUrl()
    {
        return 'https://login.mailchimp.com/oauth2/token';
    }

    /**
     * Get the authentication/login URL for oauth2 access.
     *
     * @return string
     */
    public function getAuthenticationUrl()
    {
        return 'https://login.mailchimp.com/oauth2/authorize';
    }

    /**
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return (empty($this->keys['client_id'])) ?
            [
                'username' => 'mautic.integration.keyfield.username',
                'password' => 'mautic.integration.keyfield.api',
            ] :
            [
                'client_id'     => 'mautic.integration.keyfield.clientid',
                'client_secret' => 'mautic.integration.keyfield.clientsecret',
            ];
    }

    /**
     * @param array $settings
     * @param array $parameters
     *
     * @return bool|string
     */
    public function authCallback($settings = [], $parameters = [])
    {
        $error = parent::authCallback($settings, $parameters);

        if (empty($error)) {
            // Now post to the metadata URL
            $data = $this->makeRequest('https://login.mailchimp.com/oauth2/metadata');

            return $this->extractAuthKeys($data, 'dc');
        } else {
            return $error;
        }
    }

    /**
     * @param array $settings
     *
     * @return mixed
     */
    public function getAvailableLeadFields($settings = [])
    {
        static $leadFields = [];

        if (empty($leadFields)) {
            if (isset($settings['list'])) {
                // Ajax update
                $listId = $settings['list'];
            } elseif (!empty($settings['feature_settings']['list_settings']['list'])) {
                // Form load
                $listId = $settings['feature_settings']['list_settings']['list'];
            } elseif (!empty($settings['list_settings']['list'])) {
                // Push action
                $listId = $settings['list_settings']['list'];
            }

            if (!empty($listId)) {
                $fields = $this->getApiHelper()->getCustomFields($listId);

                if (!empty($fields['data'][0]['merge_vars']) && count($fields['data'][0]['merge_vars'])) {
                    foreach ($fields['data'][0]['merge_vars'] as $field) {
                        $leadFields[$field['tag']] = [
                            'label'    => $field['name'],
                            'type'     => 'string',
                            'required' => $field['req'],
                        ];
                    }
                }
            }
        }

        return $leadFields;
    }

    /**
     * @param       $lead
     * @param array $config
     *
     * @return bool
     */
    public function pushLead($lead, $config = [])
    {
        $config = $this->mergeConfigToFeatureSettings($config);

        $mappedData = $this->populateLeadData($lead, $config);

        if (empty($mappedData)) {
            return false;
        } elseif (empty($mappedData['EMAIL'])) {
            return false;
        } elseif (!isset($config['list_settings'])) {
            return false;
        }

        try {
            if ($this->isAuthorized()) {
                $email = $mappedData['EMAIL'];
                unset($mappedData['EMAIL']);

                $options                 = [];
                $options['double_optin'] = $config['list_settings']['doubleOptin'];
                $options['send_welcome'] = $config['list_settings']['sendWelcome'];
                $listId                  = $config['list_settings']['list'];

                $this->getApiHelper()->subscribeLead($email, $listId, $mappedData, $options);

                return true;
            }
        } catch (\Exception $e) {
            $this->logIntegrationError($e);
        }

        return false;
    }
}
