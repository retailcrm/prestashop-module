<?php


class RetailcrmInventories
{
    public static $api;
    public static $default_lang;
    public static $apiVersion;
    /**
     * Load stock from retailCRM
     *
     * @return mixed
     */
    public function load_stocks() {
        $page = 1;

        do {
            $result = self::$api->storeInventories(array(), $page, 250);

            if (!$result->isSuccessful()) {
                return null;
            }

            $totalPageCount = $result['pagination']['totalPageCount'];
            $page++;
            foreach ($result['offers'] as $offer) {
                if (isset($offer['externalId'])) {
                    $invOffer = explode('#', $offer['externalId']);
                    
                    if (isset($invOffer[1])) {
                        StockAvailable::setQuantity($invOffer[0], $invOffer[1], $offer['quantity']);
                    } else {
                        StockAvailable::setQuantity($offer['externalId'], 0, $offer['quantity']);
                    }
                }
            }
        } while ($page <= $totalPageCount);

        return $success;
    }

    /**
     * Update stock quantity in WooCommerce
     *
     * @return mixed
     */
    public function updateQuantity() {

        return $this->load_stocks();
        
    }

}
