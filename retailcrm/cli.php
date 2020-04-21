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
 *  @author    DIGITAL RETAIL TECHNOLOGIES SL <mail@simlachat.com>
 *  @copyright 2020 DIGITAL RETAIL TECHNOLOGIES SL
 *  @license   https://opensource.org/licenses/MIT  The MIT License
 *
 * Don't forget to prefix your containers with your own identifier
 * to avoid any conflicts with others containers.
 */

declare(ticks = 1);

if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
    date_default_timezone_set(@date_default_timezone_get());
}

$_SERVER['HTTPS'] = 1;

require(dirname(__FILE__) . '/../../config/config.inc.php');
require(dirname(__FILE__) . '/../../init.php');
require(dirname(__FILE__) . '/bootstrap.php');

if (!defined('_PS_VERSION_')) {
    exit(1);
}

function retailcrmCliInterruptHandler($signo) {
    RetailcrmLogger::output('WARNING: Interrupt received, stopping...');
    RetailcrmJobManager::clearCurrentJob(null);
    exit(1);
}

/**
 * Class RetailcrmCli
 */
class RetailcrmCli
{
    /**
     * RetailcrmCli constructor.
     */
    public function __construct()
    {
        RetailcrmLogger::setCloneToStdout(true);
    }

    public function execute()
    {
        if (function_exists('pcntl_signal')) {
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            pcntl_signal(SIGINT, 'retailcrmCliInterruptHandler');
            pcntl_signal(SIGTERM, 'retailcrmCliInterruptHandler');
            pcntl_signal(SIGHUP, 'retailcrmCliInterruptHandler');
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
            RetailcrmJobManager::clearCurrentJob($jobName);
        } finally {
            if (isset($result) && $result) {
                RetailcrmJobManager::clearCurrentJob($jobName);
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
        RetailcrmLogger::output(sprintf('> php %s -j <job name> - Runs provided job', __FILE__));
        RetailcrmLogger::output(sprintf('> php %s --job <job name> - Runs provided job', __FILE__));
        RetailcrmLogger::output(sprintf(
            '> php %s --reset-job-manager - Will reset job manager internal timers & current job name',
            __FILE__
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
}

if (php_sapi_name() == 'cli') {
    $cli = new RetailcrmCli();
    $cli->execute();
} else {
    include_once __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
}
