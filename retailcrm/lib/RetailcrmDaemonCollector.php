<?php

class RetailcrmDaemonCollector
{
    private $customer, $siteKey, $js;

    private $template = <<<EOT
<script type="text/javascript">
    (function(_,r,e,t,a,i,l){_['retailCRMObject']=a;_[a]=_[a]||function(){(_[a].q=_[a].q||[]).push(arguments)};_[a].l=1*new Date();l=r.getElementsByTagName(e)[0];i=r.createElement(e);i.async=!0;i.src=t;l.parentNode.insertBefore(i,l)})(window,document,'script','https://collector.retailcrm.pro/w.js','_rc');
    {{ code }}
    _rc('send', 'pageView');
</script>
EOT;

    public function __construct($customer, $siteKey)
    {
        $this->customer = $customer;
        $this->siteKey = $siteKey;
    }

    /**
     * @return string
     */
    public function getJs()
    {
        return $this->js;
    }

    /**
     * @return $this
     */
    public function buildScript()
    {
        $params = array();

        if ($this->customer->id) {
            $params['customerId'] = $this->customer->id;
        }

        $this->js = preg_replace(
            '/{{ code }}/',
            sprintf(
                "\t_rc('create', '%s', %s);\n",
                $this->siteKey,
                json_encode((object) $params)
            ),
            $this->template
        );

        return $this;
    }
}
