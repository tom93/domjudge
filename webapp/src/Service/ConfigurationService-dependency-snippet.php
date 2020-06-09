    // backport: use a shim for the ConfigurationService class (added in DOMjudge 7.3)
    protected $config;
    /** @required */
    public function setConfig(\App\Service\ConfigurationService $config)
    {
        $this->config = $config;
    }

