<?php

declare(strict_types=1);

namespace Terminal42\NotificationCenterBundle\EventListener;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\NotificationCenterBundle\Event\CreateParcelEvent;
use Terminal42\NotificationCenterBundle\Event\GetTokenDefinitionsEvent;
use Terminal42\NotificationCenterBundle\Parcel\Stamp\TokenCollectionStamp;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailToken;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TokenDefinitionInterface;

class AdminEmailTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack, private TokenDefinitionFactoryInterface $tokenDefinitionFactory, private ContaoFramework $contaoFramework)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GetTokenDefinitionsEvent::class => 'onGetTokenDefinitions',
            CreateParcelEvent::class => 'onCreateParcel',
        ];
    }

    public function onGetTokenDefinitions(GetTokenDefinitionsEvent $event): void
    {
        $event->addTokenDefinition($this->getTokenDefinition());
    }

    public function onCreateParcel(CreateParcelEvent $event): void
    {
        if (!$event->getParcel()->hasStamp(TokenCollectionStamp::class)) {
            return;
        }

        $email = $this->getEmailFromPage();

        if (null === $email) {
            $email = $this->getEmailFromConfig();
        }

        if (null === $email) {
            return;
        }

        $event->getParcel()->getStamp(TokenCollectionStamp::class)->tokenCollection->add(
            $this->getTokenDefinition()->createToken($email)
        );
    }

    private function getEmailFromPage(): string|null
    {
        if (null === ($request = $this->requestStack->getCurrentRequest())) {
            return null;
        }

        $pageModel = $request->attributes->get('pageModel');

        if (!$pageModel instanceof PageModel) {
            return null;
        }

        $pageModel->loadDetails();

        return $pageModel->adminEmail ?: null;
    }

    private function getEmailFromConfig(): string|null
    {
        $email = $this->contaoFramework->getAdapter(Config::class)->get('adminEmail');

        return !\is_string($email) ? null : $email;
    }

    private function getTokenDefinition(): TokenDefinitionInterface
    {
        return $this->tokenDefinitionFactory->create(EmailToken::DEFINITION_NAME, 'admin_email', 'admin_email');
    }
}
