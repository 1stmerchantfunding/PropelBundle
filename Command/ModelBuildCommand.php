<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\PropelBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class ModelBuildCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('propel:model:build')
            ->setDescription('Build the model classes based on Propel XML schemas')

            ->addArgument('bundle', InputArgument::OPTIONAL, 'The bundle to generate model classes from')
            // @todo add the other arguments/options handled by the command
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance()
    {
        return new \Propel\Generator\Command\SqlBuildCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input)
    {
        $outputDir = realpath($this->getApplication()->getKernel()->getRootDir().'/../');

        return array(
            '--output-dir' => $outputDir,
        );
    }
}
