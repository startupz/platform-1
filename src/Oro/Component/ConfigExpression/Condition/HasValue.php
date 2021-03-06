<?php

namespace Oro\Component\ConfigExpression\Condition;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

use Oro\Component\ConfigExpression\ContextAccessorAwareInterface;
use Oro\Component\ConfigExpression\ContextAccessorAwareTrait;
use Oro\Component\ConfigExpression\Exception;

/**
 * Checks whether the value exists in the context.
 */
class HasValue extends AbstractCondition implements ContextAccessorAwareInterface
{
    use ContextAccessorAwareTrait;

    /** @var mixed */
    protected $value;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'has';
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->convertToArray($this->value);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($factoryAccessor)
    {
        return $this->convertToPhpCode($this->value, $factoryAccessor);
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array $options)
    {
        if (1 === count($options)) {
            $this->value = reset($options);
        } else {
            throw new Exception\InvalidArgumentException(
                sprintf('Options must have 1 element, but %d given.', count($options))
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isConditionAllowed($context)
    {
        if ($this->value instanceof PropertyPathInterface) {
            return $this->contextAccessor->hasValue($context, $this->value);
        }

        return true;
    }
}
