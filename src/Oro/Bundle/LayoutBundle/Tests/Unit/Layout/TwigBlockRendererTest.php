<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\Layout;

use Oro\Bundle\LayoutBundle\Layout\TwigBlockRenderer;

class TwigBlockRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testEnviromentSet()
    {
        $innerRenderer = $this->getMock('Symfony\Bridge\Twig\Form\TwigRendererInterface');
        $environment   = $this->getMockBuilder('\Twig_Environment')->getMock();

        $innerRenderer->expects($this->once())
            ->method('setEnvironment')
            ->with($this->identicalTo($environment));

        new TwigBlockRenderer($innerRenderer, $environment);
    }
}
