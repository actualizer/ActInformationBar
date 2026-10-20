<?php declare(strict_types=1);

namespace Act\InformationBar;

use Act\InformationBar\Service\InformationBarCacheTag;
use Act\InformationBar\Service\Text\BarTextFilter;
use Act\InformationBar\Service\Text\BarTextStorageCleanup;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Storefront\Framework\Asset\AssetRegistrationService;
use Symfony\Component\HttpKernel\KernelInterface;

class ActInformationBar extends Plugin
{
    public function boot(): void
    {
        parent::boot();
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

    public function postInstall(InstallContext $installContext): void
    {
        parent::postInstall($installContext);
        $this->cleanStoredTexts();
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        parent::postUpdate($updateContext);
        $this->cleanStoredTexts();
    }

    /**
     * Texts written by migrations bypass the write subscriber, so they are cleaned after both lifecycle steps.
     */
    private function cleanStoredTexts(): void
    {
        if ($this->container === null) {
            return;
        }

        // Built by hand: the service does not exist in the container that was compiled before this update.
        $connection = $this->container->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        $cleanup = new BarTextStorageCleanup($connection, new BarTextFilter());

        if ($cleanup->cleanAll() > 0) {
            $invalidator = $this->container->get(CacheInvalidator::class);
            if ($invalidator instanceof CacheInvalidator) {
                $invalidator->invalidate([InformationBarCacheTag::TAG]);
            }
        }
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
        $container = $this->container;
        if ($container === null) {
            return;
        }

        $kernel = $container->get('kernel');
        if (!$kernel instanceof KernelInterface) {
            return;
        }

        // Execute theme compile command
        $process = $kernel->getProjectDir() . '/bin/console theme:compile';
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
