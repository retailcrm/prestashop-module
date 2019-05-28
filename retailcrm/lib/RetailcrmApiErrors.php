<?php

/**
 * Class RetailcrmApiErrors
 * This is storage for all API exceptions.
 */
class RetailcrmApiErrors
{
    /** @var array */
    private static $errors;

    /** @var integer */
    private static $statusCode;

    /**
     * ApiErrors constructor. Isn't accessible.
     */
    private function __construct()
    {
        return false;
    }

    /**
     * Returns status code
     *
     * @return int
     */
    public static function getStatusCode()
    {
        return isset(static::$statusCode) ? static::$statusCode : 0;
    }

    /**
     * Returns static::$errors array, or regenerates it.
     *
     * @return array
     */
    public static function getErrors()
    {
        static::checkArray();
        return static::$errors;
    }

    /**
     * Sets static::$errors array, or regenerates it.
     * Returns true if errors is assigned.
     * Returns false if incorrect data was passed to it.
     *
     * @param array $errors
     * @param integer $statusCode
     *
     * @return bool
     */
    public static function set($errors, $statusCode)
    {
        static::checkArray();

        if (is_array($errors) && is_integer($statusCode)) {
            static::$errors = $errors;
            static::$statusCode = $statusCode;

            return true;
        }

        return false;
    }

    /**
     * Regenerates static::$errors array
     */
    private static function checkArray()
    {
        if (!is_array(static::$errors)) {
            static::$errors = array();
        }
    }
}
