<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfrRest\Mvc\View\Http;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Http\Header\Accept\FieldValuePart\AcceptFieldValuePart;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface;
use Zend\View\Model\ModelInterface;
use ZfrRest\Mvc\Exception;

/**
 * SelectModelListener. This listener is used to select the appropriate ModelInterface instance
 * according to the Accept header
 *
 * @license MIT
 * @since   0.0.1
 */
class SelectModelListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * Map a type to a specific instance of ModelInterface
     *
     * @var array
     */
    protected $typeToModel = array(
        'text/html'              => 'Zend\View\Model\ViewModel',
        'application/xhtml+xml'  => 'Zend\View\Model\ViewModel',
        'application/javascript' => 'Zend\View\Model\JsonModel',
        'application/json'       => 'Zend\View\Model\JsonModel',
    );


    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $sharedManager = $events->getSharedManager();
        $sharedManager->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($this, 'selectModel'), -60);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * @param  MvcEvent $e
     * @return void
     */
    public function selectModel(MvcEvent $e)
    {
        $result = $e->getResult();

        // If the result is already casted to a specific ModelInterface OR if this is a response, we
        // directly return
        if ($result instanceof ModelInterface || $result instanceof ResponseInterface) {
            return;
        }

        /** @var $headers \Zend\Http\Headers */
        $headers = $e->getRequest()->getHeaders();

        if (!$headers->has('accept')) {
            return;
        }

        $acceptHeader = $headers->get('accept');
        $acceptValues = $acceptHeader->getPrioritized();

        foreach ($acceptValues as $type) {
            if ($this->hasModel($type)) {
                $model = $this->getModel($type);
                $model->setVariables($result);

                $e->setResult($model);

                return;
            }
        }
    }

    /**
     * Return true if there is a specific model mapped to the type in the Accept header
     *
     * @param  AcceptFieldValuePart $acceptFieldValue
     * @return bool
     */
    protected function hasModel(AcceptFieldValuePart $acceptFieldValue)
    {
        $typeString = $acceptFieldValue->getTypeString();

        if (isset($this->typeToModel[$typeString])) {
            return true;
        }

        return false;
    }

    /**
     * Get a new instance of a model that match the type in the Accept header
     *
     * @param  AcceptFieldValuePart $acceptFieldValue
     * @return ModelInterface
     */
    protected function getModel(AcceptFieldValuePart $acceptFieldValue)
    {
        $typeString = $acceptFieldValue->getTypeString();
        $model      = $this->typeToModel[$typeString];

        if (!$model instanceof ModelInterface) {
            throw new Exception\DomainException(sprintf(
                '%s expects a valid implementation of Zend\View\Model\ModelInterface; received "%s"',
                __METHOD__,
                $model
            ));
        }

        return new $model();
    }
}
