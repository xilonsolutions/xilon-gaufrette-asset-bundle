<?php
/**
 * Created by PhpStorm.
 * User: bra
 * Date: 6/08/15
 * Time: 13:50
 */

declare(strict_types=1);

namespace Xilon\GaufretteAssetsBundle\Twig;


use Gaufrette\Filesystem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Xilon\GaufretteBundle\Service\GaufretteBaseUrlService;

class GaufretteAssetsExtension extends AbstractExtension {


    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $environment,
    ) {
    }
    #[\Override]
    public function getFilters(): array
    {
        return array(
            new TwigFilter('cdn_asset', array($this, 'cdnAssetFilter')),
        );
    }

    public function cdnAssetFilter(string $path): string
    {
        if(!$this->isProd()){
            return $path;
        }
        $cdnUri=GaufretteBaseUrlService::getGaufretteBaseUrl($this->filesystem);
        return sprintf("%s/%s",$cdnUri,$path);
    }
    public function isProd(): bool
    {
        return $this->environment=="prod";
    }
    public function getName(): string
    {
      return "xilon.gauffrete_assets_extension";
    }


}