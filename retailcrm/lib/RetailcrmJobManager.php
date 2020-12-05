<?php
/**
 * MIT License
 *
 * Copyright (c) 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 * @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
    date_default_timezone_set(@date_default_timezone_get());
}

require_once(dirname(__FILE__) . '/../../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../../init.php');
require_once(dirname(__FILE__) . '/../bootstrap.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class RetailcrmJobManager
 *
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @license   GPL
 * @link      https://retailcrm.ru
 */
class RetailcrmJobManager
{
    const LAST_RUN_NAME = 'RETAILCRM_LAST_RUN';
    const IN_PROGRESS_NAME = 'RETAILCRM_JOBS_IN_PROGRESS';
    const CURRENT_TASK = 'RETAILCRM_JOB_CURRENT';

    /** @var callable|null */
    private static $customShutdownHandler;

    /** @var bool */
    private static $shutdownHandlerRegistered;

    /**
     * Entry point for all jobs.
     * Jobs must be passed in this format:
     *  RetailcrmJobManager::startJobs(
     *      array(
     *          'jobName' => DateInterval::createFromDateString('1 hour')
     *      ),
     *      true
     *  );
     *
     * File `jobName.php` must exist in retailcrm/job and must contain everything to run job.
     * Throwed errors will be logged in <prestashop directory>/retailcrm.log
     * DateInterval must be positive. Pass `null` instead of DateInterval to remove
     * any delay - in other words, jobs without interval will be executed every time.
     *
     * @param array $jobs             Jobs list
     * @param bool  $runOnceInContext Use require_once instead of require
     *
     * @throws \Exception
     */
    public static function startJobs(
        $jobs = array(),
        $runOnceInContext = true
    ) {
        RetailcrmLogger::writeDebug(__METHOD__,'starting JobManager');
        static::execJobs($jobs, $runOnceInContext);
    }

    /**
     * Run scheduled jobs with request
     *
     * @param array $jobs
     * @param bool  $runOnceInContext
     *
     * @throws \Exception
     */
    public static function execJobs($jobs = array(), $runOnceInContext = false)
    {
        $current = date_create('now');
        $lastRuns = array();

        try {
            $lastRuns = static::getLastRuns();
        } catch (Exception $exception) {
            static::handleError(
                $exception->getFile(),
                $exception->getMessage(),
                $exception->getTraceAsString(),
                '',
                $jobs
            );

            return;
        }

        RetailcrmLogger::writeDebug(__METHOD__, 'Trying to acquire lock...');

        if (!static::lock()) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Cannot acquire lock');
            die;
        }

        RetailcrmLogger::writeDebug(
            __METHOD__,
            sprintf('Current time: %s', $current->format(DATE_RFC3339))
        );

        foreach ($lastRuns as $name => $diff) {
            if (!array_key_exists($name, $jobs)) {
                unset($lastRuns[$name]);
            }
        }

        foreach ($jobs as $job => $diff) {
            try {
                if (isset($lastRuns[$job]) && $lastRuns[$job] instanceof DateTime) {
                    $shouldRunAt = clone $lastRuns[$job];

                    if ($diff instanceof DateInterval) {
                        $shouldRunAt->add($diff);
                    }
                } else {
                    $shouldRunAt = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 00:00:00');
                }

                RetailcrmLogger::writeDebug(__METHOD__, sprintf(
                    'Checking %s, interval %s, shouldRunAt: %s: %s',
                    $job,
                    is_null($diff) ? 'NULL' : $diff->format('%R%Y-%m-%d %H:%i:%s:%F'),
                    isset($shouldRunAt) && $shouldRunAt instanceof \DateTime
                        ? $shouldRunAt->format(DATE_RFC3339)
                        : 'undefined',
                    (isset($shouldRunAt) && $shouldRunAt <= $current) ? 'true' : 'false'
                ));

                if (isset($shouldRunAt) && $shouldRunAt <= $current) {
                    RetailcrmLogger::writeDebug(__METHOD__, sprintf('Executing job %s', $job));
                    $result = RetailcrmJobManager::runJob($job, $runOnceInContext);
                    RetailcrmLogger::writeDebug(
                        __METHOD__,
                        sprintf('Executed job %s, result: %s', $job, $result ? 'true' : 'false')
                    );
                    $lastRuns[$job] = new \DateTime('now');
                }
            } catch (\Exception $exception) {
                if ($exception instanceof RetailcrmJobManagerException
                    && $exception->getPrevious() instanceof \Exception
                ) {
                    static::handleError(
                        $exception->getPrevious()->getFile(),
                        $exception->getPrevious()->getMessage(),
                        $exception->getPrevious()->getTraceAsString(),
                        $job
                    );
                } else {
                    static::handleError(
                        $exception->getFile(),
                        $exception->getMessage(),
                        $exception->getTraceAsString(),
                        $job
                    );
                }

                self::clearCurrentJob($job);
            }

            if (isset($result) && $result) {
                self::clearCurrentJob($job);
            }
        }

