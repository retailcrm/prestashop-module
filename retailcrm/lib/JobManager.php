<?php
/**
 * @author Retail Driver LCC
 * @copyright RetailCRM
 * @license GPL
 * @version 2.2.11
 * @link https://retailcrm.ru
 *
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
 * Class JobManager
 *
 * @author  Retail Driver LCC
 * @license GPL
 * @link https://retailcrm.ru
 */
class JobManager
{
    const LAST_RUN_NAME = 'RETAILCRM_LAST_RUN';
    const IN_PROGRESS_NAME = 'RETAILCRM_JOBS_IN_PROGRESS';

    /**
     * @var resource $lock
     */
    static $lock;

    /**
     * Entry point for all jobs.
     * Jobs must be passed in this format:
     *  JobManager::startJobs(
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
     * @param array $jobs
     * @param bool  $runOnceInContext
     *
     * @throws \Exception
     */
    public static function startJobs($jobs = array(), $runOnceInContext = false)
    {
        $inBackground = static::canExecInBackground();

        if ($inBackground) {
            $cmdline = sprintf('%s "%s" %b', __FILE__, static::serializeJobs($jobs), $runOnceInContext);
            static::execPHP($cmdline, true, $runOnceInContext);
        } else {
            static::execJobs($jobs, $runOnceInContext);
        }
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
        $lastRuns = static::getLastRuns();

        if (!static::lock()) {
            return;
        }

        foreach ($jobs as $job => $diff) {
            try {
                if (isset($lastRuns[$job]) && $lastRuns[$job] instanceof DateTime) {
                    $shouldRunAt = clone $lastRuns[$job];
                } else {
                    $shouldRunAt = new DateTime();
                }

                if ($diff instanceof DateInterval) {
                    $shouldRunAt->add($diff);
                }

                if (!isset($shouldRunAt) || $shouldRunAt <= $current) {
                    JobManager::runJob($job, $runOnceInContext);
                    $lastRuns[$job] = new DateTime();
                }
            } catch (\Exception $exception) {
                static::handleError($exception->getFile(), $exception->getMessage());
            } catch (\Throwable $throwable) {
                static::handleError($throwable->getFile(), $throwable->getMessage());
            }
        }

        static::setLastRuns($lastRuns);
        static::unlock();
    }

