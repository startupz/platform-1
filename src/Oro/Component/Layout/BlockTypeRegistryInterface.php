<?php

namespace Oro\Component\Layout;

use Oro\Component\Layout\Exception;

interface BlockTypeRegistryInterface
{
    /**
     * Returns a block type by name.
     *
     * @param string $name The name of the block type
     *
     * @return BlockTypeInterface
     *
     * @throws Exception\InvalidArgumentException if the given name is not valid
     * @throws Exception\LogicException if the block type cannot be created
     */
    public function getBlockType($name);

    /**
     * Returns whether the given block type is supported.
     *
     * @param string $name The name of the block type
     *
     * @return bool Whether the block type is supported
     */
    public function hasBlockType($name);

    /**
     * Returns the chain of all block types starting with the given block type
     * The first element in the chain is the top type in the hierarchy, the last element is the given type
     *
     * @param string|BlockTypeInterface $blockType
     *
     * @return BlockTypeInterface[]
     */
    public function getBlockTypeChain($blockType);
}
