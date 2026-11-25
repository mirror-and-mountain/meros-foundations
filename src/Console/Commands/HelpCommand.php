<?php

namespace MM\Meros\Foundations\Console\Commands;

final class HelpCommand extends Command
{
    /**
     * Lists the available Meros commands.
     *
     * ## EXAMPLES
     *
     *     wp meros
     */
    public function __invoke(): void
    {
        \WP_CLI::line('Available Meros commands:');
        \WP_CLI::line('');

        $commands = \WP_CLI::get_root_command()->get_subcommands();
        ksort($commands);

        foreach ($commands as $command) {
            $name = $command->get_name();
            if (str_starts_with($name, 'meros:')) {
                $description = $command->get_shortdesc();
                \WP_CLI::line('  wp ' . $name . ($description !== '' ? ' - ' . $description : ''));
            }
        }

        \WP_CLI::line('');
        \WP_CLI::line('Run wp help <command> for subcommands, options and examples.');
        \WP_CLI::line('Example: wp help meros:install');
    }
}
