<?php

namespace spec\Ftrrtf\RollbarBundle\EventListener;

use Closure;
use Ftrrtf\Rollbar\Environment;
use Ftrrtf\Rollbar\ErrorHandler;
use Ftrrtf\Rollbar\Notifier;
use Ftrrtf\RollbarBundle\EventListener\RollbarListener;
use Ftrrtf\RollbarBundle\Helper\UserHelper;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @mixin RollbarListener
 */
class RollbarListenerSpec extends ObjectBehavior
{
    function let(
        Notifier $notifier,
        ErrorHandler $errorHandler,
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        Environment $environment,
        UserHelper $userHelper
    ) {
        $notifier->getEnvironment()->willReturn($environment);
        $environment->setOption('person_callback', Argument::type(Closure::class))->shouldBeCalled();

        $this->beConstructedWith($notifier, $errorHandler, $tokenStorage, $authorizationChecker, $userHelper);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(RollbarListener::class);
    }

    function it_registers_handlers_on_kernel_request(
        ErrorHandler $errorHandler,
        Notifier $notifier,
        RequestEvent $event
    ) {
        $errorHandler->registerErrorHandler($notifier)->shouldBeCalled();
        $errorHandler->registerShutdownHandler($notifier)->shouldBeCalled();

        $this->onKernelRequest($event);
    }

    function it_catches_exception(ExceptionEvent $event, \Exception $exception)
    {
        $event->getThrowable()->willReturn($exception);
        $this->onKernelException($event);
        $this->getException()->shouldReturn($exception);
    }

    function it_catches_php_errors_with_their_own_class(ExceptionEvent $event)
    {
        $error = new \TypeError('boom');
        $event->getThrowable()->willReturn($error);
        $this->onKernelException($event);
        $this->getException()->shouldReturn($error);
    }

    function it_skips_HTTP_exception(ExceptionEvent $event, HttpException $httpException)
    {
        $event->getThrowable()->willReturn($httpException);
        $this->onKernelException($event);
        $this->getException()->shouldReturn(null);
    }

    function it_reports_exception_on_console_error(Notifier $notifier)
    {
        $exception = new \RuntimeException('boom');

        $notifier->reportException($exception)->shouldBeCalled();
        $this->onConsoleError(new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), $exception));
    }

    function it_reports_php_errors_on_console_error(Notifier $notifier)
    {
        $error = new \TypeError('boom');

        $notifier->reportException($error)->shouldBeCalled();
        $this->onConsoleError(new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), $error));
    }

    function it_reports_exception_on_kernel_response(Notifier $notifier, \Exception $exception, ResponseEvent $event)
    {
        $this->setException($exception);
        $notifier->reportException($exception)->shouldBeCalled();
        $this->onKernelResponse($event);
    }

    function it_clears_exception_after_report(Notifier $notifier, \Exception $exception, ResponseEvent $event)
    {
        $this->setException($exception);

        $notifier->reportException($exception)->shouldBeCalled();
        $this->onKernelResponse($event);

        $this->getException()->shouldReturn(null);
    }

    function it_skips_report_if_there_is_no_exception_on_kernel_response(Notifier $notifier, ResponseEvent $event)
    {
        $this->setException(null);
        $notifier->reportException(Argument::any())->shouldNotBeCalled();
        $this->onKernelResponse($event);
    }

    function it_skips_user_data_if_user_is_not_defined(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenInterface $token,
        UserHelper $userHelper
    ) {
        $authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(true);
        $tokenStorage->getToken()->willReturn($token);
        $token->getUser()->willReturn(null);

        $userHelper->buildUserData(Argument::any())->shouldNotBeCalled();

        $this->getUserData()->shouldBeNull();
    }

    function it_skips_user_data_if_user_is_anonymous(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenInterface $token,
        UserHelper $userHelper
    ) {
        $tokenStorage->getToken()->willReturn($token);
        $authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(false);

        $userHelper->buildUserData(Argument::any())->shouldNotBeCalled();

        $this->getUserData()->shouldBeNull();
    }

    function it_gets_user_data_if_user_is_defined(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenInterface $token,
        UserInterface $user,
        UserHelper $userHelper
    ) {
        $authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')->willReturn(true);
        $tokenStorage->getToken()->willReturn($token);
        $token->getUser()->willReturn($user);

        $userHelper->buildUserData($user)->shouldBeCalled();

        $this->getUserData();
    }
}
