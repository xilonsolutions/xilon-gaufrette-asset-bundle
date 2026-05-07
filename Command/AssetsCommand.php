<?php
namespace Xilon\GaufretteAssetsBundle\Command;


use OpenCloud\ObjectStore\Resource\Container;
use OpenCloud\ObjectStore\Service;
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

    protected function configure(){

        $this
            ->setName("xilon:assets:copy")
            ->setDescription('Copy Assets to Gaufrette')
            ->addArgument("folder",InputArgument::OPTIONAL,"Folder to Update")
            ->addOption("delete-all","d", InputOption::VALUE_NONE,"Delete All Before Write");
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folderName=$input->getArgument("folder");
        $deleteAll=$input->getOption("delete-all");

        $container=$this->objectStore->getContainer($this->rackspaceContainerName);

        if($deleteAll){
            $output->writeln("<info> Deleting All Asets</info>");
            $container->deleteAllObjects();
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
    public function uploadFiles(Container $container,$folder, OutputInterface $output){
        $folder = $folder ?? "";
        $finder= new Finder();
        $path=sprintf("%s%s",$this->publicBundlesPath,$folder);
        $finder->files()->in($path)
            ->notName("*.ttf")
            ->notName("*.eot")
            ->notName("*.otf")
            ->notName("*.woff")
            ->notName("*.svg");
        $files=[];
        /** @var SplFileInfo $file */
        foreach($finder as $file) {
            $filePath=sprintf("%s%s/%s",$this->publicBundlesPath,$folder,$file->getRelativePathname());
            $files[] = ["name" => $file->getFilename(), "path" =>$filePath ];
        }
        $fileChunks=array_chunk($files,100);
        foreach($fileChunks as $chunk) {
            $uploaded = false;
            $tryes = 0;
            while (((!$uploaded) && ($tryes < 5))){
                try {
                    $tryes++;
                    $container->uploadObjects($chunk);
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
    public function uploadFontFiles(Container $container,$fileType,$output)
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
                    $container->uploadObject(sprintf("bundles/%s",$file->getRelativePathname()),file_get_contents($file->getRealPath()),
                        [
                            "Access-Control-Allow-Origin"=>"*",
                            "Content-Type" => $contentType
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
    public function getContentType($fileType)
    {
        $return = "";
        switch ($fileType) {
            case "ttf":
                $return = "application/x-font-ttf";
                break;
            case "eot":
                $return = "application/vnd.ms-fontobject";
                break;
            case "otf":
                $return = "font/opentype";
                break;
            case "woff":
                $return = "application/font-woff";
                break;
            case "svg":
                $return = "image/svg+xml";
                break;
        }
        return $return;
    }
}
