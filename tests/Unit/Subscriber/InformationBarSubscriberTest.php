<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Subscriber;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\InformationBarResult;
use Act\InformationBar\Service\InformationBarText;
use Act\InformationBar\Service\InformationBarTextLoader;
use Act\InformationBar\Subscriber\InformationBarSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

class InformationBarSubscriberTest extends TestCase
{
    public function testValueContainingSemicolonIsRejected(): void
    {
        $bar = new InformationBarEntity();
        $bar->setTextColor('red; background: url(evil)');

        self::assertStringNotContainsString('--act-bar-text:', $this->styleFor($bar));
    }

    public function testValueContainingClosingBraceIsRejected(): void
    {
        $bar = new InformationBarEntity();
        $bar->setTextColor('red} .evil{color:red');

        self::assertStringNotContainsString('--act-bar-text:', $this->styleFor($bar));
    }

    public function testBareNumericValueGetsPxAppended(): void
    {
        $bar = new InformationBarEntity();
        $bar->setPaddingTop('14');

        self::assertStringContainsString('--act-bar-padding-top: 14px', $this->styleFor($bar));
    }

    public function testValueAlreadyCarryingAUnitIsNotDoublyModified(): void
    {
        $bar = new InformationBarEntity();
        $bar->setPaddingTop('14px');

        $style = $this->styleFor($bar);

        self::assertStringContainsString('--act-bar-padding-top: 14px', $style);
        self::assertStringNotContainsString('14pxpx', $style);
    }

    public function testZeroSurvivesAndStillGetsAUnit(): void
    {
        $bar = new InformationBarEntity();
        $bar->setPaddingTop('0');

        self::assertStringContainsString('--act-bar-padding-top: 0px', $this->styleFor($bar));
    }

    public function testNullAndEmptyValuesAreSkippedSoTheSassFallbackApplies(): void
    {
        $bar = new InformationBarEntity();
        $bar->setTextColor(null);
        $bar->setBackgroundColor('');

        $style = $this->styleFor($bar);

        self::assertStringNotContainsString('--act-bar-text', $style);
        self::assertStringNotContainsString('--act-bar-bg', $style);
    }

    public function testAllTwelvePropertiesAppearWithTheExpectedCustomPropertyNames(): void
    {
        $bar = new InformationBarEntity();
        $bar->setTextColor('#111111');
        $bar->setBackgroundColor('#222222');
        $bar->setPaddingTop('1px');
        $bar->setPaddingBottom('2px');
        $bar->setFontSize('14px');
        $bar->setButtonTextColor('#333333');
        $bar->setButtonTextColorHover('#444444');
        $bar->setButtonBorderColor('#555555');
        $bar->setButtonBorderColorHover('#666666');
        $bar->setButtonBorderWidth('1px');
        $bar->setButtonBackgroundColor('#777777');
        $bar->setButtonBackgroundColorHover('#888888');

        $style = $this->styleFor($bar);

        foreach ([
            '--act-bar-text: #111111',
            '--act-bar-bg: #222222',
            '--act-bar-padding-top: 1px',
            '--act-bar-padding-bottom: 2px',
            '--act-bar-font-size: 14px',
            '--act-bar-btn-text: #333333',
            '--act-bar-btn-text-hover: #444444',
            '--act-bar-btn-border: #555555',
            '--act-bar-btn-border-hover: #666666',
            '--act-bar-btn-border-width: 1px',
            '--act-bar-btn-bg: #777777',
            '--act-bar-btn-bg-hover: #888888',
        ] as $expected) {
            self::assertStringContainsString($expected, $style);
        }
    }

    public function testPageExtensionShowsFalseWhenNoBarWins(): void
    {
        $data = $this->extensionData($this->dispatch($this->loaderReturning(null)));

        self::assertSame(['show' => false], $data);
    }

    public function testPageExtensionCarriesAllPromisedKeysWhenABarWins(): void
    {
        $bar = new InformationBarEntity();
        $bar->setDisplayDuration(7);
        $bar->setFullWidth(true);
        $bar->setShowButton(true);
        $bar->setButtonTarget('_blank');

        $result = new InformationBarResult(
            $bar,
            new InformationBarText('Hallo', 'Mehr', 'Titel', '/mehr'),
        );

        $data = $this->extensionData($this->dispatch($this->loaderReturning($result)));

        self::assertSame(true, $data['show']);
        self::assertSame('Hallo', $data['message']);
        self::assertSame('Mehr', $data['buttonText']);
        self::assertSame('Titel', $data['buttonTitle']);
        self::assertSame('/mehr', $data['buttonUrl']);
        self::assertSame(7, $data['displayDuration']);
        self::assertSame(true, $data['fullWidth']);
        self::assertSame(true, $data['showButton']);
        self::assertSame('_blank', $data['buttonTarget']);
        self::assertArrayHasKey('style', $data);
    }

    public function testAjaxRequestIsSkippedEntirely(): void
    {
        $loader = new class () extends InformationBarTextLoader {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function load(SalesChannelContext $context): ?InformationBarResult
            {
                $this->calls++;

                return null;
            }
        };

        $request = Request::create('/');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $event = $this->dispatch($loader, $request);

        self::assertSame(0, $loader->calls);
        self::assertFalse($event->getPage()->hasExtension('actInformationBar'));
    }

    private function styleFor(InformationBarEntity $bar): string
    {
        $result = new InformationBarResult($bar, new InformationBarText('Text'));
        $data = $this->extensionData($this->dispatch($this->loaderReturning($result)));

        return (string) $data['style'];
    }

    /**
     * @return array<string, mixed>
     */
    private function extensionData(GenericPageLoadedEvent $event): array
    {
        $extension = $event->getPage()->getExtension('actInformationBar');

        self::assertInstanceOf(ArrayStruct::class, $extension);

        return $extension->all();
    }

    private function loaderReturning(?InformationBarResult $result): InformationBarTextLoader
    {
        return new class ($result) extends InformationBarTextLoader {
            public function __construct(private readonly ?InformationBarResult $result)
            {
            }

            public function load(SalesChannelContext $context): ?InformationBarResult
            {
                return $this->result;
            }
        };
    }

    private function dispatch(InformationBarTextLoader $loader, ?Request $request = null): GenericPageLoadedEvent
    {
        $page = new Page();
        $event = new GenericPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), $request ?? Request::create('/'));

        (new InformationBarSubscriber($loader))->onPageLoaded($event);

        return $event;
    }
}
