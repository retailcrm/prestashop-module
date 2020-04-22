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
 * Class RetailcrmCli
 *
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @license   GPL
 * @link      https://retailcrm.ru
 */
class RetailcrmCli
{
    const CURRENT_TASK_CLI = 'RETAILCRM_JOB_CURRENT_CLI';

    /** @var string CLI path */
    private $cliPath;

    /**
     * RetailcrmCli constructor.
     *
     * @param string $cliPath
     */
    public function __construct($cliPath)
    {
        RetailcrmLogger::setCloneToStdout(true);
        $this->cliPath = $cliPath;
    }

    /**
     * Run cli routine. Callable can be passed which will be used to handle terminate signals.
     *
     * @param callable|int|null $signalsHandler
     */
    public function execute($signalsHandler = null)
    {
        if (function_exists('pcntl_signal')) {
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if (!empty($signalsHandler) && (is_callable($signalsHandler) || function_exists($signalsHandler))) {
                pcntl_signal(SIGINT, $signalsHandler);
                pcntl_signal(SIGTERM, $signalsHandler);
                pcntl_signal(SIGHUP, $signalsHandler);
            }
        } else {
            RetailcrmLogger::output('WARNING: cannot handle signals properly, force stop can cause problems!');
        }

        $shortopts = "j:";
        $longopts  = array("job:", "reset-job-manager");
        $options = getopt($shortopts, $longopts);
        $jobName = isset($options['j']) ? $options['j'] : (isset($options['job']) ? $options['job'] : null);

        if (isset($options['reset-job-manager'])) {
            $this->resetJobManager();
        } elseif (empty($jobName)) {
            $this->help();
        } else {
            $this->runJob($jobName);
        }
    }

    /**
     * Runs provided job
     *
     * @param string $jobName
     */
    private function runJob($jobName)
    {
        try {
            $result = RetailcrmJobManager::runJob($jobName, true, true);
            RetailcrmLogger::output(sprintf(
                'Job %s was executed, result: %s',
                $jobName,
                $result ? 'true' : 'false'
            ));
        } catch (\Exception $exception) {
            $this->printStack($exception);
            self::clearCurrentJob($jobName);
        } finally {
            if (isset($result) && $result) {
                self::clearCurrentJob($jobName);
            }
        }
    }

    /**
     * Prints error details
     *
     * @param \Exception $exception
     */
    private function printStack($exception)
    {
        RetailcrmLogger::output(sprintf('Error while executing a job: %s', $exception->getMessage()));
        RetailcrmLogger::output(sprintf('%s:%d', $exception->getFile(), $exception->getLine()));
        RetailcrmLogger::output();
        RetailcrmLogger::output($exception->getTraceAsString());
    }

    /**
     * Prints CLI help
     */
    private function help()
    {
        RetailcrmLogger::output('Available jobs:');
        RetailcrmLogger::output();

        foreach (array_keys(RetailCRM::getJobs()) as $job) {
            RetailcrmLogger::output(sprintf(' - %s', $job));
        }

        RetailcrmLogger::output();
        RetailcrmLogger::output('Usage:');
        RetailcrmLogger::output();
        RetailcrmLogger::output(sprintf('> php %s -j <job name> - Runs provided job', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --job <job name> - Runs provided job', $this->cliPath));
        RetailcrmLogger::output(sprintf(
            '> php %s --reset-job-manager - Will reset job manager internal timers & current job name',
            $this->cliPath
        ));
        RetailcrmLogger::output();
    }

    /**
     * Resets JobManager
     */
    private function resetJobManager()
    {
        try {
            if (RetailcrmJobManager::reset()) {
                RetailcrmLogger::output('Job manager internal state was cleared.');
            } else {
                RetailcrmLogger::output('Job manager internal state was NOT cleared.');
            }
        } catch (\Exception $exception) {
            $this->printStack($exception);
        }
    }

    /**
     * Sets current running job. Every job must call this in CLI in order to work properly.
     * Current running job will be cleared automatically after job was finished (or crashed).
     * That way, JobManager will maintain it's data integrity and will coexist with manual runs and cron.
     *
     * @param string $job
     *
     * @return bool
     */
    public static function setCurrentJob($job)
    {
        return (bool) Configuration::updateValue(self::CURRENT_TASK_CLI, $job);
    }

    /**
     * Returns current job or empty string if there's no jobs running at this moment
     *
     * @return string
     */
    public static function getCurrentJob()
    {
        return (string) Configuration::get(self::CURRENT_TASK_CLI);
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
        if (Configuration::hasKey(self::CURRENT_TASK_CLI)) {
            if (is_null($job) || self::getCurrentJob() == $job) {
                return Configuration::deleteByName(self::CURRENT_TASK_CLI);
            }
        }

        return true;
    }
}