    /**
     * Extracts jobs last runs from db
     *
     * @return array
     * @throws \Exception
     */
    private static function getLastRuns()
    {
        $lastRuns = json_decode((string) Configuration::get(self::LAST_RUN_NAME), true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $lastRuns = [];
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

        return (array) $lastRuns;
    }

    /**
     * Updates jobs last runs in db
     *
     * @param array $lastRuns
     *
     * @throws \Exception
     */
    private static function setLastRuns($lastRuns = [])
    {
        $now = new DateTime();

        if (!is_array($lastRuns)) {
            $lastRuns = [];
        }

        foreach ($lastRuns as $job => $ran) {
            if ($ran instanceof DateTime) {
                $lastRuns[$job] = $ran->format(DATE_RFC3339);
            } else {
                $lastRuns[$job] = $now->format(DATE_RFC3339);
            }
        }

        Configuration::updateValue(self::LAST_RUN_NAME, (string) json_encode($lastRuns));
    }

    /**
     * Runs job
     *
     * @param string $job
     * @param bool   $once
     */
    public static function runJob($job, $once = false)
    {
        $jobFile = implode(DIRECTORY_SEPARATOR, array(_PS_ROOT_DIR_, 'modules', 'retailcrm', 'job', $job . '.php'));

        if (!file_exists($jobFile)) {
            throw new \InvalidArgumentException('Cannot find job');
        }

        static::execPHP($jobFile, false, $once);
    }

    /**
     * Runs PHP file
     *
     * @param      $fileCommandLine
     * @param bool $fork
     * @param bool $once
     */
    private static function execPHP($fileCommandLine, $fork = true, $once = false)
    {
        if ($fork) {
            static::execInBackground(sprintf('%s %s', static::getPhpBinary(), $fileCommandLine));
        } else {
            static::execHere($fileCommandLine, $once);
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

        return (string) base64_encode(json_encode($jobs));
    }

    /**
     * Unserializes jobs
     *
     * @param $jobsJson
     *
     * @return array
     */
    public static function deserializeJobs($jobsJson)
    {
        $jobs = json_decode(base64_decode($jobsJson), true);

        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(sprintf('Invalid JSON: %s', json_last_error_msg()));
        }

        if (!is_array($jobs) || count($jobs) == 0) {
            throw new \InvalidArgumentException('Empty or invalid data');
        }

        foreach ($jobs as $name => $interval) {
            if (!is_string($name) || !is_string($interval)) {
                throw new \InvalidArgumentException('Invalid job in array');
            }

            $intervalObj = unserialize($interval);

            if (!($intervalObj instanceof DateInterval)) {
                throw new \InvalidArgumentException('Invalid job interval in array');
            }

            $jobs[$name] = $intervalObj;
        }

        return (array) $jobs;
    }

    /**
     * Writes error to log and returns 500
     *
     * @param $file
     * @param $msg
     */
    private static function handleError($file, $msg)
    {
        error_log(sprintf('%s: %s', $file, $msg), 3, _PS_ROOT_DIR_ . '/retailcrm.log');
        http_response_code(500);
    }

    /**
     * Run process in background without waiting
     *
     * @param $cmd
     *
     * @return void
     */
    private static function execInBackground($cmd) {
        if (substr(php_uname(), 0, 7) == "Windows"){
            pclose(popen("start /B ". $cmd, "r"));
        } else {
            $outputPos = strpos($cmd, '>');

            if ($outputPos !== false) {
                $cmd = substr($cmd, 0, $outputPos);
            }

            $command = $cmd . " > /dev/null &";

            if (function_exists('exec')) {
                exec($command);
            } else if (function_exists('shell_exec')) {
                shell_exec($command);
            } else if (function_exists('passthru')) {
                passthru($command);
            }
        }
    }

    /**
     * Executes php script in this context, without hanging up request
     *
     * @param string $phpScript
     * @param bool   $once
     */
    private static function execHere($phpScript, $once = false)
    {
        ignore_user_abort( true);
        set_time_limit(static::getTimeLimit());

        if (version_compare(phpversion(), '7.0.16', '>=') &&
            function_exists('fastcgi_finish_request')
        ) {
            if (!headers_sent()) {
                header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate');
            }

            fastcgi_finish_request();
        }

        if ($once) {
            require_once($phpScript);
        } else {
            require($phpScript);
        }
    }

    /**
     * Returns true if system support execution in background
     *
     * @return bool
     */
    private static function canExecInBackground()
    {
        if (substr(php_uname(), 0, 7) == "Windows"){
            return function_exists('pclose') && function_exists('popen');
        } else {
            return function_exists('exec')
                || function_exists('shell_exec')
                || function_exists('passthru');
        }
    }

    /**
     * Returns path to current PHP binary
     *
     * @return string
     */
    private static function getPhpBinary()
    {
        if (defined('PHP_BINARY') && !empty(PHP_BINARY)) {
            return PHP_BINARY;
        }

        if (defined('PHP_BINDIR') && !empty(PHP_BINDIR)) {
            $version = phpversion();
            $filePath = implode(DIRECTORY_SEPARATOR, array(PHP_BINDIR, 'php' . $version));

            while (strlen($version) != 0 && !file_exists($filePath)) {
                $dotPos = strrpos($version, '.');

                if ($dotPos !== false) {
                    $version = substr($version, 0, strrpos($version, '.'));
                } else {
                    $version = '';
                }

                $filePath = implode(DIRECTORY_SEPARATOR, array(PHP_BINDIR, 'php' . $version));
            }

            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        return 'php';
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
     * Returns true if lock is present and it's not expired
     *
     * @return bool
     */
    private static function isLocked()
    {
        $inProcess = (bool) Configuration::get(self::IN_PROGRESS_NAME);
        $lastRan = date_create_from_format(DATE_RFC3339, (string) Configuration::get(self::LAST_RUN_NAME));
        $lastRanSeconds = $lastRan instanceof DateTime ? $lastRan->format('U') : time();

        if (($lastRanSeconds + self::getTimeLimit()) < time()) {
            static::unlock();

            return false;
        }

        return $inProcess;
    }

    /**
     * Installs lock
     *
     * @return bool
     */
    private static function lock()
    {
        if (!static::isLocked()) {
            Configuration::updateValue(self::IN_PROGRESS_NAME, true);

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
        Configuration::updateValue(self::IN_PROGRESS_NAME, false);

        return false;
    }
}

if (PHP_SAPI == 'cli' && $argc == 3) {
    try {
        $jobs = JobManager::deserializeJobs($argv[1]);
        $runOnce = (bool) $argv[2];
    } catch (InvalidArgumentException $exception) {
        printf('Error: %s%s', $exception->getMessage(), PHP_EOL);
        exit(0);
    }

    JobManager::execJobs($jobs, $runOnce);
}