<?php

namespace Ftrrtf\RollbarBundle\Rollbar;

use Ftrrtf\Rollbar\Environment as BaseEnvironment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configure Symfony specific env.
 */
class Environment extends BaseEnvironment
{
    /**
     * @var Request|null
     */
    protected $request;

    /**
     * @var RequestStack|null
     */
    protected $requestStack;

    /**
     * Cached values for request.
     *
     * @return array|null
     */
    public function getRequestData()
    {
        parent::getRequestData();

        if ($this->getRequest() instanceof Request) {
            if (in_array($this->getRequest()->getMethod(), array('PUT', 'DELETE'))) {
                $this->requestData[$this->getRequest()->getMethod()] = $this->getRequest()->request->all();
            }
        }

        return $this->requestData;
    }

    /**
     * The request set explicitly wins over the request stack.
     *
     * @return Request|null
     */
    public function getRequest()
    {
        if ($this->request instanceof Request) {
            return $this->request;
        }

        if ($this->requestStack instanceof RequestStack) {
            return $this->requestStack->getMasterRequest();
        }

        return null;
    }

    /**
     * @param Request|null $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * The "request" service is gone since Symfony 3.0: the current request is read
     * from the stack when a report is built, so the environment can be created before
     * any request exists (console) and still see the request of a web error.
     *
     * @param RequestStack|null $requestStack
     */
    public function setRequestStack($requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function setDefaultOptions(OptionsResolver $resolver)
    {
        parent::setDefaultOptions($resolver);

        $resolver->setDefaults(
            array(
                'framework' => Kernel::VERSION,
                'anonymize' => false,
            )
        );
    }

    public function getUserIP()
    {
        if ($this->options['anonymize']) {
            return null;
        }

        return parent::getUserIP();
    }

    public function getPersonData()
    {
        if ($this->options['anonymize']) {
            return null;
        }

        return parent::getPersonData();
    }
}
