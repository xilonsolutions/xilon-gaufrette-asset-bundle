<?php

declare(strict_types=1);

namespace Xilon\GaufretteAssetsBundle\Command;


use OpenStack\ObjectStore\v1\Models\Container;
use OpenStack\ObjectStore\v1\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class AssetsCommand extends Command
{

    const REGEX = "/.*\.(coffee|mustache|nuspec|yml|markdown|txt|json|sh|js|php|md|html|less|scss|py|rst|JS|MD|HTML|LESS|SCSS|PY|RST)$/";

    public function __construct(
        private readonly Service $objectStore,
        private readonly string $rackspaceContainerName,
        private readonly string $publicBundlesPath,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void{

        $this
            ->setName("xilon:assets:copy")
            ->setDescription('Copy Assets to Gaufrette')
            ->addArgument("folder",InputArgument::OPTIONAL,"Folder to Update")
            ->addOption("delete-all","d", InputOption::VALUE_NONE,"Delete All Before Write");
    }
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folderName=$input->getArgument("folder");
        $deleteAll=$input->getOption("delete-all");

        $container=$this->objectStore->getContainer($this->rackspaceContainerName);

        if($deleteAll){
            $output->writeln("<info> Deleting All Asets</info>");
            foreach($container->listObjects() as $storageObject){
                $container->getObject($storageObject->name)->delete();
            }
        }


        $output->writeln("<info> Starting Copy</info>");
        $this->uploadFiles($container,$folderName,$output);
        $output->writeln("<info> Uploading </info><comment> FONT </comment><info> Files </info>");
        $this->uploadFontFiles($container,"ttf",$output);
        $this->uploadFontFiles($container,"eot",$output);
        $this->uploadFontFiles($container,"otf",$output);
        $this->uploadFontFiles($container,"woff",$output);
        $this->uploadFontFiles($container,"svg",$output);
        $output->writeln("<info> Ending Copy</info>");

        return Command::SUCCESS;
    }
    public function uploadFiles(Container $container, ?string $folder, OutputInterface $output): void
    {
        $folder = $folder ?? "";
        $finder= new Finder();
        $path=sprintf("%s%s",$this->publicBundlesPath,$folder);
        $finder->files()->in($path)
            ->notName("*.ttf")
            ->notName("*.eot")
            ->notName("*.otf")
            ->notName("*.woff")
            ->notName("*.svg");
        /** @var SplFileInfo $file */
        foreach($finder as $file) {
            $filePath=sprintf("%s%s/%s",$this->publicBundlesPath,$folder,$file->getRelativePathname());
            $uploaded = false;
            $tryes = 0;
            while (((!$uploaded) && ($tryes < 5))){
                try {
                    $tryes++;
                    $stream = fopen($filePath, "r");
                    $container->createObject([
                        "name"    => $file->getFilename(),
                        "content" => $stream,
                    ]);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $uploaded = true;
                } catch (\Exception $e) {
                    if($tryes>=5){
                        $output->writeln(sprintf("<error>Guzzle Returned ServerErrorResponseException - Cancelled Upload </error>"));
                        throw $e;
                    }
                    $output->writeln(sprintf("<error>Guzzle ServerErrorResponseException We will try again in 5 seconds</error>"));
                    sleep(5);
                }
            }
        }
    }
    public function uploadFontFiles(Container $container, string $fileType, OutputInterface $output): void
    {
        $output->writeln(sprintf("<info> Uploading </info><comment> %s </comment><info> Files </info>",$fileType));
        $finder=new Finder();
        $finder->files()->in($this->publicBundlesPath)->name(sprintf("*.%s",$fileType));
        $contentType=$this->getContentType($fileType);
        foreach($finder as $file){
            $uploaded = false;
            $tryes = 0;
            while (((!$uploaded) && ($tryes < 5))){
                try {
                    $tryes++;
                    /** @var SplFileInfo $file */
                    $container->createObject([
                        "name"        => sprintf("bundles/%s",$file->getRelativePathname()),
                        "content"     => file_get_contents($file->getRealPath()),
                        "contentType" => $contentType,
                        "metadata"    => [
                            "Access-Control-Allow-Origin" => "*",
                        ],
                    ]);
                    $uploaded = true;
                    $output->writeln(sprintf("<info> Uploaded </info><comment> %s </comment><info> File </info>",$file->getRelativePathname()));
                } catch (\Exception $e) {
                    if($tryes>=5){
                        $output->writeln(sprintf("<error>Guzzle Returned ServerErrorResponseException - Cancelled Upload </error>"));
                        throw $e;
                    }
                    $output->writeln(sprintf("<error>Guzzle ServerErrorResponseException We will try again in 5 seconds</error>"));
                    sleep(5);
                }
            }

        }

    }
    public function getContentType(string $fileType): string
    {
        return match ($fileType) {
            "ttf" => "application/x-font-ttf",
            "eot" => "application/vnd.ms-fontobject",
            "otf" => "font/opentype",
            "woff" => "application/font-woff",
            "svg" => "image/svg+xml",
            default => "",
        };
    }
}
