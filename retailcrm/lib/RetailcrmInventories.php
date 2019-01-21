<?php

class RetailcrmInventories
{
    public static $api;

    /**
     * Load stock from retailCRM
     *
     * @return mixed
     */
    public static function loadStocks()
    {
        $page = 1;

        do {
            $result = self::$api->storeInventories(array(), $page, 250);

            if ($result === false) {
                return $result;
            }

            foreach ($result['offers'] as $offer) {
                self::setQuantityOffer($offer);
            }

            $totalPageCount = $result['pagination']['totalPageCount'];
            $page++;
        } while ($page <= $totalPageCount);
    }

    private static function setQuantityOffer($offer) 
    {
        if (isset($offer['externalId'])) {
            $invOffer = explode('#', $offer['externalId']);

            if (isset($invOffer[1])) {
                StockAvailable::setQuantity($invOffer[0], $invOffer[1], $offer['quantity']);
            } else {
                StockAvailable::setQuantity($offer['externalId'], 0, $offer['quantity']);
            }
        }
    }
}
