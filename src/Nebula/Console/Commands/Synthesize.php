<?php

namespace Nebula\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psy\Configuration;
use Psy\Shell;
use Psy\VersionUpdater\Checker;

class Synthesize extends Command {
    protected static $defaultName = 'synthesize';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = Configuration::fromInput($input);
        $config->setUpdateCheck(Checker::NEVER);

        $config->getPresenter()->addCasters(
            [
                'Nebula\Collections\Collection' => 'Nebula\Console\SynthesizeCaster::castCollection'
            ]
        );

        $shell = new Shell($config);

        return $shell->run();
    }
}
