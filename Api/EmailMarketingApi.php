<?php

namespace MauticPlugin\MauticEmailMarketingBundle\Api;

use Mautic\PluginBundle\Integration\UnifiedIntegrationInterface;

class EmailMarketingApi
{
    protected \Mautic\PluginBundle\Integration\UnifiedIntegrationInterface $integration;
    protected $keys;

    public function __construct(UnifiedIntegrationInterface $integration)
    {
        $this->integration = $integration;
        $this->keys        = $integration->getKeys();
    }
}
