<?php

namespace Ftrrtf\RollbarBundle\EventListener;

use Ftrrtf\Rollbar\ErrorHandler;
use Ftrrtf\Rollbar\Notifier;
use Ftrrtf\RollbarBundle\Helper\UserHelper;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Rollbar framework Listener.
 */
class RollbarListener
{
    /**
     * @var Notifier
     */
    protected $notifier;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var \Throwable|null
     */
    protected $exception;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;
    /**
     * @var UserHelper
     */
    private $userHelper;

    /**
     * Init.
     *
     * @param Notifier                      $notifier
     * @param ErrorHandler                  $errorHandler
     * @param TokenStorageInterface         $tokenStorage
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param UserHelper                    $userHelper
     */
    public function __construct(
        Notifier $notifier,
        ErrorHandler $errorHandler,
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        UserHelper $userHelper
    ) {
        $this->notifier = $notifier;
        $this->errorHandler = $errorHandler;
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->userHelper = $userHelper;

        $self = $this;
        $this->notifier->getEnvironment()
            ->setOption(
                'person_callback',
                function () use ($self) {
                    return $self->getUserData();
                }
            );
    }

    /**
     * Register error handler.
     *
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $this->errorHandler->registerErrorHandler($this->notifier);
        $this->errorHandler->registerShutdownHandler($this->notifier);
    }

    /**
     * Save exception.
     *
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event)
    {
        // Skip HTTP exception
        if ($event->getThrowable() instanceof HttpException) {
            return;
        }

        $this->setException($event->getThrowable());
    }

    /**
     * Report the error of a console command.
     *
     * The console application catches the error itself and renders it, so a PHP
     * exception handler never sees it: this event is the only hook.
     *
     * @param ConsoleErrorEvent $event
     */
    public function onConsoleError(ConsoleErrorEvent $event)
    {
        $this->notifier->reportException($event->getError());
    }

    /**
     * Wrap exception with additional info.
     *
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->getException()) {
            $this->notifier->reportException($this->getException());
            $this->setException(null);
        }
    }

    /**
     * Get current user info.
     *
     * @return null|array
     */
    public function getUserData()
    {
        if (!$this->tokenStorage->getToken()
            || !$this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')
        ) {
            return null;
        }

        $user = $this->tokenStorage->getToken()->getUser();

        if (!$user) {
            return null;
        }

        return $this->userHelper->buildUserData($user);
    }

    /**
     * @return \Throwable|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Throwable|null $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }
}
