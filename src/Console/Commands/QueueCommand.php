<?php

namespace MM\Meros\Foundations\Console\Commands;

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

/** Runs Laravel's queue commands. */
class QueueCommand extends Command
{
    private const ALLOWED = ['work', 'restart', 'retry', 'failed', 'forget', 'flush', 'clear'];

    /**
     * Run a queue command: work, restart, retry, failed, forget, flush or clear.
     *
     * ## OPTIONS
     *
     * <command>
     * : One of work, restart, retry, failed, forget, flush, clear.
     *
     * [<argument>...]
     * : Command arguments, e.g. the connection name for `work` or job ids for `retry`.
     *
     * [--<field>=<value>]
     * : Any option of the underlying `queue:<command>`, e.g. --queue=default --stop-when-empty --max-time=20.
     *
     * ## EXAMPLES
     *
     *     wp meros:queue work my_connection --queue=my_queue --stop-when-empty
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc)
    {
        $command = array_shift($args);

        if (!in_array($command, self::ALLOWED, true)) {
            \WP_CLI::error('Unknown queue command. Use one of: ' . implode(', ', self::ALLOWED));
        }

        $line = 'queue:' . $command;

        foreach ($args as $argument) {
            $line .= ' ' . escapeshellarg($argument);
        }

        foreach ($assoc as $key => $value) {
            $line .= $value === true ? " --{$key}" : ' --' . $key . '=' . escapeshellarg((string) $value);
        }

        $output = new BufferedOutput();
        $status = $this->app->make(Kernel::class)->call($line, [], $output);

        \WP_CLI::line(trim($output->fetch()));

        $status === 0 ? \WP_CLI::success('Done.') : \WP_CLI::halt($status);
    }
}
