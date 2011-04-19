<?php

namespace Propel\PropelBundle\Command;

use Propel\PropelBundle\Command\PhingCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Util\Filesystem;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DataDumpCommand.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class DataDumpCommand extends PhingCommand
{
    protected static $destPath = '/propel/dump';

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Dump data from database into xml file')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Set this parameter to define a connection to use')
            ->setHelp(<<<EOT
The <info>propel:data-dump</info> dumps data from database into xml file.
          
  <info>php app/console propel:data-dump</info>

The <info>--connection</info> parameter allows you to change the connection to use.
The default connection is the active connection (propel.dbal.default_connection).
EOT
            )
            ->setName('propel:data-dump')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $defaultConfig = $this->getConnection($input, $output);

        $this->callPhing('datadump', array(
            'propel.database.url'       => $defaultConfig['connection']['dsn'],
            'propel.database.database'  => $defaultConfig['adapter'],
            'propel.database.user'      => $defaultConfig['connection']['user'],
            'propel.database.password'  => $defaultConfig['connection']['password'],
            'propel.schema.dir'         => $this->getApplication()->getKernel()->getRootDir() . '/propel/schema/',
        ));

        $finder = new Finder();
        $filesystem = new Filesystem();

        $datas = $finder->name('*_data.xml')->in($this->getTmpDir());

        foreach($datas as $data) {
            $dest = $this->getApplication()->getKernel()->getRootDir() . self::$destPath . '/xml/' . $data->getFilename();

            $filesystem->copy((string) $data, $dest);
            $filesystem->remove($data);

            $output->writeln(sprintf('Wrote dumped data in "<info>%s</info>".', $dest));
        }

        if (count($datas) <= 0) {
            $output->writeln('No new dumped files.');
        }
    }
}
