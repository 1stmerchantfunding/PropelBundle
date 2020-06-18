<?php

namespace Propel\Bundle\PropelBundle\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WrapCommand
{
    public static function wrap(Command $command) : Command
    {
        // kludge to ensure there's a return code
        $command->setCode(function (InputInterface $input, OutputInterface $output) use ($command)
        {
            $method = new \ReflectionMethod($command, 'execute');
            $method->setAccessible(true);

            return $method->invoke($command, $input, $output) ?? 0;
        });

        return $command;
    }
}
