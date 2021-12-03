<?php
/**
 * MIT License
 *
 * Copyright (c) 2021 DIGITAL RETAIL TECHNOLOGIES SL
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
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2021 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
    date_default_timezone_set(@date_default_timezone_get());
}

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';
require_once dirname(__FILE__) . '/../bootstrap.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class RetailcrmCli
 *
 * @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 * @license   GPL
 *
 * @see      https://retailcrm.ru
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

        $shortopts = 'j:s:';
        $longopts = [
            'job:',
            'shop:',
            'set-web-jobs:',
            'query-web-jobs',
            'run-jobs',
            'reset-job-manager',
            'reset-all',
        ];

        $options = getopt($shortopts, $longopts);
        $jobName = isset($options['j']) ? $options['j'] : (isset($options['job']) ? $options['job'] : null);
        $shopId = isset($options['s']) ? $options['s'] : (isset($options['shop']) ? $options['shop'] : null);

        if (isset($options['reset-job-manager'])) {
            $this->resetJobManager();
        } elseif (isset($options['reset-all'])) {
            $this->resetAll();
        } elseif (isset($options['query-web-jobs'])) {
            $this->queryWebJobs($shopId);
        } elseif (isset($options['run-jobs'])) {
            RetailcrmTools::startJobManager();
        } elseif (isset($options['set-web-jobs'])) {
            $this->setWebJobs(self::getBool($options['set-web-jobs']), $shopId);
        } elseif (empty($jobName)) {
            $this->help();
        } else {
            $this->setCleanupOnShutdown();
            $this->runJob($jobName, $shopId);
        }
    }

    /**
     * Shutdown handler. Moved here in order to keep compatibility with older PHP versions.
     *
     * @param mixed $error
     */
    public function cleanupOnShutdown($error)
    {
        if (null !== $error) {
            self::clearCurrentJob(null);
        }
    }

    /**
     * This will register shutdown handler which will clean lock before shutdown
     */
    private function setCleanupOnShutdown()
    {
        RetailcrmJobManager::setCustomShutdownHandler([$this, 'cleanupOnShutdown']);
    }

    /**
     * Runs provided job
     *
     * @param string $jobName
     */
    private function runJob($jobName, $shopId)
    {
        try {
            $result = RetailcrmJobManager::runJob($jobName, true, false, $shopId);
            RetailcrmLogger::output(sprintf(
                'Job %s was executed, result: %s',
                $jobName,
                $result ? 'true' : 'false'
            ));
        } catch (\Exception $exception) {
            if ($exception instanceof RetailcrmJobManagerException && $exception->getPrevious() instanceof \Exception) {
                $this->printStack($exception->getPrevious());
            } else {
                $this->printStack($exception);
            }

            self::clearCurrentJob($jobName);
        }

        if (isset($result) && $result) {
            self::clearCurrentJob($jobName);
        }
    }

    /**
     * Prints error details
     *
     * @param \Exception $exception
     * @param string $header
     */
    private function printStack($exception, $header = 'Error while executing a job: ')
    {
        RetailcrmLogger::output(sprintf('%s%s', $header, $exception->getMessage()));
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

        foreach ($this->getAllowedJobs() as $job) {
            RetailcrmLogger::output(sprintf(' - %s', $job));
        }

        RetailcrmLogger::output();
        RetailcrmLogger::output('Usage:');
        RetailcrmLogger::output();
        RetailcrmLogger::output(sprintf('> php %s -j <job name> - Runs provided job', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --job <job name> - Runs provided job', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --run-jobs - Run default jobs routine', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --set-web-jobs true / false - Enable or disable web jobs', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --query-web-jobs - Check web jobs status', $this->cliPath));
        RetailcrmLogger::output();
        RetailcrmLogger::output(
            'NOTICE: If you have MultiShop feature enabled, you can additionally ' .
            'specify shop id when manually running job: '
        );
        RetailcrmLogger::output('At default jobs are running for all active shops alternately.');
        RetailcrmLogger::output();
        RetailcrmLogger::output(sprintf('> php %s -j <job name> -s <shop id> - Runs provided job for specified shop', $this->cliPath));
        RetailcrmLogger::output(sprintf('> php %s --job <job name> --shop <shop id> - Runs provided job for specified shop', $this->cliPath));
        RetailcrmLogger::output();
        RetailcrmLogger::output(
            'WARNING: Commands below are dangerous and should be used only when ' .
            "job manager or cli doesn't work properly."
        );
        RetailcrmLogger::output('Use them at your own risk.');
        RetailcrmLogger::output();
        RetailcrmLogger::output(sprintf(
            '> php %s --reset-job-manager - Will reset job manager internal timers & current job name',
            $this->cliPath
        ));
        RetailcrmLogger::output(sprintf(
            '> php %s --reset-all - Will reset the entire job subsystem state, can resolve most problems',
            $this->cliPath
        ));
        RetailcrmLogger::output();
    }

    /**
     * Sets new web jobs state
     *
     * @param bool $state
     * @param $shopId
     */
    private function setWebJobs($state, $shopId = null)
    {
        if (null === $shopId) {
            RetailcrmLogger::output('You must specify shop id');

            return;
        }

        RetailcrmContextSwitcher::setShopContext($shopId);
        $this->loadConfiguration();

        Configuration::updateValue(RetailCRM::ENABLE_WEB_JOBS, $state ? '1' : '0');
        RetailcrmLogger::output('Updated web jobs state.');
        $this->queryWebJobs($shopId);
    }

    /**
     * Prints web jobs status
     *
     * @param $shopId
     */
    private function queryWebJobs($shopId = null)
    {
        if (null === $shopId) {
            RetailcrmLogger::output('You must specify shop id');

            return;
        }

        RetailcrmContextSwitcher::setShopContext($shopId);
        $this->loadConfiguration();

        RetailcrmLogger::output(sprintf(
            'Web jobs status: %s',
            RetailcrmTools::isWebJobsEnabled() ? 'true (enabled)' : 'false (disabled)'
        ));
    }

    /**
     * Load PrestaShop configuration if it's not loaded yet
     */
    private function loadConfiguration()
    {
        if (!Configuration::configurationIsLoaded()) {
            Configuration::loadConfiguration();
        }
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
     * Resets JobManager and cli internal lock
     */
    private function resetAll()
    {
        $this->resetJobManager();
        self::clearCurrentJob(null);
        RetailcrmLogger::output('CLI command lock was cleared.');
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
        return (bool) Configuration::updateGlobalValue(self::CURRENT_TASK_CLI, $job);
    }

    /**
     * Returns current job or empty string if there's no jobs running at this moment
     *
     * @return string
     */
    public static function getCurrentJob()
    {
        return (string) Configuration::getGlobalValue(self::CURRENT_TASK_CLI);
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
        if (null === $job || self::getCurrentJob() == $job) {
            return Configuration::deleteByName(self::CURRENT_TASK_CLI);
        }

        return true;
    }

    /**
     * Converts string param from CLI into boolean
     *
     * @param string $param
     */
    private static function getBool($param)
    {
        if ('true' == $param) {
            return true;
        }

        if ('false' == $param) {
            return false;
        }

        return (bool) $param;
    }

    /**
     * Returns list of jobs which are allowed to be executed via cli
     *
     * @return string[]
     */
    private function getAllowedJobs()
    {
        return [
            'RetailcrmAbandonedCartsEvent',
            'RetailcrmIcmlEvent',
            'RetailcrmIcmlUpdateUrlEvent',
            'RetailcrmSyncEvent',
            'RetailcrmInventoriesEvent',
            'RetailcrmExportEvent',
            'RetailcrmUpdateSinceIdEvent',
            'RetailcrmClearLogsEvent',
        ];
    }
}
