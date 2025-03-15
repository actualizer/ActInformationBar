<?php declare(strict_types=1);

namespace Act\InformationBar;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Storefront\Framework\Asset\AssetRegistrationService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class ActInformationBar extends Plugin
{
    public function boot(): void
    {
        parent::boot();
    }
    
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.xml');
    }
    
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->compileTheme();
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->compileTheme();
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->compileTheme();
    }

    /**
     * Compile theme when plugin is installed, updated or activated
     */
    private function compileTheme(): void
    {
        // Execute theme compile command
        $process = $this->container->get('kernel')->getProjectDir() . '/bin/console theme:compile';
        exec($process);
    }
    
    /**
     * Registriert die kompilierten Assets für das Plugin
     */
    public function getStorefrontScriptPath(): string
    {
        return 'storefront/js/act-information-bar.js';
    }
}
