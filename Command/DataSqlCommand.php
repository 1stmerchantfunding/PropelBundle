<?php

namespace Propel\PropelBundle\Command;

use Propel\PropelBundle\Command\PhingCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Util\Filesystem;

/**
 * DataSqlCommand.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class DataSqlCommand extends PhingCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Generates sql from data xml')
            ->setHelp(<<<EOT
The <info>propel:data-sql</info> generates sql from data xml.

  <info>php app/console propel:data-sql</info>
EOT
            )
            ->setName('propel:data-sql')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootDir    = $this->getApplication()->getKernel()->getRootDir();
        $schemaDir  = $rootDir . '/propel/schema/';
        $sqlDir     = $rootDir . '/propel/sql/';
        $xmlDumpDir = $rootDir . '/propel/dump/xml/';

        $filesystem = new Filesystem();

        if (!is_dir($xmlDumpDir)) {
            $filesystem->mkdir($xmlDumpDir);
        }

        $finder = new Finder();
        foreach($finder->name('*_data.xml')->in($xmlDumpDir) as $data) {
            $filesystem->copy((string) $data, $schemaDir . $data->getFilename());
        }

        $this->callPhing('datasql', array(
            'propel.sql.dir'            => $sqlDir,
            'propel.schema.dir'         => $schemaDir,
        ));

        $finder = new Finder();
        foreach($finder->name('*_data.xml')->in($schemaDir) as $data) {
            $filesystem->remove($data);
        }

        $this->writeSummary($output, 'propel-data-sql');
        $output->writeln(sprintf('SQL from XML data dump file is in <comment>%s</comment>.', $sqlDir));
    }
}

