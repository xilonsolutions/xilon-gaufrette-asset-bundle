<?php
/**
 * Created by PhpStorm.
 * User: bra
 * Date: 5/08/15
 * Time: 9:04
 *
 * EXTEND OF Asset Abstract Command for not doing MKDIR
 */

declare(strict_types=1);

namespace Xilon\GaufretteAssetsBundle\Command;

use Assetic\Asset\AssetCollectionInterface;
use Assetic\Asset\AssetInterface;
use Assetic\Util\VarUtils;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\AsseticBundle\Command\AbstractCommand as BaseAbstractCommand;

abstract class AbstractCommand extends BaseAbstractCommand  {
    /**
     * Performs the asset dump.
     *
     * @param AssetInterface  $asset  An asset
     * @param OutputInterface $stdout The command output
     *
     * @throws RuntimeException If there is a problem writing the asset
     */
    private function doDump(AssetInterface $asset, OutputInterface $stdout): void
    {
        $combinations = VarUtils::getCombinations(
            $asset->getVars(),
            $this->getContainer()->getParameter('assetic.variables')
        );

        foreach ($combinations as $combination) {
            $asset->setValues($combination);

            // resolve the target path
            $target = rtrim($this->basePath, '/').'/'.$asset->getTargetPath();
            $target = str_replace('_controller/', '', $target);
            $target = VarUtils::resolve($target, $asset->getVars(), $asset->getValues());

            if (!is_dir($dir = dirname($target))) {
                $stdout->writeln(sprintf(
                    '<comment>%s</comment> <info>[dir+]</info> %s',
                    date('H:i:s'),
                    $dir
                ));

//                if (false === @mkdir($dir, 0777, true)) {
//                    throw new \RuntimeException('Unable to create directory '.$dir);
//                }
            }

            $stdout->writeln(sprintf(
                '<comment>%s</comment> <info>[file+]</info> %s',
                date('H:i:s'),
                $target
            ));

            if (OutputInterface::VERBOSITY_VERBOSE <= $stdout->getVerbosity()) {
                if ($asset instanceof AssetCollectionInterface) {
                    foreach ($asset as $leaf) {
                        $root = $leaf->getSourceRoot();
                        $path = $leaf->getSourcePath();
                        $stdout->writeln(sprintf('        <comment>%s/%s</comment>', $root ?: '[unknown root]', $path ?: '[unknown path]'));
                    }
                } else {
                    $root = $asset->getSourceRoot();
                    $path = $asset->getSourcePath();
                    $stdout->writeln(sprintf('        <comment>%s/%s</comment>', $root ?: '[unknown root]', $path ?: '[unknown path]'));
                }
            }

            if (false === @file_put_contents($target, $asset->dump())) {
                throw new \RuntimeException('Unable to write file '.$target);
            }
        }
    }
}