        try {
            static::setLastRuns($lastRuns);
        } catch (Exception $exception) {
            static::handleError(
                $exception->getFile(),
                $exception->getMessage(),
                $exception->getTraceAsString(),
                '',
                $jobs
            );
        }

        static::unlock();
    }

    /**
     * Extracts jobs last runs from db
     *
     * @return array<string, \DateTime>
     * @throws \Exception
     */
    private static function getLastRuns()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        $lastRuns = json_decode((string)Configuration::get(self::LAST_RUN_NAME), true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $lastRuns = array();
        } else {
            foreach ($lastRuns as $job => $ran) {
                $lastRan = DateTime::createFromFormat(DATE_RFC3339, $ran);

                if ($lastRan instanceof DateTime) {
                    $lastRuns[$job] = $lastRan;
                } else {
                    $lastRuns[$job] = new DateTime();
                }
            }
        }

        return (array)$lastRuns;
    }

    /**
     * Updates jobs last runs in db
     *
     * @param array $lastRuns
     *
     * @throws \Exception
     */
    private static function setLastRuns($lastRuns = array())
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        $now = new DateTime();

        if (!is_array($lastRuns)) {
            $lastRuns = array();
        }

        foreach ($lastRuns as $job => $ran) {
            if ($ran instanceof DateTime) {
                $lastRuns[$job] = $ran->format(DATE_RFC3339);
            } else {
                $lastRuns[$job] = $now->format(DATE_RFC3339);
            }

            RetailcrmLogger::writeDebug(
                __METHOD__,
                sprintf('Saving last run for %s as %s', $job, $lastRuns[$job])
            );
        }

        Configuration::updateValue(self::LAST_RUN_NAME, (string)json_encode($lastRuns));
    }

    /**
     * Runs job
     *
     * @param string $job
     * @param bool   $once
     * @param bool   $cliMode
     *
     * @return bool
     * @throws \RetailcrmJobManagerException
     */
    public static function runJob($job, $once = false, $cliMode = false, $shopId = null)
    {
        $jobName = self::escapeJobName($job);
        $jobFile = implode(
            DIRECTORY_SEPARATOR,
            array(_PS_ROOT_DIR_, 'modules', 'retailcrm', 'lib', 'events', $jobName . '.php')
        );

        if (!file_exists($jobFile)) {
            throw new \RetailcrmJobManagerException('Cannot find job', $job);
        }

        try {
            return static::execHere($jobName, $jobFile, $once, $cliMode, $shopId);
        } catch (\RetailcrmJobManagerException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new RetailcrmJobManagerException($exception->getMessage(), $jobFile, array(), 0, $exception);
        }
    }

    /**
     * Serializes jobs to JSON
     *
     * @param $jobs
     *
     * @return string
     */
    public static function serializeJobs($jobs)
    {
        foreach ($jobs as $name => $interval) {
            $jobs[$name] = serialize($interval);
        }

        return (string)base64_encode(json_encode($jobs));
    }

    /**
     * Sets current running job. Every job must call this in order to work properly.
     * Current running job will be cleared automatically after job was finished (or crashed).
     * That way, JobManager will maintain it's data integrity and will coexist with manual runs and cron.
     *
     * @param string $job
     *
     * @return bool
     */
    public static function setCurrentJob($job)
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        return (bool)Configuration::updateValue(self::CURRENT_TASK, $job);
    }

    /**
     * Returns current job or empty string if there's no jobs running at this moment
     *
     * @return string
     */
    public static function getCurrentJob()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        return (string)Configuration::get(self::CURRENT_TASK);
    }

    /**
     * Clears current job (job name must be provided to ensure we're removed correct job).
     *
     * @param string|null $job
     *
     * @return bool
     */
    public static function clearCurrentJob($job)
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        if (is_null($job) || self::getCurrentJob() == $job) {
            return Configuration::deleteByName(self::CURRENT_TASK);
        }

        return true;
    }

    /**
     * Resets JobManager internal state. Doesn't work if JobManager is active.
     *
     * @return bool
     * @throws \Exception
     */
    public static function reset()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        $result = Configuration::deleteByName(self::CURRENT_TASK);
        $result = $result && Configuration::deleteByName(self::LAST_RUN_NAME);

        self::unlock();

        return $result;
    }

    /**
     * Sets custom shutdown handler, it will be called before calling default shutdown handler.
     *
     * @param callable $shutdownHandler
     */
    public static function setCustomShutdownHandler($shutdownHandler)
    {
        if (is_callable($shutdownHandler)) {
            self::$customShutdownHandler = $shutdownHandler;
        }
    }

    /**
     * Wrapper for shutdown handler. Moved here in order to keep compatibility with older PHP versions.
     */
    public static function shutdownHandlerWrapper()
    {
        $error = error_get_last();

        if(null !== $error && $error['type'] === E_ERROR) {
            self::defaultShutdownHandler($error);
        }
    }

    /**
     * Register default shutdown handler (should be be called before any job execution)
     */
    private static function registerShutdownHandler()
    {
        if (!self::$shutdownHandlerRegistered) {
            register_shutdown_function(array('RetailcrmJobManager', 'shutdownHandlerWrapper'));
            self::$shutdownHandlerRegistered = true;
        }
    }

    /**
     * Default handler for shutdown function
     *
     * @param array $error
     */
    private static function defaultShutdownHandler($error)
    {
        if (is_callable(self::$customShutdownHandler)) {
            call_user_func_array(self::$customShutdownHandler, array($error));
        }

        RetailcrmLogger::writeCaller(
            __METHOD__,
            'Warning: something disrupted correct process execution. All information will be provided here.'
        );
        RetailcrmLogger::writeCaller(__METHOD__, print_r($error, true));
        self::unlock();
        exit(1);
    }

    /**
     * Writes error to log and returns 500
     *
     * @param string $file
     * @param string $msg
     * @param string $trace
     * @param string $currentJob
     * @param array  $jobs
     */
    private static function handleError($file, $msg, $trace, $currentJob = '', $jobs = array())
    {
        $data = array();

        if (!empty($currentJob)) {
            $data[] = 'current job: ' . $currentJob;
        }

        if (count($jobs) > 0) {
            $data[] = 'jobs list: ' . self::serializeJobs($jobs);
        }

        RetailcrmLogger::writeNoCaller(sprintf('%s: %s (%s)', $file, $msg, implode(', ', $data)));
        RetailcrmLogger::writeNoCaller($trace);

        if (PHP_SAPI != 'cli' && !headers_sent()) {
            RetailcrmTools::http_response_code(500);
        }
    }

    /**
     * Executes job without hanging up request (if executed by a hit).
     * Returns execution result from job.
     *
     * @param string $jobName
     * @param string $phpScript
     * @param bool   $once
     * @param bool   $cliMode
     *
     * @return bool
     * @throws \RetailcrmJobManagerException
     */
    private static function execHere($jobName, $phpScript, $once = false, $cliMode = false, $shopId = null)
    {
        set_time_limit(static::getTimeLimit());

        if (!$cliMode) {
            ignore_user_abort(true);

            if (version_compare(phpversion(), '7.0.16', '>=') &&
                function_exists('fastcgi_finish_request')
            ) {
                if (!headers_sent()) {
                    header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                }

                fastcgi_finish_request();
            }
        }

        if ($once) {
            require_once($phpScript);
        } else {
            require($phpScript);
        }

        if (!class_exists($jobName)) {
            throw new \RetailcrmJobManagerException(sprintf(
                'The job file "%s" has been found, but job class "%s" was not present in there.',
                $phpScript,
                $jobName
            ));
        }

        $job = new $jobName();

        if (!($job instanceof RetailcrmEventInterface)) {
            throw new \RetailcrmJobManagerException(sprintf(
                'Class "%s" must implement RetailcrmEventInterface',
                $jobName
            ));
        }

        $job->setCliMode($cliMode);
        $job->setShopId($shopId);

        self::registerShutdownHandler();

        return $job->execute();
    }

    /**
     * Returns script execution time limit
     *
     * @return int
     */
    private static function getTimeLimit()
    {
        return 14400;
    }

    /**
     * Removes disallowed symbols from job name. Only latin characters, numbers and underscore allowed.
     *
     * @param string $job
     *
     * @return string
     */
    private static function escapeJobName($job)
    {
        return (string) preg_replace('/[^[a-zA-Z0-9_]]*/m', '', $job);
    }

    /**
     * Returns when JobManager was executed
     *
     * @throws \Exception
     */
    private static function getLastRun()
    {
        $lastRuns = array_values(static::getLastRuns());

        if (empty($lastRuns)) {
            return \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 00:00:00');
        }

        usort(
            $lastRuns,
            function ($first, $second) {
                if ($first < $second) {
                    return 1;
                } else if ($first > $second) {
                    return -1;
                } else {
                    return 0;
                }
            }
        );

        return $lastRuns[count($lastRuns) - 1];
    }

    /**
     * Returns true if lock is present and it's not expired
     *
     * @return bool
     * @throws \Exception
     */
    private static function isLocked()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        $inProcess = (bool)Configuration::get(self::IN_PROGRESS_NAME);
        $lastRan = static::getLastRun();
        $lastRanSeconds = $lastRan->format('U');

        if (($lastRanSeconds + self::getTimeLimit()) < time()) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Removing lock because time limit exceeded.');
            static::unlock();

            return false;
        }

        return $inProcess;
    }

    /**
     * Installs lock
     *
     * @return bool
     * @throws \Exception
     */
    private static function lock()
    {
        if (!static::isLocked()) {
            RetailcrmLogger::writeDebug(__METHOD__, 'Acquiring lock...');
            Configuration::updateValue(self::IN_PROGRESS_NAME, true);
            RetailcrmLogger::writeDebug(__METHOD__, 'Lock acquired.');

            return true;
        }

        return false;
    }

    /**
     * Removes lock
     *
     * @return bool
     */
    private static function unlock()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        RetailcrmLogger::writeDebug(__METHOD__, 'Removing lock...');
        Configuration::updateValue(self::IN_PROGRESS_NAME, false);
        RetailcrmLogger::writeDebug(__METHOD__, 'Lock removed.');

        return false;
    }
}
