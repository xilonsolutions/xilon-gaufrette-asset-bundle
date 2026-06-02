<?php
/**
 * Created by PhpStorm.
 * User: bra
 * Date: 6/08/15
 * Time: 13:50
 */

namespace Xilon\GaufretteAssetsBundle\Twig;


use Gaufrette\Filesystem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Xilon\GaufretteBundle\Service\GaufretteBaseUrlService;

class GaufretteAssetsExtension extends AbstractExtension {


    private $filesystem;
    private $environment;
    public function __construct(Filesystem $filesystem,$environment){
        $this->filesystem=$filesystem;
        $this->environment=$environment;
    }
    public function getFilters()
    {
        return array(
            new TwigFilter('cdn_asset', array($this, 'cdnAssetFilter')),
        );
    }

    public function cdnAssetFilter($path){
        if(!$this->isProd()){
            return $path;
        }
        $cdnUri=GaufretteBaseUrlService::getGaufretteBaseUrl($this->filesystem);
        return sprintf("%s/%s",$cdnUri,$path);
    }
    public function isProd(){
        return $this->environment=="prod";
    }
    public function getName()
    {
      return "xilon.gauffrete_assets_extension";
    }


}